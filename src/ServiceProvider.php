<?php

namespace InEngine\Modulus;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;


class ServiceProvider extends BaseServiceProvider
{

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/modulus.php' => config_path('modulus.php'),
        ]);
    }

    public function register(): void
    {
        
    }
}