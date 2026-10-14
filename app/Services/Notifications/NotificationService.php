<?php

namespace App\Services\Notifications;

use App\Contracts\HasPreferredLanguage;
use App\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Traits\Localizable;

/**
 * MOTORUL CENTRAL DE NOTIFICĂRI.
 *
 * Toate comunicările automate trec pe aici. Niciun controller nu trimite mail direct.
 * Limba este aleasă AUTOMAT din destinatar (preferred_language), iar mesajul este
 * trimis DOAR în limba respectivă (nu bilingv).
 */
class NotificationService
{
    use Localizable;

    public function __construct(protected TemplateRenderer $renderer) {}

    /**
     * @param  array{channel?:string, dedupe_key?:string, related?:Model, action_url?:string, action_label_ro?:string, action_label_hu?:string}  $options
     */
    public function send(string $key, HasPreferredLanguage&Model $recipient, array $data = [], array $options = []): ?NotificationLog
    {
        $channel = $options['channel'] ?? 'mail';
        $dedupe = $options['dedupe_key'] ?? null;

        if ($dedupe && ($existing = NotificationLog::where('dedupe_key', $dedupe)->first())) {
            return $existing;
        }

        $template = NotificationTemplate::where('key', $key)->where('channel', $channel)->first();
        if (! $template || ! $template->active) {
            Log::info("Notificare omisă: template {$key}/{$channel} inexistent sau inactiv.");

            return null;
        }

        $lang = $recipient->preferredLanguage()->value;
        $data = array_merge([
            'name' => $recipient->notificationName(),
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
        ], $data);

        $subject = $this->renderer->renderText($template->{"subject_{$lang}"} ?: $template->subject_ro, $data);
        $bodyTemplate = $template->{"body_{$lang}"} ?: $template->body_ro;

        $actionUrl = $options['action_url'] ?? ($data['action_url'] ?? null);
        $actionLabel = $options["action_label_{$lang}"] ?? ($options['action_label_ro'] ?? null);

        // Randăm HTML-ul în limba DESTINATARULUI (inclusiv layout, footer).
        $html = $this->withLocale($lang, fn () => view('mail.templated', [
            'subject' => $subject,
            'bodyHtml' => $this->renderer->renderHtml($bodyTemplate, $data),
            'actionUrl' => $actionUrl,
            'actionLabel' => $actionLabel,
            'lang' => $lang,
        ])->render());

        $email = $recipient->notificationEmail();
        $status = NotificationStatus::Queued;
        $error = null;
        if ($channel === 'mail' && ! $email) {
            $status = NotificationStatus::Skipped;
            $error = 'Destinatarul nu are adresă de e-mail.';
        }
        if ($channel === 'database') {
            $status = NotificationStatus::Sent;
        }

        try {
            $log = NotificationLog::create([
                'template_key' => $key,
                'channel' => $channel,
                'notifiable_type' => $recipient->getMorphClass(),
                'notifiable_id' => $recipient->getKey(),
                'recipient_email' => $email,
                'language' => $lang,
                'subject' => mb_substr($subject, 0, 255),
                'body' => $html,
                'status' => $status,
                'error' => $error,
                'dedupe_key' => $dedupe,
                'related_type' => isset($options['related']) ? $options['related']->getMorphClass() : null,
                'related_id' => isset($options['related']) ? $options['related']->getKey() : null,
                'sent_at' => $status === NotificationStatus::Sent ? now() : null,
            ]);
        } catch (QueryException $e) {
            // Cursă pe dedupe_key: alt proces a creat deja mesajul.
            if ($dedupe && ($existing = NotificationLog::where('dedupe_key', $dedupe)->first())) {
                return $existing;
            }
            throw $e;
        }

        if ($status === NotificationStatus::Queued) {
            SendNotificationJob::dispatch($log->id);
        }

        return $log;
    }

    /** Trimite către mai mulți destinatari (fiecare în limba lui). */
    public function sendMany(string $key, iterable $recipients, callable|array $data = [], array $options = []): int
    {
        $count = 0;
        foreach ($recipients as $recipient) {
            $payload = is_callable($data) ? $data($recipient) : $data;
            $opts = $options;
            if (isset($options['dedupe_prefix'])) {
                $opts['dedupe_key'] = $options['dedupe_prefix'].':'.$recipient->getMorphClass().':'.$recipient->getKey();
            }
            if ($this->send($key, $recipient, $payload, $opts)) {
                $count++;
            }
        }

        return $count;
    }
}
