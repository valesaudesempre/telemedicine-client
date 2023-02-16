<?php

namespace ValeSaude\TelemedicineClient;

use Illuminate\Support\Facades\Cache;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use ValeSaude\TelemedicineClient\Providers\DrConsultaScheduledTelemedicineProvider;

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
        $this->app->bind(DrConsultaScheduledTelemedicineProvider::class, static function () {
            return new DrConsultaScheduledTelemedicineProvider(
                config('services.dr-consulta.base_url', 'https://b2bmarketplaceapihomolog.drconsulta.com'),
                config('services.dr-consulta.client_id'),
                config('services.dr-consulta.secret'),
                config('services.dr-consulta.default_unit_id'),
                Cache::store(config('telemedicine-client.cache-store'))
            );
        });
    }
}