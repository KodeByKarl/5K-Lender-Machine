<?php

namespace App\Providers;

use App\Services\LoanCalculator;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LoanCalculator::class, fn () => new LoanCalculator(
            collectOnSundays: config('lending.collect_on_sundays'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->logAuthenticationEvents();
    }

    /** Record logins, logouts, and failed login attempts in the audit log (Agreement §2.3). */
    private function logAuthenticationEvents(): void
    {
        Event::listen(function (Login $event) {
            activity('auth')
                ->causedBy($event->user)
                ->event('login')
                ->withProperties(['ip' => request()->ip()])
                ->tap(fn ($activity) => $activity->area_id = $event->user->area_id)
                ->log('Logged in');
        });

        Event::listen(function (Logout $event) {
            if (! $event->user) {
                return;
            }

            activity('auth')
                ->causedBy($event->user)
                ->event('logout')
                ->tap(fn ($activity) => $activity->area_id = $event->user->area_id)
                ->log('Logged out');
        });

        Event::listen(function (Failed $event) {
            activity('auth')
                ->causedBy($event->user)
                ->event('login_failed')
                ->withProperties(['email' => $event->credentials['email'] ?? null, 'ip' => request()->ip()])
                ->tap(fn ($activity) => $activity->area_id = $event->user?->area_id)
                ->log('Failed login attempt');
        });
    }
}
