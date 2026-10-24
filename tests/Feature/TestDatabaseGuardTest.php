<?php

namespace Tests\Feature;

use RuntimeException;
use Tests\TestCase;

class TestDatabaseGuardTest extends TestCase
{
    public function test_guard_rejects_db_url_pointing_at_another_database(): void
    {
        $previous = getenv('DB_URL');
        // Host .invalid: nếu guard để lọt thì cũng không thể kết nối tới DB thật.
        $url = 'mysql://root@guard-check.invalid/cinema_db';
        putenv("DB_URL={$url}");
        $_ENV['DB_URL'] = $_SERVER['DB_URL'] = $url;

        try {
            $this->expectException(RuntimeException::class);
            $this->createApplication();
        } finally {
            putenv('DB_URL='.($previous === false ? '' : $previous));
            $_ENV['DB_URL'] = $_SERVER['DB_URL'] = $previous === false ? '' : $previous;
        }
    }
}
