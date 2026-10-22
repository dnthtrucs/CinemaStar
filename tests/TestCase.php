<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        // Kiểm tra database của connection đã được Laravel resolve (đã gộp DB_URL),
        // không chỉ biến DB_DATABASE. Việc tạo connection chưa mở kết nối tới MySQL.
        $connection = $app['db']->connection();
        $config = config('database.connections.'.$connection->getName());
        if ($connection->getDatabaseName() !== 'cinema_test' || isset($config['read']) || isset($config['write'])) {
            throw new \RuntimeException('Tests must use the cinema_test database (check DB_URL / DB_DATABASE).');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
