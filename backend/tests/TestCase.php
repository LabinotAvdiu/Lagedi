<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $database = config('database.connections.mysql.database');
        if (! is_string($database) || ! str_contains($database, '_test')) {
            $this->fail(sprintf(
                'Refusing to run: tests are not pointed at a _test database (got "%s"). Run `php artisan config:clear` and remove bootstrap/cache/config.php.',
                (string) $database,
            ));
        }

        $this->withoutMiddleware(ThrottleRequests::class);
        Cache::flush();
    }
}
