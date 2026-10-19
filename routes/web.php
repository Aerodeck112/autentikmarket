<?php

use App\Http\Controllers\Account;
use App\Http\Controllers\Auth;
use App\Http\Controllers\Crm;
use App\Http\Controllers\FileController;
use App\Http\Controllers\Producer;
use App\Http\Controllers\PublicStorageController;
use App\Http\Controllers\Site;
use App\Http\Controllers\Staff;
use App\Services\Updates\UpdateManager;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SITE PUBLIC — /ro/... și /hu/... cu slug-uri traduse (lang/{ro,hu}/routes.php)
|--------------------------------------------------------------------------
*/
Route::get('/', Site\LocaleRedirectController::class)->name('root');
Route::get('/sitemap.xml', [Site\SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [Site\SeoController::class, 'robots'])->name('robots');
// Imaginile publice când `storage:link` lipsește (găzduire fără Terminal); altfel le servește Apache direct.
Route::get('/storage/{path}', PublicStorageController::class)->where('path', '.*')->name('storage.public')
    ->withoutMiddleware('web');
Route::get('/language/{locale}', [Site\LocaleRedirectController::class, 'switch'])->whereIn('locale', ['ro', 'hu'])->name('language.switch');

foreach (Locales::supported() as $locale) {
    $t = fn (string $key) => trans("routes.{$key}", [], $locale);

    Route::prefix($locale)->name("{$locale}.")->middleware("locale:{$locale}")->group(function () use ($t) {
        Route::get('/', [Site\HomeController::class, 'index'])->name('home');
        Route::get($t('about'), [Site\PageController::class, 'about'])->name('about');
        Route::get($t('producers'), [Site\ProducerController::class, 'index'])->name('producers.index');
        Route::get($t('producers').'/{producer:slug}', [Site\ProducerController::class, 'show'])->name('producers.show');
        Route::get($t('events'), [Site\EventController::class, 'index'])->name('events.index');
        Route::get($t('events').'/{event:slug}', [Site\EventController::class, 'show'])->name('events.show');
        Route::get($t('events').'/{event:slug}/map.json', [Site\EventController::class, 'map'])->middleware('throttle:map')->name('events.map');
        Route::get($t('blog'), [Site\BlogController::class, 'index'])->name('blog.index');
        Route::get($t('blog').'/{slug}', [Site\BlogController::class, 'show'])->name('blog.show');
        Route::get($t('reviews'), [Site\ReviewController::class, 'index'])->name('reviews.index');
        Route::get($t('for_producers'), [Site\PageController::class, 'forProducers'])->name('for-producers');
        Route::get($t('shop'), [Site\PageController::class, 'shop'])->name('shop');
        Route::get($t('faq'), [Site\PageController::class, 'faq'])->name('faq');
        Route::get($t('terms'), [Site\PageController::class, 'terms'])->name('legal.terms');
        Route::get($t('privacy'), [Site\PageController::class, 'privacy'])->name('legal.privacy');
        Route::get($t('cookies'), [Site\PageController::class, 'cookies'])->name('legal.cookies');
        Route::get($t('contact'), [Site\ContactController::class, 'show'])->name('contact');
        Route::post($t('contact'), [Site\ContactController::class, 'store'])->middleware('throttle:forms')->name('contact.store');
        Route::post($t('newsletter'), [Site\NewsletterController::class, 'store'])->middleware('throttle:forms')->name('newsletter.store');

        Route::middleware('guest')->group(function () use ($t) {
            Route::get($t('login'), [Auth\LoginController::class, 'show'])->name('login');
            Route::post($t('login'), [Auth\LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
            Route::get($t('register'), [Auth\RegisterController::class, 'show'])->name('register');
            Route::post($t('register'), [Auth\RegisterController::class, 'store'])->middleware('throttle:forms')->name('register.store');
            Route::get($t('register_producer'), [Auth\RegisterController::class, 'showProducer'])->name('register.producer');
            Route::post($t('register_producer'), [Auth\RegisterController::class, 'storeProducer'])->middleware('throttle:forms')->name('register.producer.store');
            Route::get($t('forgot_password'), [Auth\PasswordController::class, 'request'])->name('password.request');
            Route::post($t('forgot_password'), [Auth\PasswordController::class, 'email'])->middleware('throttle:forms')->name('password.email');
        });
    });
}

/*
|--------------------------------------------------------------------------
| Autentificare (fără prefix de limbă)
|--------------------------------------------------------------------------
*/
Route::get('/reset-password/{token}', [Auth\PasswordController::class, 'reset'])->middleware('guest')->name('password.reset');
Route::post('/reset-password', [Auth\PasswordController::class, 'update'])->middleware(['guest', 'throttle:forms'])->name('password.update');
Route::post('/logout', [Auth\LoginController::class, 'destroy'])->middleware('auth')->name('logout');
// Invitație din CRM (cumpărător / producător adăugat de organizator): URL semnat, 7 zile, o singură folosire.
Route::get('/invite/{user}', [Auth\InvitationController::class, 'show'])->middleware(['signed', 'throttle:forms'])->name('invite.show');
Route::post('/invite/{user}', [Auth\InvitationController::class, 'accept'])->middleware(['signed', 'throttle:forms'])->name('invite.accept');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', Auth\DashboardRedirectController::class)->name('dashboard.redirect');
    Route::get('/email/verify', [Auth\VerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [Auth\VerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [Auth\VerificationController::class, 'send'])->middleware('throttle:3,1')->name('verification.send');
});

Route::get('/newsletter/confirm/{token}', [Site\NewsletterController::class, 'confirm'])->name('newsletter.confirm');
Route::get('/newsletter/unsubscribe/{token}', [Site\NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');

/*
|--------------------------------------------------------------------------
| QR producător („Am cumpărat de aici”) + recenzii
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/p/{token}', [Account\InteractionController::class, 'show'])->where('token', '[A-Za-z0-9]{20,64}')->name('qr.producer');
    Route::post('/p/{token}', [Account\InteractionController::class, 'store'])->where('token', '[A-Za-z0-9]{20,64}')->middleware('throttle:scan')->name('qr.producer.store');
    Route::get('/review/{interaction}', [Account\ReviewController::class, 'create'])->middleware('signed')->name('reviews.create');
    Route::post('/review/{interaction}', [Account\ReviewController::class, 'store'])->middleware(['signed', 'throttle:forms'])->name('reviews.store');
});

/*
|--------------------------------------------------------------------------
| Fișiere PRIVATE — servite doar după verificarea permisiunilor
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('files')->name('files.')->group(function () {
    Route::get('/producer-documents/{document}', [FileController::class, 'producerDocument'])->name('producer-document');
    Route::get('/contracts/{contract}', [FileController::class, 'contract'])->name('contract');
    Route::get('/annexes/{annex}', [FileController::class, 'annex'])->name('annex');
});

/*
|--------------------------------------------------------------------------
| CONT CLIENT / VIZITATOR
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('/', [Account\AccountController::class, 'index'])->name('index');
    Route::get('/card', [Account\AccountController::class, 'card'])->name('card');
    Route::get('/card/qr.svg', [Account\AccountController::class, 'qr'])->name('qr');
    Route::post('/card/regenerate', [Account\AccountController::class, 'regenerateCard'])->middleware('throttle:forms')->name('card.regenerate');
    Route::get('/history', [Account\AccountController::class, 'history'])->name('history');
    Route::get('/raffles', [Account\RaffleController::class, 'index'])->name('raffles');
    Route::post('/raffles/{raffle}/enter', [Account\RaffleController::class, 'enter'])->middleware('verified')->name('raffles.enter');
    Route::get('/reviews', [Account\ReviewController::class, 'index'])->name('reviews');
    Route::get('/notifications', [Account\AccountController::class, 'notifications'])->name('notifications');
    Route::get('/profile', [Account\ProfileController::class, 'edit'])->name('profile');
    Route::put('/profile', [Account\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [Account\ProfileController::class, 'password'])->name('password');
    Route::put('/consents', [Account\ProfileController::class, 'consents'])->name('consents');
    Route::delete('/', [Account\ProfileController::class, 'destroy'])->name('destroy');
});

/*
|--------------------------------------------------------------------------
| ZONA PRODUCĂTOR
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('producer')->name('producer.')->group(function () {
    Route::get('/onboarding', [Producer\OnboardingController::class, 'show'])->name('onboarding');
    Route::post('/onboarding', [Producer\OnboardingController::class, 'store'])->name('onboarding.store');

    Route::middleware('producer')->group(function () {
        Route::get('/', [Producer\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/profile', [Producer\ProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [Producer\ProfileController::class, 'update'])->name('profile.update');
        Route::post('/media', [Producer\ProfileController::class, 'uploadMedia'])->middleware('throttle:forms')->name('media.store');
        Route::delete('/media/{media}', [Producer\ProfileController::class, 'deleteMedia'])->name('media.destroy');
        Route::get('/documents', [Producer\DocumentController::class, 'index'])->name('documents');
        Route::post('/documents', [Producer\DocumentController::class, 'store'])->middleware('throttle:forms')->name('documents.store');
        Route::delete('/documents/{document}', [Producer\DocumentController::class, 'destroy'])->name('documents.destroy');
        Route::post('/submit', [Producer\DocumentController::class, 'submit'])->middleware('verified')->name('submit');
        Route::get('/events', [Producer\EventController::class, 'index'])->name('events');
        Route::get('/events/{event}', [Producer\EventController::class, 'show'])->name('events.show');
        Route::post('/events/{event}/confirm', [Producer\EventController::class, 'confirm'])->name('events.confirm');
        Route::get('/events/{event}/map.json', [Producer\EventController::class, 'map'])->middleware('throttle:map')->name('events.map');
        Route::post('/events/{event}/slots/{slot}/hold', [Producer\EventController::class, 'hold'])->middleware('throttle:forms')->name('events.hold');
        Route::post('/events/{event}/reservations/{reservation}/request', [Producer\EventController::class, 'request'])->name('events.request');
        Route::delete('/events/{event}/reservations/{reservation}', [Producer\EventController::class, 'cancel'])->name('events.cancel');
        Route::get('/scan', [Producer\ScanController::class, 'show'])->name('scan');
        Route::post('/scan', [Producer\ScanController::class, 'store'])->middleware('throttle:scan')->name('scan.store');
        Route::get('/qr.svg', [Producer\ScanController::class, 'qr'])->name('qr');
        Route::get('/reviews', [Producer\DashboardController::class, 'reviews'])->name('reviews');
    });
});

/*
|--------------------------------------------------------------------------
| STAFF EVENIMENT (PWA, mobil)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'can:checkin.perform'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/', [Staff\StaffController::class, 'index'])->name('index');
    Route::get('/scan/{token}', [Staff\StaffController::class, 'scanLink'])->where('token', '[A-Za-z0-9]{20,64}')->name('scan');
    Route::post('/checkin', [Staff\StaffController::class, 'checkin'])->middleware('throttle:scan')->name('checkin');
    Route::get('/search', [Staff\StaffController::class, 'search'])->middleware('throttle:scan')->name('search');
    Route::get('/visitors/{user}', [Staff\StaffController::class, 'visitor'])->name('visitor');
    Route::post('/rewards/{reward}/redeem', [Staff\StaffController::class, 'redeem'])->middleware('can:rewards.redeem')->name('rewards.redeem');
    Route::post('/raffle-winners/{winner}/claim', [Staff\StaffController::class, 'claim'])->middleware('can:raffles.validate')->name('winners.claim');
    Route::get('/producers', [Staff\StaffController::class, 'producers'])->middleware('can:producers.view')->name('producers');
});

/*
|--------------------------------------------------------------------------
| CRM ADMIN
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'can:crm.access'])->prefix('crm')->name('crm.')->group(function () {
    Route::get('/', [Crm\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/search', [Crm\SearchController::class, 'index'])->name('search');

    // Evenimente
    Route::middleware('can:events.view')->group(function () {
        Route::get('/events', [Crm\EventController::class, 'index'])->name('events.index');
        Route::get('/events/create', [Crm\EventController::class, 'create'])->middleware('can:events.manage')->name('events.create');
        Route::post('/events', [Crm\EventController::class, 'store'])->middleware('can:events.manage')->name('events.store');
        Route::get('/events/{event}', [Crm\EventController::class, 'show'])->name('events.show');
        Route::get('/events/{event}/edit', [Crm\EventController::class, 'edit'])->middleware('can:events.manage')->name('events.edit');
        Route::put('/events/{event}', [Crm\EventController::class, 'update'])->middleware('can:events.manage')->name('events.update');
        Route::post('/events/{event}/status', [Crm\EventController::class, 'status'])->middleware('can:events.manage')->name('events.status');
        Route::post('/events/{event}/snapshot', [Crm\EventController::class, 'snapshot'])->middleware('can:maps.manage')->name('events.snapshot');
        Route::post('/events/{event}/photos', [Crm\EventController::class, 'uploadPhotos'])->middleware('can:events.manage')->name('events.photos');
        Route::delete('/events/{event}/photos/{media}', [Crm\EventController::class, 'deletePhoto'])->middleware('can:events.manage')->name('events.photos.destroy');
        Route::post('/events/{event}/post-event', [Crm\EventController::class, 'runPostEvent'])->middleware('can:events.manage')->name('events.post-event');
        Route::get('/events/{event}/export/{type}', [Crm\EventController::class, 'export'])->middleware('can:exports.run')->whereIn('type', ['visitors', 'producers'])->name('events.export');
    });

    // Hartă & standuri (per eveniment) + rezervări
    Route::middleware('can:reservations.manage')->group(function () {
        Route::get('/events/{event}/map', [Crm\EventMapController::class, 'show'])->name('events.map');
        Route::get('/events/{event}/map.json', [Crm\EventMapController::class, 'data'])->name('events.map.data');
        Route::post('/events/{event}/slots/{slot}/block', [Crm\EventMapController::class, 'block'])->name('events.slots.block');
        Route::post('/events/{event}/slots/{slot}/assign', [Crm\EventMapController::class, 'assign'])->name('events.slots.assign');
        Route::get('/events/{event}/reservations', [Crm\ReservationController::class, 'index'])->name('reservations.index');
        Route::post('/reservations/{reservation}/approve', [Crm\ReservationController::class, 'approve'])->name('reservations.approve');
        Route::post('/reservations/{reservation}/reject', [Crm\ReservationController::class, 'reject'])->name('reservations.reject');
        Route::post('/reservations/{reservation}/cancel', [Crm\ReservationController::class, 'cancel'])->name('reservations.cancel');
        Route::post('/registrations/{registration}/attendance', [Crm\ReservationController::class, 'attendance'])->name('registrations.attendance');
        Route::post('/reservations/{reservation}/annex', [Crm\ReservationController::class, 'annex'])->middleware('can:contracts.manage')->name('reservations.annex');
    });

    // Locații + editor hartă master
    Route::middleware('can:maps.manage')->group(function () {
        Route::get('/venues', [Crm\VenueController::class, 'index'])->name('venues.index');
        Route::get('/venues/create', [Crm\VenueController::class, 'create'])->name('venues.create');
        Route::post('/venues', [Crm\VenueController::class, 'store'])->name('venues.store');
        Route::get('/venues/{venue}/edit', [Crm\VenueController::class, 'edit'])->name('venues.edit');
        Route::put('/venues/{venue}', [Crm\VenueController::class, 'update'])->name('venues.update');
        Route::post('/venues/{venue}/layouts', [Crm\LayoutController::class, 'store'])->name('layouts.store');
        Route::get('/layouts/{layout}/editor', [Crm\LayoutController::class, 'editor'])->name('layouts.editor');
        Route::get('/layouts/{layout}/data', [Crm\LayoutController::class, 'data'])->name('layouts.data');
        Route::put('/layouts/{layout}/slots', [Crm\LayoutController::class, 'sync'])->name('layouts.sync');
        Route::post('/layouts/{layout}/background', [Crm\LayoutController::class, 'background'])->name('layouts.background');
        Route::post('/layouts/{layout}/master', [Crm\LayoutController::class, 'makeMaster'])->name('layouts.master');
        Route::post('/layouts/{layout}/duplicate', [Crm\LayoutController::class, 'duplicate'])->name('layouts.duplicate');
    });

    // Producători
    Route::middleware('can:producers.view')->group(function () {
        Route::get('/producers', [Crm\ProducerController::class, 'index'])->name('producers.index');
        Route::get('/producers/export', [Crm\ProducerController::class, 'export'])->middleware('can:exports.run')->name('producers.export');
        Route::get('/producers/create', [Crm\ProducerController::class, 'create'])->middleware('can:producers.manage')->name('producers.create');
        Route::post('/producers', [Crm\ProducerController::class, 'store'])->middleware('can:producers.manage')->name('producers.store');
        Route::post('/producers/{producer}/invite', [Crm\ProducerController::class, 'invite'])->middleware(['can:producers.manage', 'throttle:forms'])->name('producers.invite');
        Route::get('/producers/{producer}', [Crm\ProducerController::class, 'show'])->name('producers.show');
        Route::get('/producers/{producer}/edit', [Crm\ProducerController::class, 'edit'])->middleware('can:producers.manage')->name('producers.edit');
        Route::put('/producers/{producer}', [Crm\ProducerController::class, 'update'])->middleware('can:producers.manage')->name('producers.update');
        Route::post('/producers/{producer}/status', [Crm\ProducerController::class, 'status'])->middleware('can:producers.approve')->name('producers.status');
        Route::post('/producers/{producer}/document-requests', [Crm\ProducerController::class, 'requestDocuments'])->middleware('can:producers.approve')->name('producers.document-requests');
        Route::post('/producers/{producer}/documents/{document}/review', [Crm\ProducerController::class, 'reviewDocument'])->middleware('can:producers.approve')->name('producers.documents.review');
        Route::post('/producers/{producer}/contract', [Crm\ContractController::class, 'generate'])->middleware('can:contracts.manage')->name('producers.contract');
    });
    Route::resource('categories', Crm\CategoryController::class)->except(['show'])->middleware('can:producers.manage');

    // Vizitatori
    Route::middleware('can:visitors.view')->group(function () {
        Route::get('/visitors', [Crm\VisitorController::class, 'index'])->name('visitors.index');
        Route::get('/visitors/export', [Crm\VisitorController::class, 'export'])->middleware('can:exports.run')->name('visitors.export');
        Route::get('/visitors/create', [Crm\VisitorController::class, 'create'])->middleware('can:visitors.manage')->name('visitors.create');
        Route::post('/visitors', [Crm\VisitorController::class, 'store'])->middleware('can:visitors.manage')->name('visitors.store');
        Route::post('/visitors/{user}/invite', [Crm\VisitorController::class, 'invite'])->middleware(['can:visitors.manage', 'throttle:forms'])->name('visitors.invite');
        Route::get('/visitors/{user}', [Crm\VisitorController::class, 'show'])->name('visitors.show');
        Route::put('/visitors/{user}', [Crm\VisitorController::class, 'update'])->middleware('can:visitors.manage')->name('visitors.update');
    });

    // Check-in (listă + manual)
    Route::middleware('can:checkin.perform')->group(function () {
        Route::get('/checkins', [Crm\CheckinController::class, 'index'])->name('checkins.index');
        Route::post('/checkins', [Crm\CheckinController::class, 'store'])->name('checkins.store');
    });

    // Fidelitate
    Route::middleware('can:loyalty.manage')->group(function () {
        Route::get('/loyalty', [Crm\LoyaltyController::class, 'index'])->name('loyalty.index');
        Route::post('/loyalty/rules', [Crm\LoyaltyController::class, 'store'])->name('loyalty.store');
        Route::put('/loyalty/rules/{rule}', [Crm\LoyaltyController::class, 'update'])->name('loyalty.update');
        Route::delete('/loyalty/rules/{rule}', [Crm\LoyaltyController::class, 'destroy'])->name('loyalty.destroy');
    });

    // Tombole
    Route::middleware('can:raffles.manage')->group(function () {
        Route::get('/raffles', [Crm\RaffleController::class, 'index'])->name('raffles.index');
        Route::get('/raffles/create', [Crm\RaffleController::class, 'create'])->name('raffles.create');
        Route::post('/raffles', [Crm\RaffleController::class, 'store'])->name('raffles.store');
        Route::get('/raffles/{raffle}', [Crm\RaffleController::class, 'show'])->name('raffles.show');
        Route::get('/raffles/{raffle}/edit', [Crm\RaffleController::class, 'edit'])->name('raffles.edit');
        Route::put('/raffles/{raffle}', [Crm\RaffleController::class, 'update'])->name('raffles.update');
        Route::post('/raffles/{raffle}/status', [Crm\RaffleController::class, 'status'])->name('raffles.status');
        Route::post('/raffles/{raffle}/draw', [Crm\RaffleController::class, 'draw'])->middleware('can:raffles.draw')->name('raffles.draw');
        Route::post('/raffle-draws/{draw}/void', [Crm\RaffleController::class, 'void'])->middleware('can:raffles.draw')->name('raffles.void');
    });

    // Recenzii
    Route::middleware('can:reviews.moderate')->group(function () {
        Route::get('/reviews', [Crm\ReviewController::class, 'index'])->name('reviews.index');
        Route::post('/reviews/{review}/moderate', [Crm\ReviewController::class, 'moderate'])->name('reviews.moderate');
    });

    // Contracte, anexe, template-uri
    Route::middleware('can:contracts.manage')->group(function () {
        Route::get('/contracts', [Crm\ContractController::class, 'index'])->name('contracts.index');
        Route::post('/contracts/{contract}/status', [Crm\ContractController::class, 'status'])->name('contracts.status');
        Route::get('/document-templates', [Crm\DocumentTemplateController::class, 'index'])->name('templates.index');
        Route::get('/document-templates/{template}/edit', [Crm\DocumentTemplateController::class, 'edit'])->name('templates.edit');
        Route::put('/document-templates/{template}', [Crm\DocumentTemplateController::class, 'update'])->name('templates.update');
        Route::get('/document-templates/{template}/preview', [Crm\DocumentTemplateController::class, 'preview'])->name('templates.preview');
    });

    // Task-uri
    Route::middleware('can:tasks.view')->group(function () {
        Route::get('/tasks', [Crm\TaskController::class, 'index'])->name('tasks.index');
        Route::post('/tasks', [Crm\TaskController::class, 'store'])->middleware('can:tasks.manage')->name('tasks.store');
        Route::put('/tasks/{task}', [Crm\TaskController::class, 'update'])->middleware('can:tasks.manage')->name('tasks.update');
        Route::get('/task-templates', [Crm\TaskTemplateController::class, 'index'])->middleware('can:tasks.manage')->name('task-templates.index');
        Route::post('/task-templates', [Crm\TaskTemplateController::class, 'store'])->middleware('can:tasks.manage')->name('task-templates.store');
        Route::put('/task-templates/{template}', [Crm\TaskTemplateController::class, 'update'])->middleware('can:tasks.manage')->name('task-templates.update');
    });

    // Blog
    Route::middleware('can:blog.manage')->group(function () {
        Route::get('/blog', [Crm\BlogController::class, 'index'])->name('blog.index');
        Route::get('/blog/create', [Crm\BlogController::class, 'create'])->name('blog.create');
        Route::post('/blog', [Crm\BlogController::class, 'store'])->name('blog.store');
        Route::get('/blog/{post}/edit', [Crm\BlogController::class, 'edit'])->name('blog.edit');
        Route::put('/blog/{post}', [Crm\BlogController::class, 'update'])->name('blog.update');
        Route::post('/blog/{post}/status', [Crm\BlogController::class, 'status'])->name('blog.status');
        Route::post('/blog/from-event/{event}', [Crm\BlogController::class, 'fromEvent'])->name('blog.from-event');
    });

    // Automatizări & notificări
    Route::middleware('can:notifications.manage')->group(function () {
        Route::get('/automations', [Crm\AutomationController::class, 'index'])->name('automations.index');
        Route::get('/automations/templates/{template}/edit', [Crm\AutomationController::class, 'edit'])->name('automations.edit');
        Route::put('/automations/templates/{template}', [Crm\AutomationController::class, 'update'])->name('automations.update');
        Route::get('/automations/log', [Crm\AutomationController::class, 'log'])->name('automations.log');
        Route::post('/automations/log/{log}/retry', [Crm\AutomationController::class, 'retry'])->name('automations.retry');
        Route::post('/automations/queue/run', [Crm\AutomationController::class, 'runQueue'])->middleware('throttle:6,1')->name('automations.queue.run');
        Route::post('/automations/queue/retry-failed', [Crm\AutomationController::class, 'retryFailed'])->middleware('throttle:6,1')->name('automations.queue.retry');
    });

    // Rapoarte
    Route::middleware('can:reports.view')->group(function () {
        Route::get('/reports', [Crm\ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/languages/export', [Crm\ReportController::class, 'exportLanguages'])->middleware('can:exports.run')->name('reports.languages.export');
    });

    // Setări
    Route::middleware('can:settings.manage')->group(function () {
        Route::get('/settings', [Crm\SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [Crm\SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/test-email', [Crm\SettingsController::class, 'testEmail'])->middleware('throttle:forms')->name('settings.test-email');
        Route::put('/settings/mail', [Crm\SettingsController::class, 'updateMail'])->middleware('throttle:forms')->name('settings.mail');
        Route::get('/contact-messages', [Crm\SettingsController::class, 'messages'])->name('messages.index');
        Route::post('/contact-messages/{message}/handled', [Crm\SettingsController::class, 'handled'])->name('messages.handled');
    });
    Route::middleware('can:users.manage')->group(function () {
        Route::get('/users', [Crm\UserController::class, 'index'])->name('users.index');
        Route::put('/users/{user}/roles', [Crm\UserController::class, 'roles'])->name('users.roles');
    });

    // SISTEM
    Route::get('/system', [Crm\SystemController::class, 'index'])->middleware('can:system.view')->name('system.index');
    Route::get('/system/audit', [Crm\SystemController::class, 'audit'])->middleware('can:audit.view')->name('system.audit');
    Route::post('/system/refresh-config', [Crm\SystemController::class, 'refreshConfig'])->middleware(['can:system.update', 'throttle:6,1'])->name('system.refresh');
    Route::middleware('can:system.update')->prefix('system/updates')->name('updates.')->group(function () {
        Route::get('/', [Crm\UpdateController::class, 'index'])->name('index');
        Route::post('/', [Crm\UpdateController::class, 'upload'])->middleware('throttle:10,1')->name('upload');
        Route::get('/{update}', [Crm\UpdateController::class, 'show'])->name('show');
        Route::post('/{update}/confirm-destructive', [Crm\UpdateController::class, 'confirmDestructive'])->name('confirm');
        Route::post('/{update}/step/{step}', [Crm\UpdateController::class, 'step'])->whereIn('step', UpdateManager::STEPS)->name('step');
        Route::post('/{update}/rollback', [Crm\UpdateController::class, 'rollback'])->name('rollback');
    });
});
