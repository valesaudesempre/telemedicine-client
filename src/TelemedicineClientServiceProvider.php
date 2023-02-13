<?php

namespace ValeSaude\TelemedicineClient;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use ValeSaude\TelemedicineClient\Providers\DrConsultaProvider;

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
        $this->app->bind(DrConsultaProvider::class, static function () {
            return new DrConsultaProvider(
                config('services.drconsulta.base_url', 'https://b2bmarketplaceapihomolog.drconsulta.com'),
                config('services.drconsulta.client_id'),
                config('services.drconsulta.secret'),
                config('services.drconsulta.default_unit_id')
            );
        });
    }
}