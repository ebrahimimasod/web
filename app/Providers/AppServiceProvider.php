<?php

namespace App\Providers;

use App\Services\SettingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('setting', function () {
            return new SettingService();
        });
    }


    public function boot(): void
    {
//        AliasLoader::getInstance()->alias('Setting', \App\Facades\Setting::class);
    }
}
