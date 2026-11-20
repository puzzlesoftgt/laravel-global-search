<?php
namespace PuzzleSoft\GlobalSearch;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use PuzzleSoft\GlobalSearch\Components\GlobalSearch as GlobalSearchComponent;
use PuzzleSoft\GlobalSearch\Console\MakeGlobalSearchCommand;
use PuzzleSoft\GlobalSearch\Services\SearchManager;

class GlobalSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/global-search.php',
            'global-search'
        );

        $this->app->singleton(
            SearchManager::class,
            fn () => new SearchManager()
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(
            __DIR__ . '/../resources/views',
            'global-search'
        );

        Livewire::component(
            'global-search',
            GlobalSearchComponent::class
        );

        $this->app->booted(function () {
            $this->app->make(SearchManager::class)
                ->register(config('global-search.commands', []));
        });

        view()->share(
            'globalSearchCssPath',
            __DIR__ . '/../resources/css/global-search.css'
        );

        $this->loadTranslationsFrom(
            __DIR__ . '/../lang',
            'global-search'
        );

        $this->publishes([
            __DIR__ . '/../config/global-search.php'
                => config_path('global-search.php'),
        ], 'global-search-config');

        $this->publishes([
            __DIR__ . '/../lang' => $this->app->langPath('vendor/global-search'),
        ], 'global-search-translations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeGlobalSearchCommand::class,
            ]);
        }
    }
}
