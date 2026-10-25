<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    /**
     * Laravel's RefreshDatabase runs `migrate:fresh` for non-memory databases.
     * That is destructive, so on MySQL/PostgreSQL we apply only pending
     * migrations (additive) and then wrap each test in a transaction.
     * The SQLite in-memory path keeps the framework behaviour.
     */
    protected function refreshTestDatabase()
    {
        if ($this->usingInMemoryDatabases()) {
            parent::refreshTestDatabase();

            return;
        }

        if (! RefreshDatabaseState::$migrated) {
            Artisan::call('migrate', ['--force' => true]);

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }
}
