<?php

namespace App\Http\Controllers;

use App\Services\RestoreBackupService;
use App\Services\SaveBackupService;
use Illuminate\Http\JsonResponse;

class BackupController extends Controller
{
    public function save(SaveBackupService $service): JsonResponse
    {
        $result = $service->execute();
        return response()->json($result);
    }

    public function restore(RestoreBackupService $service): JsonResponse
    {
        $result = $service->execute();
        return response()->json($result);
    }
}
