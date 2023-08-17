<?php

namespace ValeSaude\TelemedicineClient;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use ValeSaude\TelemedicineClient\Config\DrConsultaConfigRepository;
use ValeSaude\TelemedicineClient\Config\SharedConfigRepository;
use ValeSaude\TelemedicineClient\Contracts\DrConsultaConfigRepositoryInterface;
use ValeSaude\TelemedicineClient\Contracts\SharedConfigRepositoryInterface;

/**
 * @codeCoverageIgnore
 */
class TelemedicineClientServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('telemedicine-client')
            ->hasConfigFile('telemedicine-client');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SharedConfigRepositoryInterface::class, static function (Application $app) {
            return new SharedConfigRepository($app->get(ConfigRepository::class), $app->get(CacheFactory::class));
        });

        $this->app->singleton(DrConsultaConfigRepositoryInterface::class, static function (Application $app) {
            return new DrConsultaConfigRepository($app->get(ConfigRepository::class));
        });
    }
}