<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->environment() == 'local') {
		    $this->app->register('Laracasts\Generators\GeneratorsServiceProvider');
	    }
        $this->app->register(\Mpociot\ApiDoc\ApiDocGeneratorServiceProvider::class);
        //$this->app->register(\L5Swagger\L5SwaggerServiceProvider::class);
    }
}
