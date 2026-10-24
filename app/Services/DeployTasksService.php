<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * After a deploy (see docs/DEPLOY.md), the work a terminal would do, made by the app itself: the server has no
 * terminal and the deploy only pulls the code. Ported from icd-laravel's RefreshDeployCachesService, plus the
 * database: when the deployed commit or the .env changed, it runs the migrations, the one-time commands not run yet
 * (ONCE) and rebuilds Laravel's caches. Right after a pull OPcache may still hold the old PHP files for a while, so
 * first the caches are only cleared and the rest waits until the change is SETTLE_SECONDS old. On a failure the
 * caches are cleared and it is tried again on the next change; the status shows on the health check (/up).
 */
class DeployTasksService
{
    public const SETTLE_SECONDS = 90;

    /** Commands to run once in production, after the migrations, each recorded when done. */
    public const ONCE = [
        // balances become fully derived from the movements (manual adjustments turn into adjustment movements)
        'balances-rebuild' => 'balances:rebuild',
    ];

    public function __construct(private ?string $marker = null, private int $settleSeconds = self::SETTLE_SECONDS)
    {
        $this->marker ??= storage_path('framework/deploy-tasks');
    }

    /** The last run: "ok", "failed:<step>:<error>@<file>:<line>", or "pending" (a change not handled yet). */
    public function status(): string
    {
        if ($this->isStale()) {
            return 'pending:' . $this->changedSecondsAgo() . 's';
        }

        return trim((string) @file_get_contents($this->marker . '.status')) ?: 'ok';
    }

    /** How long ago the code or the .env changed (the checkout's last write). */
    public function changedSecondsAgo(): int
    {
        $times = array_filter([@filemtime(base_path('.git/index')), @filemtime(base_path('.git/HEAD')), @filemtime(base_path('.env'))]);

        return $times ? max(0, time() - max($times)) : PHP_INT_MAX;
    }

    /** What the tasks ran for: the commit checked out and the time .env was last changed. */
    public function signature(): ?string
    {
        $head = @file_get_contents(base_path('.git/HEAD'));
        if ($head === false) {
            return null;
        }
        $head = trim($head);
        if (str_starts_with($head, 'ref: ')) {
            $ref = substr($head, 5);
            $commit = @file_get_contents(base_path(".git/{$ref}"));
            if ($commit === false) {
                // After a "git gc" the branch's commit is in packed-refs.
                preg_match('/^([0-9a-f]{40}) ' . preg_quote($ref, '/') . '$/m', (string) @file_get_contents(base_path('.git/packed-refs')), $match);
                $commit = $match[1] ?? '';
            }
            $head = trim($commit);
        }

        return $head === '' ? null : $head . '|' . (int) @filemtime(base_path('.env'));
    }

    public function isStale(): bool
    {
        $signature = $this->signature();

        return $signature !== null && $signature !== @file_get_contents($this->marker);
    }

    /** Runs the tasks when stale; one request at a time (the others go on with what is there). */
    public function execute(): void
    {
        $lock = @fopen($this->marker . '.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return;
        }
        try {
            $signature = $this->signature();
            if ($signature === null || $signature === @file_get_contents($this->marker)) {
                return;
            }
            // Too soon after the change: only cleared (once), the rest on a later request.
            if ($this->changedSecondsAgo() < $this->settleSeconds) {
                if (@file_get_contents($this->marker . '.cleared') !== $signature) {
                    Artisan::call('optimize:clear');
                    file_put_contents($this->marker . '.cleared', $signature);
                }

                return;
            }
            $this->forgetCompiledCode();
            $status = $this->run();
            file_put_contents($this->marker, $signature);
            file_put_contents($this->marker . '.status', $status);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function run(): string
    {
        $step = 'migrate';
        try {
            if (Artisan::call('migrate', ['--force' => true]) !== 0) {
                throw new \RuntimeException('migrate');
            }
            foreach (self::ONCE as $name => $command) {
                $done = $this->marker . '.once-' . $name;
                if (is_file($done)) {
                    continue;
                }
                $step = $name;
                if (Artisan::call($command) !== 0) {
                    throw new \RuntimeException($name);
                }
                file_put_contents($done, date('c'));
            }
            $step = 'optimize';
            Artisan::call('optimize');

            return 'ok';
        } catch (Throwable $error) {
            Artisan::call('optimize:clear');
            report($error);

            // What failed, for the health check (no message: it may hold paths or values).
            return 'failed:' . $step . ':' . class_basename($error) . '@' . basename($error->getFile()) . ':' . $error->getLine();
        }
    }

    /** Drops the app's own PHP files from OPcache, so they are compiled again from what was pulled. */
    private function forgetCompiledCode(): void
    {
        if (! function_exists('opcache_invalidate')) {
            return;
        }
        foreach (['app', 'bootstrap', 'config', 'routes', 'database', 'lib'] as $folder) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($folder), \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() === 'php') {
                    @opcache_invalidate($file->getPathname(), true);
                }
            }
        }
    }
}
