<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        // First launch on a fresh install: create the Areas, accounts and starter plans.
        // NativePHP has already migrated the user's database by this point.
        if (User::doesntExist()) {
            Artisan::call('db:seed', ['--force' => true]);
        }

        Window::open()
            ->title(config('lending.business_name', config('app.name')))
            ->url(url('/admin'))
            ->width(1280)
            ->height(800)
            ->minWidth(1024)
            ->minHeight(640)
            ->rememberState();
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
            'memory_limit' => '512M',
            'max_execution_time' => '120',
        ];
    }
}
