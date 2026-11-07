<?php

use App\Models\User;
use App\Models\Wallet;
use App\Services\SystemBackupService;

beforeEach(function () {
    // a key pair for the test (the real public key is config/backup-public.pem)
    $this->privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $path = storage_path('framework/testing-backup-public.pem');
    file_put_contents($path, openssl_pkey_get_details($this->privateKey)['key']);
    config(['backup.public_key' => $path]);
});

afterEach(fn () => @unlink(storage_path('framework/testing-backup-public.pem')));

function openBackup(string $file, $privateKey): array
{
    $keyFile = storage_path('framework/testing-backup-private.pem');
    openssl_pkey_export($privateKey, $pem);
    file_put_contents($keyFile, $pem);
    $backupFile = storage_path('framework/testing-backup.bin');
    file_put_contents($backupFile, $file);

    exec('php ' . escapeshellarg(base_path('scripts/decrypt-backup.php')) . ' ' . escapeshellarg($backupFile) . ' ' . escapeshellarg($keyFile), $out, $code);
    @unlink($keyFile);
    @unlink($backupFile);
    expect($code)->toBe(0);
    return json_decode(implode("\n", $out), true);
}

function walletOf(User $user, string $name): void
{
    $wallet = new Wallet(['name' => $name, 'type' => 'debit', 'active' => true]);
    $wallet->user_id = $user->id;
    $wallet->save();
}

it('backs up every user and table, encrypted, without signing in', function () {
    walletOf(User::factory()->create(), 'Banco A');
    walletOf(User::factory()->create(), 'Banco B');

    $response = $this->post(route('system-backup'))->assertOk();
    $file = $response->getContent();
    expect(substr($file, 0, 9))->toBe('ESCUDOBK1')
        ->and($file)->not->toContain('Banco A')
        ->and($response->headers->get('X-Backup-Fingerprint'))->toHaveLength(64);

    $backup = openBackup($file, $this->privateKey);
    expect($backup['format'])->toBe(SystemBackupService::FORMAT)
        ->and($backup['counts']['users'])->toBe(2)
        ->and(array_column($backup['tables']['wallets'], 'name'))->toBe(['Banco A', 'Banco B'])
        ->and($backup['tables'])->toHaveKeys(['financial_movements', 'payment_reminders', 'migrations'])
        ->and($backup['tables'])->not->toHaveKeys(['sessions', 'cache', 'jobs']);
});

it('keeps the fingerprint while the data is the same', function () {
    walletOf(User::factory()->create(), 'Banco');
    $first = $this->post(route('system-backup'))->headers->get('X-Backup-Fingerprint');
    $this->travel(5)->minutes();
    $again = $this->post(route('system-backup'))->headers->get('X-Backup-Fingerprint');
    walletOf(User::first(), 'Outro');
    $changed = $this->post(route('system-backup'))->headers->get('X-Backup-Fingerprint');

    expect($again)->toBe($first)->and($changed)->not->toBe($first);
});

it('does not open with another key', function () {
    $file = $this->post(route('system-backup'))->getContent();
    $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($other, $pem);
    $keyFile = storage_path('framework/testing-other.pem');
    file_put_contents($keyFile, $pem);
    file_put_contents($backupFile = storage_path('framework/testing-backup.bin'), $file);

    exec('php ' . escapeshellarg(base_path('scripts/decrypt-backup.php')) . " $backupFile $keyFile 2>&1", $out, $code);
    @unlink($keyFile);
    @unlink($backupFile);
    expect($code)->toBe(1)->and(implode('', $out))->toContain('Wrong private key');
});
