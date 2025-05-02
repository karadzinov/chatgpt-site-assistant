<?php

namespace MartinK\ChatGptSiteAssistant;

use Illuminate\Support\ServiceProvider;
use MartinK\ChatGptSiteAssistant\Console\ScanSitemapContent;

class ChatGptSiteAssistantServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot()
    {
        // Publish config file
        $this->publishes([
            __DIR__ . '/../config/chatgpt-site-assistant.php' => config_path('chatgpt-site-assistant.php'),
        ], 'config');

        // Publish migration files
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'migrations');

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ScanSitemapContent::class,
            ]);
        }

        // Register routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php'); // or web.php, depending on where your routes are
    }


    /**
     * Register any application services.
     */
    public function register()
    {
        // Merge default config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/chatgpt-site-assistant.php',
            'chatgpt-site-assistant'
        );
    }
}
