<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * SMTP configurat din CRM → Setări → E-mail (pentru găzduirile fără Terminal: fără editarea .env).
 * Când serverul SMTP este completat în CRM, are prioritate față de MAIL_* din .env.
 * Parola se păstrează criptată cu APP_KEY și nu este afișată niciodată înapoi.
 */
class MailSettings
{
    public const ENCRYPTIONS = ['ssl', 'tls'];

    /** Aplică setările din CRM peste config('mail.*'). Apelat la pornirea aplicației (web + cron/coadă). */
    public static function apply(): void
    {
        try {
            $host = trim((string) Settings::get('mail.smtp_host', ''));
            if ($host === '') {
                return;
            }
            config(self::config(
                $host,
                (int) Settings::get('mail.smtp_port', 465),
                (string) Settings::get('mail.smtp_encryption', 'ssl'),
                (string) Settings::get('mail.smtp_username', ''),
                self::password(),
                Settings::get('mail.smtp_verify_peer', '1') !== '0',
                (string) Settings::get('mail.from_address', ''),
                (string) Settings::get('mail.from_name', ''),
            ));
            Mail::purge('smtp');
        } catch (Throwable) {
            // Fără bază de date (instalare) sau cheie schimbată: rămân valorile din .env.
        }
    }

    /** @return array<string, mixed> chei config() pentru mailerul SMTP */
    public static function config(string $host, int $port, string $encryption, string $username, string $password, bool $verifyPeer, string $from, string $fromName): array
    {
        return [
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $port,
            // ssl = conexiune criptată de la început (de regulă 465); tls = STARTTLS (de regulă 587)
            'mail.mailers.smtp.scheme' => $encryption === 'tls' ? 'smtp' : 'smtps',
            'mail.mailers.smtp.username' => $username !== '' ? $username : null,
            'mail.mailers.smtp.password' => $password !== '' ? $password : null,
            'mail.mailers.smtp.verify_peer' => $verifyPeer,
            'mail.mailers.smtp.timeout' => 20,
            'mail.from.address' => $from !== '' ? $from : ($username !== '' ? $username : config('mail.from.address')),
            'mail.from.name' => $fromName !== '' ? $fromName : config('mail.from.name'),
        ];
    }

    public static function password(): string
    {
        $stored = (string) Settings::get('mail.smtp_password', '');

        return $stored === '' ? '' : Crypt::decryptString($stored);
    }

    public static function encryptPassword(string $plain): string
    {
        return Crypt::encryptString($plain);
    }

    /** Configurat din CRM (are prioritate față de .env)? */
    public static function fromCrm(): bool
    {
        return trim((string) Settings::get('mail.smtp_host', '')) !== '';
    }

    /** E-mailurile chiar pleacă? (log/array = doar scrise local, nimic nu ajunge la destinatar) */
    public static function delivers(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    /** Eroarea tehnică a serverului de e-mail → explicație în română + ce de făcut. */
    public static function explain(Throwable $e): string
    {
        $m = $e->getMessage();
        $hint = match (true) {
            (bool) preg_match('/authenticat|535|534|Username and Password not accepted|Invalid login/i', $m) => __('Serverul a respins utilizatorul sau parola. Verifică adresa completă de e-mail ca utilizator și parola contului de e-mail (cPanel → Email Accounts).'),
            (bool) preg_match('/certificate|SSL operation failed|peer name|CN=|verify failed/i', $m) => __('Certificatul serverului de e-mail nu corespunde numelui folosit. Folosește ca server numele afișat în cPanel → Email Accounts → Connect Devices (de ex. numele serverului de hosting) sau dezactivează verificarea certificatului.'),
            (bool) preg_match('/getaddrinfo|php_network_getaddresses|Name or service not known|No such host/i', $m) => __('Numele serverului de e-mail nu există. Verifică „Server SMTP” (de ex. mail.domeniul-tau.ro).'),
            (bool) preg_match('/timed out|Connection refused|Connection could not be established|Network is unreachable/i', $m) => __('Nu se poate face conexiunea la server pe acest port. Încearcă portul 465 cu SSL sau 587 cu TLS; dacă tot nu merge, hostingul poate bloca portul — întreabă suportul.'),
            (bool) preg_match('/550|551|553|554|sender|not owned|not permitted|relay/i', $m) => __('Serverul a refuzat expeditorul. Adresa „De la” trebuie să fie chiar contul de e-mail folosit la autentificare (sau un alias al lui).'),
            default => __('Verifică datele SMTP și încearcă din nou.'),
        };

        return $hint.' '.__('Detaliu tehnic: :m', ['m' => mb_strimwidth(trim($m), 0, 300, '…')]);
    }
}
