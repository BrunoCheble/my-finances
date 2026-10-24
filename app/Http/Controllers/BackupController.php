<?php

namespace App\Http\Controllers;

use App\Services\SaveBackupService;
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
}
