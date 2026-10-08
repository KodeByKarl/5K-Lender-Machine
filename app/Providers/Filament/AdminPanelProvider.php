<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(isSimple: false) // everyone can change their own password
            ->spa()
            ->unsavedChangesAlerts()

            // Brand: one accent (teal = money/trust), neutral slate surfaces, status colors kept for status only.
            ->brandName(config('lending.business_name'))
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('favicon.svg'))
            ->font('Inter')
            ->colors([
                'primary' => $this->primary(),
                'gray' => Color::Slate,
                'danger' => Color::Rose,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'info' => Color::Sky,
            ])

            ->sidebarWidth('16rem')
            ->maxContentWidth(Width::SevenExtraLarge)
            ->navigationGroups([
                NavigationGroup::make('Lending'),
                NavigationGroup::make('Savings'),
                NavigationGroup::make('Administration')->collapsed(),
            ])
            ->globalSearchKeyBindings(['ctrl+k', 'command+k'])

            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.hooks.head'))
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => view('filament.hooks.user-label'))
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn () => Blade::render(
                '<p style="text-align:center;font-size:.75rem;margin-top:.5rem" class="text-gray-500 dark:text-gray-400">Authorized personnel only. Every sign-in is recorded.</p>'
            ))

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Teal shifted one step darker. Filament fills buttons with shade 600 only when white text
     * passes WCAG AA on it; Tailwind teal-600 (3.7:1) does not, so buttons fell back to a pale
     * teal with dark text. Teal-700 in the 600 slot gives 5.5:1.
     *
     * @return array<int, string>
     */
    private function primary(): array
    {
        $t = Color::Teal;

        return [
            50 => $t[50], 100 => $t[100], 200 => $t[200], 300 => $t[300], 400 => $t[400],
            500 => $t[600], 600 => $t[700], 700 => $t[800], 800 => $t[900], 900 => $t[950], 950 => $t[950],
        ];
    }
}
