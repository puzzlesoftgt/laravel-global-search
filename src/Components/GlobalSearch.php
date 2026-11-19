<?php
namespace PuzzleSoft\GlobalSearch\Components;

use Livewire\Attributes\Renderless;
use Livewire\Component;
use PuzzleSoft\GlobalSearch\Services\SearchManager;

class GlobalSearch extends Component
{
    #[Renderless]
    public function searchCommands(string $query = ''): array
    {
        return app(SearchManager::class)
            ->searchCommands($query);
    }

    #[Renderless]
    public function searchAll(string $query = ''): array
    {
        return app(SearchManager::class)->searchAll($query);
    }

    #[Renderless]
    public function openResult(string $commandId, string|int $id): ?string
    {
        return app(SearchManager::class)->openResult($commandId, $id);
    }

    #[Renderless]
    public function dependencies(string $commandId): array
    {
        return app(SearchManager::class)
            ->dependencyDefinitions($commandId);
    }

    #[Renderless]
    public function searchDependency(
        string $commandId,
        string $dependencyName,
        string $query
    ): array {
        return app(SearchManager::class)->searchDependency(
            $commandId,
            $dependencyName,
            $query
        );
    }

    #[Renderless]
    public function executeCommand(
        string $commandId,
        array $values = []
    ): ?string {
        return app(SearchManager::class)
            ->execute($commandId, $values);
    }

    public function render()
    {
        $manager = app(SearchManager::class);

        return view('global-search::global-search', [
            'settings' => [
                'keyboard' => config('global-search.keyboard', []),
                'search' => config('global-search.search', []),
                'ui' => config('global-search.ui', []),
            ],
            'commands' => $manager->searchCommands(''),
        ]);
    }
}
