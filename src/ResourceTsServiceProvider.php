<?php

namespace ResourceTs;

use ResourceTs\Commands\GenerateTypescriptCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ResourceTsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('resource-ts')
            ->hasConfigFile()
            ->hasCommand(GenerateTypescriptCommand::class);
    }
}
