<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The whole database (every user, every table but the transient ones) as one encrypted file, for the
 * scheduled backup. It is encrypted here with the backup public key (config/backup-public.pem): only the
 * private key, kept by the owner outside the server, opens it - the server holds no secret.
 *
 * File: "ESCUDOBK1" | key length (2 bytes) | AES key encrypted with RSA-OAEP | IV (12) | GCM tag (16) |
 * gzipped JSON encrypted with AES-256-GCM. scripts/decrypt-backup.php opens it.
 */
class SystemBackupService
{
    public const FORMAT = 1;
    public const MAGIC = 'ESCUDOBK1';

    /** Rebuilt by the app itself: caches, sessions and queues. */
    public const SKIP = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

    public static function publicKeyPath(): string
    {
        return config('backup.public_key', base_path('config/backup-public.pem'));
    }

    /** @return array{0: string, 1: string} encrypted file and fingerprint of the data */
    public function execute(): array
    {
        $tables = [];
        foreach (DB::connection()->getSchemaBuilder()->getTableListing() as $table) {
            $table = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
            if (!in_array($table, self::SKIP, true)) {
                $tables[$table] = DB::table($table)->get()->all();
            }
        }
        ksort($tables);

        // same data -> same fingerprint (the creation time stays out of it)
        $fingerprint = hash('sha256', json_encode($tables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $json = json_encode([
            'app' => config('app.name'),
            'format' => self::FORMAT,
            'created_at' => now()->toIso8601String(),
            'counts' => array_map('count', $tables),
            'tables' => $tables,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [self::encrypt(gzencode($json, 9), file_get_contents(self::publicKeyPath())), $fingerprint];
    }

    public static function encrypt(string $data, string $publicKey): string
    {
        $key = random_bytes(32);
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false || !openssl_public_encrypt($key, $encryptedKey, $publicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new RuntimeException('Backup encryption failed.');
        }
        return self::MAGIC . pack('n', strlen($encryptedKey)) . $encryptedKey . $iv . $tag . $cipher;
    }
}
