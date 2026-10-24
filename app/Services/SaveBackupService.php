<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The user's data as one JSON file to download: every table of the app, the rows as stored
 * (no computed attributes), only the user's. Sessions, passkeys (bound to the device and the
 * domain) and the like stay out.
 */
class SaveBackupService
{
    public const FORMAT = 1;

    /** Tables with a user_id, in an order that can be restored (parents first). */
    public const TABLES = [
        'wallets',
        'financial_categories',
        'loans',
        'financial_movements',
        'financial_balances',
        'movement_changes',
        'statement_accounts',
        'statement_imports',
    ];

    public function execute(User $user): array
    {
        $tables = [];
        foreach (self::TABLES as $table) {
            $tables[$table] = DB::table($table)->where('user_id', $user->id)->orderBy('id')->get()->all();
        }
        // lines of the imports have no user_id: they come through their import
        $importIds = array_map(fn ($import) => $import->id, $tables['statement_imports']);
        $tables['statement_import_lines'] = $importIds
            ? DB::table('statement_import_lines')->whereIn('statement_import_id', $importIds)->orderBy('id')->get()->all()
            : [];

        return [
            'app' => config('app.name'),
            'format' => self::FORMAT,
            'created_at' => now()->toIso8601String(),
            'user' => ['name' => $user->name, 'email' => $user->email],
            'counts' => array_map('count', $tables),
            'tables' => $tables,
        ];
    }

    public static function filename(): string
    {
        return 'escudo-backup-' . now()->format('Y-m-d-His') . '.json';
    }
}
