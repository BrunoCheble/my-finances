<?php

namespace App\Http\Controllers;

use App\Services\SystemBackupService;
use Illuminate\Http\Response;

/**
 * Called by the scheduled backup (GitHub Actions). Public on purpose: what it returns is encrypted with
 * the backup public key, so it is useless without the private key.
 */
class SystemBackupController extends Controller
{
    public function __invoke(SystemBackupService $service): Response
    {
        abort_unless(is_file(SystemBackupService::publicKeyPath()), 503);
        [$file, $fingerprint] = $service->execute();

        return response($file, 200, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-store',
            'X-Backup-Fingerprint' => $fingerprint,
        ]);
    }
}
