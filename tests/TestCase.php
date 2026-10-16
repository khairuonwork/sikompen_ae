<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('kompen-respon-hub.database_connection', 'sqlite');
    }
}
