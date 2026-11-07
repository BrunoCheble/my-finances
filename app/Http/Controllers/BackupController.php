<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SaveBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    /**
     * Downloads the user's data (see SaveBackupService) to the device; nothing is kept on the server.
     */
    public function save(Request $request, SaveBackupService $service): StreamedResponse
    {
        $backup = $service->execute($request->user());

        return response()->streamDownload(function () use ($backup) {
            echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, SaveBackupService::filename(), ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    /**
     * The scheduled backup (GitHub Actions) asks for the data with the user's token (Bearer).
     */
    public function auto(Request $request, SaveBackupService $service): JsonResponse
    {
        $token = (string) $request->bearerToken();
        $user = $token !== '' ? User::where('backup_token_hash', hash('sha256', $token))->first() : null;
        abort_unless($user, 401);

        return response()->json($service->execute($user), 200, ['Cache-Control' => 'no-store'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** A new token for the scheduled backup, shown once; the previous one stops working. */
    public function token(Request $request): RedirectResponse
    {
        $token = Str::random(48);
        $request->user()->forceFill(['backup_token_hash' => hash('sha256', $token)])->save();

        return redirect()->route('profile.edit')->with('backup_token', $token);
    }

    public function revoke(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['backup_token_hash' => null])->save();

        return redirect()->route('profile.edit')->with('status', 'backup-token-revoked');
    }
}
