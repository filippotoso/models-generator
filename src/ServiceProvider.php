<?php

namespace FilippoToso\ModelsGenerator;

use Illuminate\Foundation\Support\Providers\EventServiceProvider;

use FilippoToso\ModelsGenerator\GenerateModels;

class ServiceProvider extends EventServiceProvider
{

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {

        parent::boot();

        $this->loadViewsFrom(dirname(__DIR__) . '/resources/views', 'models-generator');

        $this->publishes([
            dirname(__DIR__) . '/config/models-generator.php' => config_path('models-generator.php'),
        ], 'config');

        $this->publishes([
            dirname(__DIR__) . '/resources/views' => resource_path('views/vendor/models-generator'),
        ], 'views');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateModels::class
            ]);
        }

    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {

        $this->mergeConfigFrom(
            dirname(__DIR__) . '/config/default.php', 'models-generator'
        );

    }

}
