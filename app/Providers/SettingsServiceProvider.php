<?php

namespace App\Providers;

use App\Services\Dashboard\SettingsService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // This runs on EVERY application boot, including artisan commands that do not
        // need a DB at all (key:generate, package:discover during composer install
        // inside the Docker build - there is no DB connection at all at that point).
        // If the DB is not ready/does not exist, stay silent and let the config fall back to .env.
        try {
            if (!Schema::hasTable('settings')) {
                return;
            }

            app(SettingsService::class)->applyToRuntimeConfig();
        } catch (\Throwable) {
            return;
        }
    }
}
