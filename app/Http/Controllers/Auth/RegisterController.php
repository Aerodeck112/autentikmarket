<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ConsentType;
use App\Enums\Language;
use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\Producer;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function __construct(protected NotificationService $notifications) {}

    public function show(): View
    {
        return view('auth.register', ['producer' => false]);
    }

    public function showProducer(): View
    {
        return view('auth.register', ['producer' => true]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCommon($request);
        $user = DB::transaction(function () use ($data, $request) {
            $user = $this->createUser($data, $request);
            $user->assignRole('visitor');

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('locale', $user->preferredLanguage()->value);

        $this->notifications->send('ACCOUNT_WELCOME', $user, ['member_code' => $user->member_code], [
            'dedupe_key' => 'welcome:'.$user->id,
            'action_url' => route('account.card'),
            'action_label_ro' => 'Vezi cardul Autentik',
            'action_label_hu' => 'Autentik kártya megtekintése',
        ]);
        $user->sendEmailVerificationNotification();

        return redirect()->route('account.card')->with('success', __('Bine ai venit! Cardul tău Autentik este gata.'));
    }

    public function storeProducer(Request $request): RedirectResponse
    {
        $data = $this->validateCommon($request, [
            'brand_name' => ['required', 'string', 'max:150'],
        ]);

        $user = DB::transaction(function () use ($data, $request) {
            $user = $this->createUser($data, $request);
            $user->assignRole('producer');
            $producer = new Producer([
                'user_id' => $user->id,
                'brand_name' => $data['brand_name'],
                'representative_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'city' => $user->city,
                // Limba producătorului = limba aleasă la înregistrare (modificabilă de admin).
                'preferred_language' => $data['preferred_language'],
            ]);
            $producer->save();

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('locale', $user->preferredLanguage()->value);

        $this->notifications->send('PRODUCER_REGISTERED', $user->producer, ['producer_name' => $user->producer->brand_name], [
            'dedupe_key' => 'producer-registered:'.$user->producer->id,
            'action_url' => route('producer.profile'),
            'action_label_ro' => 'Completează profilul',
            'action_label_hu' => 'Profil kitöltése',
        ]);
        $user->sendEmailVerificationNotification();

        return redirect()->route('producer.profile')->with('success', __('Contul de producător a fost creat. Completează profilul și documentele.'));
    }

    protected function validateCommon(Request $request, array $extra = []): array
    {
        return $request->validate(array_merge([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9 +().-]{6,40}$/'],
            'city' => ['nullable', 'string', 'max:120'],
            // Limba de comunicare: OBLIGATORIE.
            'preferred_language' => ['required', Rule::enum(Language::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
            'privacy' => ['accepted'],
            'newsletter' => ['nullable', 'boolean'],
            'marketing' => ['nullable', 'boolean'],
        ], $extra), [
            'preferred_language.required' => __('Te rugăm să alegi limba în care dorești să comunicăm.'),
        ]);
    }

    protected function createUser(array $data, Request $request): User
    {
        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? null,
            'preferred_language' => $data['preferred_language'],
            'password' => $data['password'],
            'newsletter' => (bool) ($data['newsletter'] ?? false),
            'marketing_consent' => (bool) ($data['marketing'] ?? false),
        ]);

        // GDPR: consimțăminte SEPARATE, cu versiune, dată și IP.
        $consents = [
            ConsentType::Terms->value => true,
            ConsentType::Privacy->value => true,
            ConsentType::Newsletter->value => (bool) ($data['newsletter'] ?? false),
            ConsentType::Marketing->value => (bool) ($data['marketing'] ?? false),
        ];
        foreach ($consents as $type => $granted) {
            Consent::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'type' => $type,
                'version' => config("autentik.legal_versions.{$type}", '1.0'),
                'granted' => $granted,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'source' => 'registration',
            ]);
        }

        return $user;
    }
}
