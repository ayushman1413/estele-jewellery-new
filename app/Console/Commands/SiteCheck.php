<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Payment\RazorpayGateway;
use App\Services\Vendors\PanelAccessService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * One command that says what on this server stops email or payments from
 * working, without sending an email or creating a payment:
 *
 *   php artisan site:check
 *
 * The deploy runs it after every release (with --github, which also turns
 * each problem into an annotation on the GitHub Actions run), so the live
 * server's state can be read from the Actions page without logging in.
 * Nothing secret is printed: no passwords, and only the start of the key id.
 */
class SiteCheck extends Command
{
    /** Written by the schedule every five minutes (see bootstrap/app.php). */
    public const SCHEDULER_HEARTBEAT_KEY = 'site-check:scheduler-last-run';

    protected $signature = 'site:check {--github : Also print GitHub Actions annotations}';

    protected $description = 'Check this server\'s settings for email, online payment, database, storage and the scheduler (sends and charges nothing)';

    private int $failures = 0;

    private int $warnings = 0;

    public function handle(RazorpayGateway $razorpay): int
    {
        $this->section('Version', fn () => $this->checkVersion());
        $this->section('App', fn () => $this->checkApp());
        $this->section('Database', fn () => $this->checkDatabase());
        $this->section('Cache and sessions', fn () => $this->checkCache());
        $this->section('Storage', fn () => $this->checkStorage());
        $this->section('Email', fn () => $this->checkMail());
        $this->section('Online payment (Razorpay)', fn () => $this->checkRazorpay($razorpay));
        $this->section('Scheduled jobs', fn () => $this->checkScheduler());

        $this->newLine();
        $summary = "{$this->failures} problem(s), {$this->warnings} warning(s).";
        $this->failures > 0 ? $this->error($summary) : $this->info($summary);

        return $this->failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function checkVersion(): void
    {
        $file = base_path('REVISION');

        if (is_file($file)) {
            $this->ok('Deployed commit '.trim((string) file_get_contents($file)));
        } else {
            $this->caution('No REVISION file, so this code was not put here by the GitHub deploy (or predates this check).');
        }
    }

    private function checkApp(): void
    {
        $env = (string) config('app.env');
        $production = $env === 'production';
        $url = (string) config('app.url');
        $host = (string) parse_url($url, PHP_URL_HOST);

        $production ? $this->ok('APP_ENV=production') : $this->caution("APP_ENV={$env} (the live site should say production)");

        if (config('app.debug') && $production) {
            $this->problem('APP_DEBUG=true shows error details (and settings) to shoppers. Set APP_DEBUG=false.');
        }

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1'], true)) {
            $this->problem("APP_URL={$url}: links in emails (vendor setup links, password resets) point there. Set it to the site's address, e.g. https://yourdomain.com");
        } elseif ($production && ! str_starts_with($url, 'https://')) {
            $this->caution("APP_URL={$url} is not https://, so links in emails use http.");
        } else {
            $this->ok("APP_URL={$url}");
        }

        if (app()->configurationIsCached()) {
            $this->ok('Settings are cached. After editing .env run: php artisan optimize:clear && php artisan optimize');
        }
    }

    private function checkDatabase(): void
    {
        DB::connection()->getPdo();
        $this->ok('Connected ('.DB::connection()->getDriverName().', database '.DB::connection()->getDatabaseName().')');

        $migrator = app('migrator');
        $files = $migrator->getMigrationFiles(array_merge($migrator->paths(), [database_path('migrations')]));
        $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());

