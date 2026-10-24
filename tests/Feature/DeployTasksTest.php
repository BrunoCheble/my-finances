<?php

use App\Services\DeployTasksService;
use Illuminate\Support\Facades\Artisan;

// Artisan is faked: the real "optimize" would write the test config into bootstrap/cache.
beforeEach(function () {
    $this->marker = sys_get_temp_dir() . '/deploy-tasks-' . uniqid();
    $this->calls = [];
    Artisan::shouldReceive('call')->andReturnUsing(function ($command) {
        $this->calls[] = $command;
        return 0;
    });
});

afterEach(function () {
    array_map('unlink', glob($this->marker . '*'));
});

it('runs the migrations, the one-time commands and the caches after a deploy, once', function () {
    $tasks = new DeployTasksService($this->marker, 0);
    expect($tasks->isStale())->toBeTrue();

    $tasks->execute();
    expect($this->calls)->toBe(['migrate', 'balances:rebuild', 'optimize'])
        ->and($tasks->status())->toBe('ok')
        ->and($tasks->isStale())->toBeFalse();

    // nothing changed: nothing runs
    $tasks->execute();
    expect($this->calls)->toHaveCount(3);

    // a new deploy: migrations and caches again, the one-time command not
    unlink($this->marker);
    $tasks->execute();
    expect(array_slice($this->calls, 3))->toBe(['migrate', 'optimize']);
});

it('only clears the caches right after the pull and waits for the rest', function () {
    $tasks = new DeployTasksService($this->marker, 3600);

    $tasks->execute();
    $tasks->execute();

    expect($this->calls)->toBe(['optimize:clear'])
        ->and($tasks->status())->toStartWith('pending:');
});

it('records a failed migration and keeps the app without caches', function () {
    Artisan::swap(new class {
        public array $calls = [];
        public function call($command, $parameters = [])
        {
            $this->calls[] = $command;
            return $command === 'migrate' ? 1 : 0;
        }
    });
    $tasks = new DeployTasksService($this->marker, 0);

    $tasks->execute();

    expect(Artisan::getFacadeRoot()->calls)->toBe(['migrate', 'optimize:clear'])
        ->and($tasks->status())->toStartWith('failed:migrate:');
});
