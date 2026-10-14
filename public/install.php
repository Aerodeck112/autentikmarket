<?php

/**
 * Autentik Market — INSTALATOR WEB (pentru cPanel fără Terminal).
 *
 * Face, din browser, ce fac comenzile de instalare: scrie .env, generează cheia, rulează migrațiile
 * și datele inițiale, creează contul super admin și cache-urile. Se blochează definitiv după instalare.
 * Logica: App\Support\Installer. Documentație: docs/CPANEL-INSTALL.md.
 */

use App\Support\Installer;

$base = dirname(__DIR__);

if (! is_file($base.'/vendor/autoload.php')) {
    http_response_code(500);
    exit('Lipsește directorul vendor/. Folosiți pachetul de instalare autentikmarket-install-X.Y.Z.zip.');
}
require $base.'/vendor/autoload.php';

$installer = new Installer($base);

if ($installer->installed()) {
    http_response_code(404);
    exit('<!doctype html><meta charset="utf-8"><title>404</title><p style="font-family:sans-serif">Aplicația este deja instalată. Instalatorul este dezactivat.</p>');
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

$installer->ensureToken();

$e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$host = preg_replace('/[^A-Za-z0-9.\-:]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
$domain = preg_replace('/^www\./', '', explode(':', $host)[0]);

$in = [
    'token' => '',
    'app_url' => ($https ? 'https://' : 'http://').$host,
    'db_host' => 'localhost', 'db_port' => '3306', 'db_database' => '', 'db_username' => '', 'db_password' => '',
    'admin_first' => '', 'admin_last' => '', 'admin_email' => '', 'admin_password' => '', 'admin_password2' => '',
    'mail_host' => 'mail.'.$domain, 'mail_port' => '465', 'mail_username' => 'noreply@'.$domain, 'mail_password' => '',
];
$errors = [];
$result = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($in as $k => $_) {
        $in[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $in['db_password'] = (string) ($_POST['db_password'] ?? '');
    $in['admin_password'] = (string) ($_POST['admin_password'] ?? '');
    $in['admin_password2'] = (string) ($_POST['admin_password2'] ?? '');
    $in['mail_password'] = (string) ($_POST['mail_password'] ?? '');

    if (! $installer->checkToken($in['token'])) {
        $errors[] = 'Codul de instalare nu este corect. Îl găsiți în File Manager, în fișierul storage/app/private/install-token.txt.';
    }
    if (! $installer->requirementsMet()) {
        $errors[] = 'Cerințele obligatorii ale serverului nu sunt îndeplinite (vezi lista).';
    }
    if (! filter_var($in['app_url'], FILTER_VALIDATE_URL) || ! preg_match('#^https?://[^/]+$#', rtrim($in['app_url'], '/'))) {
        $errors[] = 'Adresa site-ului trebuie să fie de forma https://autentikmarket.ro (fără / la final).';
    }
    foreach (['db_host' => 'serverul bazei de date', 'db_database' => 'numele bazei de date', 'db_username' => 'utilizatorul bazei de date'] as $k => $label) {
        if ($in[$k] === '') {
            $errors[] = "Completați {$label}.";
        }
    }
    if (! ctype_digit($in['db_port'])) {
        $errors[] = 'Portul bazei de date trebuie să fie un număr (de regulă 3306).';
    }
    if ($in['admin_first'] === '' || $in['admin_last'] === '' || mb_strlen($in['admin_first']) > 100 || mb_strlen($in['admin_last']) > 100) {
        $errors[] = 'Completați prenumele și numele administratorului.';
    }
    if (! filter_var($in['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Adresa de e-mail a administratorului nu este validă.';
    }
    if (mb_strlen($in['admin_password']) < 10 || ! preg_match('/\pL/u', $in['admin_password']) || ! preg_match('/\d/', $in['admin_password'])) {
        $errors[] = 'Parola administratorului: minim 10 caractere, cu litere și cifre.';
    } elseif ($in['admin_password'] !== $in['admin_password2']) {
        $errors[] = 'Cele două parole ale administratorului nu coincid.';
    }
    if ($in['mail_host'] !== '' && ! ctype_digit($in['mail_port'])) {
        $errors[] = 'Portul SMTP trebuie să fie un număr (465 sau 587).';
    }

    if (! $errors && ($dbError = $installer->testDatabase([
        'host' => $in['db_host'], 'port' => $in['db_port'], 'database' => $in['db_database'],
        'username' => $in['db_username'], 'password' => $in['db_password'],
    ]))) {
        $errors[] = $dbError;
    }

    if (! $errors) {
        try {
            $smtp = $in['mail_host'] !== '' && $in['mail_password'] !== '';
            $installer->markInProgress();
            $installer->writeEnv([
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'APP_URL' => rtrim($in['app_url'], '/'),
                'APP_KEY' => Installer::generateKey(),
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $in['db_host'],
                'DB_PORT' => $in['db_port'],
                'DB_DATABASE' => $in['db_database'],
                'DB_USERNAME' => $in['db_username'],
                'DB_PASSWORD' => $in['db_password'],
                'SESSION_SECURE_COOKIE' => str_starts_with($in['app_url'], 'https://') ? 'true' : 'false',
                // Fără SMTP, mesajele se scriu în log până la configurare (nu se pierd erori în coadă).
                'MAIL_MAILER' => $smtp ? 'smtp' : 'log',
                'MAIL_SCHEME' => $in['mail_port'] === '587' ? 'smtp' : 'smtps',
                'MAIL_HOST' => $in['mail_host'] ?: 'mail.'.$domain,
                'MAIL_PORT' => $in['mail_port'] ?: '465',
                'MAIL_USERNAME' => $in['mail_username'],
                'MAIL_PASSWORD' => $in['mail_password'],
                'MAIL_FROM_ADDRESS' => $in['mail_username'] ?: 'noreply@'.$domain,
            ]);
            $result = $installer->setup([
                'email' => $in['admin_email'], 'first_name' => $in['admin_first'], 'last_name' => $in['admin_last'],
                'password' => $in['admin_password'], 'language' => 'ro',
            ]);
            $result['smtp'] = $smtp;
        } catch (Throwable $ex) {
            $result = ['ok' => false, 'log' => [['step' => 'config', 'ok' => false, 'output' => $ex->getMessage()]]];
        }
    }
    $in['admin_password'] = $in['admin_password2'] = $in['db_password'] = $in['mail_password'] = '';
}

$checks = $installer->requirements();
$canInstall = $installer->requirementsMet();
$phpCli = Installer::guessPhpCli();
$stepNames = ['config' => 'Configurare (.env)', 'migrate' => 'Tabelele bazei de date', 'seed' => 'Date inițiale (roluri, șabloane, locații, hărți)', 'storage' => 'Link pentru fișierele încărcate', 'admin' => 'Contul super admin', 'optimize' => 'Cache (optimizare)'];
?>
<!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Instalare · Autentik Market</title>
<style>
    :root { --forest: #1d5733; --forest-dark: #133e23; --sun: #dd8d2b; --clay: #a35f0c; --cream: #faf3e1; --paper: #fffdf7; --ink: #1a2b20; --soft: #4b5a4f; --line: #e9ddbf; }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--cream); color: var(--ink); font: 15px/1.55 -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    main { max-width: 760px; margin: 0 auto; padding: 32px 16px 64px; }
    header img { height: 56px; width: auto; }
    h1 { font-size: 26px; margin: 18px 0 4px; color: var(--forest-dark); }
    h2 { font-size: 17px; margin: 0 0 12px; color: var(--forest); }
    .card { background: var(--paper); border: 1px solid var(--line); border-radius: 18px; padding: 22px; margin-top: 18px; }
    label { display: block; font-size: 13px; font-weight: 600; margin: 12px 0 4px; }
    input { width: 100%; padding: 10px 12px; border: 1px solid #d6c9a6; border-radius: 10px; font: inherit; background: #fff; }
    input:focus { outline: 2px solid var(--sun); border-color: var(--sun); }
    .grid { display: grid; gap: 0 14px; grid-template-columns: 1fr 1fr; }
    @media (max-width: 560px) { .grid { grid-template-columns: 1fr; } }
    .hint { font-size: 12.5px; color: var(--soft); margin: 4px 0 0; }
    .btn { display: inline-block; margin-top: 20px; background: var(--sun); color: var(--forest-dark); border: 0; border-radius: 999px; padding: 13px 26px; font-weight: 700; font-size: 15px; cursor: pointer; text-decoration: none; }
    .btn:disabled { opacity: .5; cursor: not-allowed; }
    .err { background: #fdecea; border: 1px solid #f3c2bd; color: #8a1c12; border-radius: 12px; padding: 12px 16px; margin-top: 18px; }
    .ok { background: #e6f3dc; border: 1px solid #b8dba0; color: #1d4a2a; border-radius: 12px; padding: 12px 16px; }
    ul.checks { list-style: none; padding: 0; margin: 0; font-size: 14px; }
    ul.checks li { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; border-bottom: 1px solid var(--line); }
    ul.checks small { color: var(--soft); }
    .y { color: #2f7d3a; font-weight: 700; } .n { color: #b42318; font-weight: 700; } .w { color: var(--clay); font-weight: 700; }
    code, pre { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; }
    pre { background: var(--forest-dark); color: var(--cream); padding: 12px 14px; border-radius: 10px; white-space: pre-wrap; word-break: break-all; }
    .token { font-size: 20px; letter-spacing: 3px; text-transform: uppercase; font-family: ui-monospace, monospace; }
    details summary { cursor: pointer; font-weight: 600; color: var(--forest); }
    ol li { margin-bottom: 8px; }
</style>
</head>
<body>
<main>
<header><img src="images/brand/logo-wordmark.png" alt="Autentik Market"></header>

<?php if ($result && $result['ok']) { ?>
    <h1>Instalare reușită 🎉</h1>
    <div class="card">
        <p class="ok">Autentik Market este instalat. Instalatorul s-a dezactivat automat.</p>
        <h2 style="margin-top:20px">Ce urmează</h2>
        <ol>
            <li><strong>Cron (obligatoriu):</strong> cPanel → <em>Cron Jobs</em> → <em>Once Per Minute (* * * * *)</em>, comanda:
                <pre><?= $e($phpCli.' '.$base.'/artisan schedule:run >> /dev/null 2>&1') ?></pre>
                <span class="hint">Dacă nu merge, calea corectă a PHP apare în cPanel → MultiPHP Manager (ex. <code>/opt/cpanel/ea-php83/root/usr/bin/php</code> sau <code>/usr/local/bin/php</code>).</span></li>
            <?php if (! $https) { ?>
            <li><strong>SSL:</strong> cPanel → <em>SSL/TLS Status</em> → <em>Run AutoSSL</em>. Apoi, în <code>.env</code>, puneți <code>APP_URL=https://…</code> și <code>SESSION_SECURE_COOKIE=true</code>, apoi CRM → Sistem → <em>Reîncarcă configurația</em>.</li>
            <?php } ?>
            <?php if (empty($result['smtp'])) { ?>
            <li><strong>E-mail:</strong> SMTP nu a fost completat, așa că mesajele se scriu deocamdată doar în log. Completați <code>MAIL_*</code> în <code>.env</code> (File Manager), apoi CRM → Sistem → <em>Reîncarcă configurația</em> și CRM → Setări → <em>Trimite email test</em>.</li>
            <?php } ?>
            <li><strong>Autentificare:</strong> <a href="<?= $e(rtrim($in['app_url'], '/').'/ro/autentificare') ?>"><?= $e(rtrim($in['app_url'], '/').'/ro/autentificare') ?></a> cu adresa <strong><?= $e($in['admin_email']) ?></strong>.</li>
            <li>CRM → Setări: datele organizatorului, linkul Autentik Shop, logo.</li>
        </ol>
        <a class="btn" href="<?= $e(rtrim($in['app_url'], '/').'/ro/autentificare') ?>">Mergi la autentificare</a>
    </div>
<?php } else { ?>
    <h1>Instalare Autentik Market</h1>
    <p class="hint">Completați formularul o singură dată. Instalatorul creează configurația, baza de date și contul de administrator, apoi se dezactivează.</p>

    <?php if ($errors) { ?>
        <div class="err"><strong>Corectați:</strong><ul style="margin:6px 0 0 18px;padding:0"><?php foreach ($errors as $err) { ?><li><?= $e($err) ?></li><?php } ?></ul></div>
    <?php } ?>
    <?php if ($result && ! $result['ok']) { ?>
        <div class="err"><strong>Instalarea s-a oprit.</strong> Puteți corecta și relua (folosind același cod de instalare).
            <ul style="margin:6px 0 0 18px;padding:0"><?php foreach ($result['log'] as $l) { ?><li><?= $e($stepNames[$l['step']] ?? $l['step']) ?>: <?= $l['ok'] ? 'OK' : '<strong>EROARE</strong> — '.$e($l['output']) ?></li><?php } ?></ul></div>
    <?php } ?>

    <div class="card">
        <h2>1. Verificarea serverului</h2>
        <ul class="checks">
            <?php foreach ($checks as $c) { ?>
                <li><span><?= $e($c['label']) ?><?php if (! $c['ok']) { ?><br><small><?= $e($c['detail']) ?></small><?php } ?></span>
                    <span class="<?= $c['ok'] ? 'y' : ($c['required'] ? 'n' : 'w') ?>"><?= $c['ok'] ? '✓' : ($c['required'] ? '✗' : 'recomandat') ?></span></li>
            <?php } ?>
        </ul>
        <?php if (! $canInstall) { ?><p class="err">Rezolvați punctele marcate cu ✗, apoi reîncărcați pagina.</p><?php } ?>
    </div>

    <form method="post" autocomplete="off" onsubmit="var b = this.querySelector('[type=submit]'); b.textContent = 'Se instalează… (până la un minut)'; setTimeout(function () { b.disabled = true; }, 0);">
        <div class="card">
            <h2>2. Codul de instalare</h2>
            <p class="hint" style="margin-top:0">Pentru siguranță, doar cine are acces la găzduire poate instala. În cPanel → <em>File Manager</em>, deschideți fișierul
                <code><?= $e(basename($base)) ?>/storage/app/private/install-token.txt</code> (click dreapta → <em>View</em>) și copiați codul de 12 caractere.</p>
            <label for="token">Cod de instalare</label>
            <input class="token" id="token" name="token" value="<?= $e($in['token']) ?>" required maxlength="20" spellcheck="false">
        </div>

        <div class="card">
            <h2>3. Site</h2>
            <label for="app_url">Adresa site-ului</label>
            <input id="app_url" name="app_url" value="<?= $e($in['app_url']) ?>" required>
            <p class="hint">Ex. <code>https://autentikmarket.ro</code>. <?php if (! $https) { ?><strong>Pagina nu este deschisă prin HTTPS</strong>: dacă domeniul are deja SSL, deschideți instalatorul cu https://.<?php } ?></p>
        </div>

        <div class="card">
            <h2>4. Baza de date</h2>
            <p class="hint" style="margin-top:0">Creată în cPanel → <em>MySQL Databases</em> (baza, utilizatorul și „Add User To Database” cu <em>ALL PRIVILEGES</em>). Numele au de obicei prefixul contului, ex. <code>user_autentik</code>.</p>
            <div class="grid">
                <div><label for="db_database">Numele bazei de date</label><input id="db_database" name="db_database" value="<?= $e($in['db_database']) ?>" required></div>
                <div><label for="db_username">Utilizator</label><input id="db_username" name="db_username" value="<?= $e($in['db_username']) ?>" required></div>
                <div><label for="db_password">Parola utilizatorului</label><input id="db_password" name="db_password" type="password" autocomplete="new-password"></div>
                <div class="grid" style="grid-template-columns:2fr 1fr">
                    <div><label for="db_host">Server</label><input id="db_host" name="db_host" value="<?= $e($in['db_host']) ?>" required></div>
                    <div><label for="db_port">Port</label><input id="db_port" name="db_port" value="<?= $e($in['db_port']) ?>" required></div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>5. Contul tău de administrator (super admin)</h2>
            <div class="grid">
                <div><label for="admin_first">Prenume</label><input id="admin_first" name="admin_first" value="<?= $e($in['admin_first']) ?>" required></div>
                <div><label for="admin_last">Nume</label><input id="admin_last" name="admin_last" value="<?= $e($in['admin_last']) ?>" required></div>
            </div>
            <label for="admin_email">E-mail (pentru autentificare)</label>
            <input id="admin_email" name="admin_email" type="email" value="<?= $e($in['admin_email']) ?>" required>
            <div class="grid">
                <div><label for="admin_password">Parolă</label><input id="admin_password" name="admin_password" type="password" autocomplete="new-password" required minlength="10"></div>
                <div><label for="admin_password2">Repetă parola</label><input id="admin_password2" name="admin_password2" type="password" autocomplete="new-password" required minlength="10"></div>
            </div>
            <p class="hint">Minim 10 caractere, cu litere și cifre.</p>
        </div>

        <div class="card">
            <details <?= $in['mail_password'] !== '' ? 'open' : '' ?>>
                <summary>6. E-mail SMTP (opțional, se poate completa și mai târziu)</summary>
                <p class="hint">Creați în cPanel → <em>Email Accounts</em> adresa (ex. <code>noreply@<?= $e($domain) ?></code>). Datele SMTP apar la <em>Connect Devices</em>.</p>
                <div class="grid">
                    <div><label for="mail_host">Server SMTP</label><input id="mail_host" name="mail_host" value="<?= $e($in['mail_host']) ?>"></div>
                    <div><label for="mail_port">Port</label><input id="mail_port" name="mail_port" value="<?= $e($in['mail_port']) ?>"></div>
                    <div><label for="mail_username">Adresa de e-mail (utilizator)</label><input id="mail_username" name="mail_username" value="<?= $e($in['mail_username']) ?>"></div>
                    <div><label for="mail_password">Parola e-mailului</label><input id="mail_password" name="mail_password" type="password" autocomplete="new-password"></div>
                </div>
            </details>
        </div>

        <button class="btn" type="submit" <?= $canInstall ? '' : 'disabled' ?>>Instalează</button>
    </form>
<?php } ?>
</main>
</body>
</html>
