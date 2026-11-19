<?php
namespace PuzzleSoft\GlobalSearch\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PuzzleSoft\GlobalSearch\Commands\GlobalSearchCommand;
use PuzzleSoft\GlobalSearch\Dependencies\SearchDependency;
use PuzzleSoft\GlobalSearch\SearchResult;
use Throwable;

class SearchManager
{
    protected array $commands = [];

    public function register(array|string $commands): void
    {
        foreach ((array) $commands as $command) {
            if (!is_string($command) ||
                !is_subclass_of($command, GlobalSearchCommand::class)) {
                $name = is_string($command) ? $command : get_debug_type($command);

                throw new InvalidArgumentException(
                    "Invalid search command [{$name}]. The class must exist and extend ".GlobalSearchCommand::class.'.'
                );
            }

            $this->commands[$command] = $command;
        }
    }

    public function commands(): Collection
    {
        return collect($this->commands)
            ->map(fn ($class) => app($class))
            ->filter(fn ($command) => $command->authorized())
            ->values();
    }

    public function findCommand(string $id): GlobalSearchCommand
    {
        if (!isset($this->commands[$id])) {
            throw new InvalidArgumentException('Unknown search command.');
        }

        $command = app($id);

        abort_unless($command->authorized(), 403);

        return $command;
    }

    public function searchCommands(string $query): array
    {
        $query = trim($query);

        return $this->commands()
            ->filter(function ($command) use ($query) {
                if ($query === '') {
                    return true;
                }

                $haystack = implode(' ', array_filter([
                    $command::name(),
                    $command::description(),
                    ...$command::keywords(),
                ]));

                return Str::contains(
                    Str::lower($haystack),
                    Str::lower($query)
                );
            })
            ->map(function ($command) {
                $dependencies = $command->dependencies();

                return [
                    'id' => get_class($command),
                    'name' => $command::name(),
                    'description' => $command::description(),
                    'icon' => $command::icon(),
                    'keywords' => $command::keywords(),
                    'has_dependencies' => count($dependencies) > 0,
                ];
            })
            ->values()
            ->all();
    }

    public function getDependency(
        string $commandId,
        string $dependencyName
    ): SearchDependency {
        $command = $this->findCommand($commandId);

        foreach ($command->dependencies() as $dependency) {
            if ($dependency instanceof SearchDependency &&
                $dependency->name === $dependencyName) {
                return $dependency;
            }
        }

        throw new InvalidArgumentException(
            'Unknown search dependency.'
        );
    }

    public function dependencyDefinitions(string $commandId): array
    {
        $command = $this->findCommand($commandId);

        return collect($command->dependencies())
            ->map(fn (SearchDependency $dependency) => [
                'name' => $dependency->name,
                'placeholder' => $dependency->placeholder,
            ])
            ->values()
            ->all();
    }

    public function searchAll(string $query): array
    {
        $query = trim($query);
        $min = max(
            0,
            (int) config('global-search.search.min_characters', 2)
        );

        if (mb_strlen($query) < $min) {
            return [];
        }

        $limit = max(
            1,
            (int) config('global-search.search.max_results', 10)
        );

        $items = [];

        foreach ($this->commands() as $command) {
            if (!$command->universal()) {
                continue;
            }

            try {
                $results = collect($command->search($query))->take($limit);

                foreach ($results as $result) {
                    if (!$result instanceof SearchResult) {
                        throw new InvalidArgumentException(
                            'Search commands must return SearchResult objects.'
                        );
                    }

                    $payload = $result->jsonSerialize();
                    $payload['result_id'] = $payload['id'];
                    $payload['id'] = get_class($command).':'.$payload['id'];
                    $payload['command_id'] = get_class($command);
                    $payload['category'] = $command::category();
                    $payload['icon'] = $payload['icon'] ?: $command::icon();
                    $items[] = $payload;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $items;
    }

    public function openResult(string $commandId, string|int $id): ?string
    {
        $command = $this->findCommand($commandId);

        abort_unless($command->universal(), 403);

        return $command->open($id);
    }

    public function searchDependency(
        string $commandId,
        string $dependencyName,
        string $query
    ): array {
        $min = max(
            0,
            (int) config('global-search.search.min_characters', 2)
        );

        if (mb_strlen(trim($query)) < $min) {
            return [];
        }

        $limit = max(
            1,
            (int) config('global-search.search.max_results', 10)
        );

        return $this->getDependency($commandId, $dependencyName)
            ->search(trim($query))
            ->take($limit)
            ->map(fn (SearchResult $result) => $result->jsonSerialize())
            ->values()
            ->all();
    }

    public function execute(
        string $commandId,
        array $values = []
    ): ?string {
        $command = $this->findCommand($commandId);
        $resolved = [];

        foreach ($command->dependencies() as $dependency) {
            if (!array_key_exists($dependency->name, $values)) {
                throw new InvalidArgumentException(
                    "Missing dependency [{$dependency->name}]."
                );
            }

            $id = $values[$dependency->name];

            if (!is_string($id) && !is_int($id)) {
                throw new InvalidArgumentException(
                    'Invalid dependency value.'
                );
            }

            $resolved[$dependency->name] = $dependency->resolve($id);
        }

        // Execute receives resolved values in dependency order.
        $result = $command->execute(...array_values($resolved));

        if ($result === null) {
            return null;
        }

        if (!is_string($result)) {
            throw new InvalidArgumentException(
                'Search commands must return a URL or null.'
            );
        }

        return $result;
    }
}
