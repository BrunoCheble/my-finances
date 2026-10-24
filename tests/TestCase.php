<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Safety lock: .env may point to production and RefreshDatabase drops
     * every table. Only allow tests to run on in-memory SQLite.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.$connection.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            fwrite(STDERR, "\nTestes abortados: banco de teste deve ser sqlite :memory: (atual: $connection / $database).\n");
            exit(1);
        }

        return $app;
    }
}
