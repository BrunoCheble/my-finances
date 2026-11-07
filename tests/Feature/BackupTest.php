<?php

use App\Models\FinancialCategory;
use App\Models\Loan;
use App\Models\User;
use App\Models\Wallet;
use App\Services\SaveBackupService;

function backupWallet(User $user, string $name): Wallet
{
    $wallet = new Wallet(['name' => $name, 'type' => 'debit', 'active' => true]);
    $wallet->user_id = $user->id;
    $wallet->save();
    return $wallet;
}

it('downloads every table of the user as one json file', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $wallet = backupWallet($user, 'Banco');
    $this->postJson(route('financial-movements.store'), [
        'description' => 'Salário', 'type' => 'income', 'date' => '2026-10-05', 'amount' => 1000, 'wallet_id' => $wallet->id,
    ])->assertCreated();
    $this->postJson(route('financial-movements.store'), [
        'description' => 'Empréstimo', 'type' => 'loan', 'date' => '2026-10-06', 'amount' => 50, 'wallet_id' => $wallet->id,
        'loan_person' => 'Carlos', 'loan_direction' => 'lend',
    ])->assertCreated();

    $response = $this->post(route('backup.save'))->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('escudo-backup-');

    $backup = json_decode($response->streamedContent(), true);
    expect($backup['format'])->toBe(SaveBackupService::FORMAT)
        ->and($backup['user']['email'])->toBe($user->email)
        ->and(array_keys($backup['tables']))->toEqual([...SaveBackupService::TABLES, 'statement_import_lines'])
        ->and($backup['counts']['wallets'])->toBe(1)
        ->and($backup['counts']['financial_movements'])->toBe(2)
        ->and($backup['counts']['loans'])->toBe(1)
        ->and($backup['counts']['financial_balances'])->toBe(1)
        ->and($backup['tables']['loans'][0]['person'])->toBe('Carlos');
});

it('never includes the data of other users', function () {
    $other = User::factory()->create();
    backupWallet($other, 'Segredo');
    $category = new FinancialCategory(['name' => 'Alheia', 'type' => 'expense', 'active' => true]);
    $category->user_id = $other->id;
    $category->save();

    $user = User::factory()->create();
    backupWallet($user, 'Minha');

    $backup = json_decode($this->actingAs($user)->post(route('backup.save'))->streamedContent(), true);

    expect(array_column($backup['tables']['wallets'], 'name'))->toBe(['Minha'])
        ->and($backup['tables']['financial_categories'])->toBe([]);
});

it('needs to be signed in', function () {
    $this->post(route('backup.save'))->assertRedirect(route('login'));
});

it('gives the scheduled backup the data of the token\'s user only', function () {
    $user = User::factory()->create();
    backupWallet($user, 'Minha');
    backupWallet(User::factory()->create(), 'Alheia');

    $this->actingAs($user)->post(route('backup.token'))->assertRedirect(route('profile.edit'));
    $token = session('backup_token');
    expect($token)->toHaveLength(48)->and($user->fresh()->backup_token_hash)->toBe(hash('sha256', $token));
    auth()->logout();

    $backup = $this->postJson(route('backup.auto'), [], ['Authorization' => "Bearer $token"])->assertOk()->json();
    expect($backup['format'])->toBe(SaveBackupService::FORMAT)
        ->and(array_column($backup['tables']['wallets'], 'name'))->toBe(['Minha']);
});

it('refuses the scheduled backup without a valid token', function () {
    $this->postJson(route('backup.auto'))->assertUnauthorized();
    $this->postJson(route('backup.auto'), [], ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
});

it('stops a token when a new one is created or it is turned off', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('backup.token'));
    $first = session('backup_token');
    $this->post(route('backup.token'));
    $second = session('backup_token');

    $this->postJson(route('backup.auto'), [], ['Authorization' => "Bearer $first"])->assertUnauthorized();
    $this->postJson(route('backup.auto'), [], ['Authorization' => "Bearer $second"])->assertOk();

    $this->actingAs($user)->delete(route('backup.revoke'));
    $this->postJson(route('backup.auto'), [], ['Authorization' => "Bearer $second"])->assertUnauthorized();
});

it('shows the new token once on the profile', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('backup.token'));
    $token = session('backup_token');
    $this->get(route('profile.edit'))->assertSee($token);
    $this->get(route('profile.edit'))->assertDontSee($token)->assertSee('Backup automático');
});
