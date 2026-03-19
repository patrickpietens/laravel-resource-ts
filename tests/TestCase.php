<?php

namespace ResourceTs\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use ResourceTs\ResourceTsServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            ResourceTsServiceProvider::class,
        ];
    }
}