        if ($pending) {
            $this->problem(count($pending).' database update(s) not applied (e.g. '.reset($pending).'). Run: php artisan migrate --force');
        } else {
            $this->ok('All database migrations applied');
        }
    }

    private function checkCache(): void
    {
        $token = Str::random(12);
        Cache::put('site-check:probe', $token, 60);
        Cache::get('site-check:probe') === $token
            ? $this->ok('Cache works (store: '.config('cache.default').')')
            : $this->problem('Cache store "'.config('cache.default').'" does not keep values.');
        Cache::forget('site-check:probe');

        $this->ok('Sessions: '.config('session.driver'));

        // Customers log in once and should stay logged in on this browser
        // until they manually log out (login already calls Auth::login with
        // remember: true) — that promise is broken if the server's .env still
        // carries Laravel's stock session settings (SESSION_LIFETIME=120,
        // i.e. 2 hours) instead of this app's long-lived defaults, or has
        // SESSION_EXPIRE_ON_CLOSE turned on. Either one logs shoppers out
        // exactly the way "close the browser, come back, logged out" reports.
        $lifetimeMinutes = (int) config('session.lifetime');

        if (config('session.expire_on_close')) {
            $this->problem('SESSION_EXPIRE_ON_CLOSE=true: every session cookie is deleted the moment the browser closes, logging customers out even with "remember me". Set SESSION_EXPIRE_ON_CLOSE=false in .env.');
        } elseif ($lifetimeMinutes < 60 * 24 * 30) {
            $this->problem("SESSION_LIFETIME={$lifetimeMinutes} minutes (about ".round($lifetimeMinutes / 60, 1).' hours) — the session cookie itself expires and is deleted by the browser after that long, which shows up as a logged-out customer. Set SESSION_LIFETIME=2628000 in .env (about 5 years) so login lasts until the customer logs out manually.');
        } else {
            $this->ok('SESSION_LIFETIME='.$lifetimeMinutes.' minutes, SESSION_EXPIRE_ON_CLOSE=false — logins persist across browser restarts until logout.');
        }

        if (config('session.driver') === 'redis') {
            try {
                Cache::store('redis')->put('site-check:session-probe', $token, 60);
                Cache::store('redis')->forget('site-check:session-probe');
            } catch (\Throwable $e) {
                $this->problem('SESSION_DRIVER=redis but Redis could not be reached ('.Str::limit($e->getMessage(), 150).'): sessions silently fail to persist, which looks exactly like customers being logged out. Fix the Redis connection or set SESSION_DRIVER=database.');
            }
        }
    }

    private function checkStorage(): void
    {
        foreach (['storage/logs', 'storage/framework/views', 'storage/framework/cache', 'storage/app/public'] as $dir) {
            if (! is_writable(base_path($dir))) {
                $this->problem("{$dir} is not writable by PHP.");
            }
        }

        is_link(public_path('storage')) || is_dir(public_path('storage'))
            ? $this->ok('public/storage link present (uploaded images can be shown)')
            : $this->problem('public/storage link is missing, so images uploaded in the admin do not show. Create it: ln -s ../storage/app/public public/storage');
    }

    private function checkMail(): void
    {
        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->problem("MAIL_MAILER={$mailer}: emails (vendor setup links, password resets) are only written to the log, never sent.");
            $this->line('        '.str_replace("\n", "\n        ", PanelAccessService::mailSetupHint()));

            return;
        }

        if ($mailer !== 'smtp') {
            $this->ok("Mailer: {$mailer} (not checked here; use php artisan mail:test you@example.com)");

            return;
        }

        $smtp = config('mail.mailers.smtp');
        $username = (string) ($smtp['username'] ?? '');
        $this->line("        Server {$smtp['host']}:{$smtp['port']} ({$smtp['scheme']}), user ".($username ?: '(none)').", from {$from}");

        if (blank($from) || $from === 'hello@example.com') {
            $this->problem('MAIL_FROM_ADDRESS is not set to a real address.');
        } elseif (str_ends_with(strtolower($username), '@gmail.com') && strcasecmp($username, $from) !== 0) {
            $this->caution('Gmail sends as the signed-in account: set MAIL_FROM_ADDRESS to the same address as MAIL_USERNAME.');
        }

        try {
            $transport = Mail::mailer('smtp')->getSymfonyTransport();
            $transport->start();
            $transport->stop();
            $this->ok("Signed in to {$smtp['host']} (no email was sent)");
        } catch (\Throwable $e) {
            $message = trim($e->getMessage());
            $this->problem('The mail server refused: '.Str::limit(preg_replace('/\s+/', ' ', $message), 300));

            if (preg_match('/535|534|Username and Password not accepted|authenticat/i', $message)) {
                $this->line('        Wrong MAIL_USERNAME / MAIL_PASSWORD. For Gmail, MAIL_PASSWORD must be a 16-letter App Password (Google Account > Security > 2-Step Verification > App passwords), written without spaces, not the normal Gmail password.');
            } elseif (preg_match('/timed out|refused|could not be established|getaddrinfo|No route/i', $message)) {
                $this->line("        This server cannot reach {$smtp['host']}:{$smtp['port']}. Try the other port (465 with MAIL_SCHEME=smtps, or 587 with MAIL_SCHEME=smtp), or send through Hostinger's own mail (MAIL_HOST=smtp.hostinger.com with a mailbox created in hPanel).");
            }
        }
    }

    private function checkRazorpay(RazorpayGateway $razorpay): void
    {
        $provider = Setting::where('key', 'payment_provider')->value('value') ?: 'cod';
        $on = $provider === 'razorpay';
        $keyId = (string) config('services.razorpay.key_id');

        $on
            ? $this->ok('Online payment is switched on (payment_provider = razorpay)')
            : $this->caution("Online payment is off (payment_provider = {$provider}): only Cash on Delivery is offered. Switch it on in the admin under Settings.");

        if (! $razorpay->isConfigured()) {
            $message = 'RAZORPAY_KEY_ID and/or RAZORPAY_KEY_SECRET are not set in .env.';
            $on ? $this->problem($message.' Checkout falls back to Cash on Delivery only.') : $this->caution($message);

            return;
        }

        $mode = str_starts_with($keyId, 'rzp_live_') ? 'LIVE' : (str_starts_with($keyId, 'rzp_test_') ? 'TEST' : 'unrecognised');
        $this->line('        Key '.substr($keyId, 0, 12)."... ({$mode} key)");

        if ($mode === 'TEST' && config('app.env') === 'production') {
            $this->caution('Test keys: payments go through Razorpay\'s test mode and no real money is taken. Use rzp_live_ keys to take real payments.');
        }

        if (filled(config('services.razorpay.base_url'))) {
            $this->problem('RAZORPAY_BASE_URL is set, so payments go to '.config('services.razorpay.base_url').' instead of Razorpay. Remove it from .env (it is for local testing only).');
        }

        try {
            $razorpay->verifyCredentials();
            $this->ok('Razorpay accepted the key id and secret');
        } catch (RequestException $e) {
            $this->problem($e->response->status() === 401
                ? 'Razorpay rejected the keys (401): the key id and secret do not match, or are from different modes (test/live). Copy both again from the Razorpay dashboard.'
                : 'Razorpay answered HTTP '.$e->response->status().': '.Str::limit((string) ($e->response->json('error.description') ?? $e->response->body()), 200));
        } catch (ConnectionException $e) {
            $this->problem('This server cannot reach Razorpay ('.Str::limit($e->getMessage(), 150).'). Ask the host to allow outgoing HTTPS to api.razorpay.com.');
        }

        if (blank(config('services.razorpay.webhook_secret'))) {
            $this->caution('RAZORPAY_WEBHOOK_SECRET is not set: Razorpay webhooks are rejected, so a payment whose tab closed early is only picked up when the customer comes back or by the unpaid-order check.');
        }
    }

    private function checkScheduler(): void
    {
        $last = Cache::get(self::SCHEDULER_HEARTBEAT_KEY);

        if ($last && now()->diffInMinutes($last, absolute: true) <= 15) {
            $this->ok("Running (last run {$last})");

            return;
        }

        $this->caution(($last ? "Last ran {$last}, not in the last 15 minutes." : 'Has not run on this server.')
            .' Unpaid online orders are not cancelled and wallet credits do not expire without it. Add this cron job in hPanel (Advanced > Cron Jobs), every minute: cd '.base_path().' && php artisan schedule:run');
    }

    private function section(string $title, \Closure $check): void
    {
        $this->newLine();
        $this->line("<options=bold>{$title}</>");

        try {
            $check();
        } catch (\Throwable $e) {
            $this->problem(Str::limit(preg_replace('/\s+/', ' ', $e->getMessage()), 300));
        }
    }

    private function ok(string $message): void
    {
        $this->line("  <info>OK</info>    {$message}");
    }

    private function caution(string $message): void
    {
        $this->warnings++;
        $this->line("  <comment>WARN</comment>  {$message}");

        if ($this->option('github')) {
            $this->line('::warning::'.$message);
        }
    }

    private function problem(string $message): void
    {
        $this->failures++;
        $this->line("  <error>FAIL</error>  {$message}");

        if ($this->option('github')) {
            $this->line('::error::'.$message);
        }
    }
}
