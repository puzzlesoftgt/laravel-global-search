<?php
namespace PuzzleSoft\GlobalSearch\Dependencies;

use Closure;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use PuzzleSoft\GlobalSearch\SearchResult;

class SearchDependency
{
    protected ?Closure $searchCallback = null;
    protected ?Closure $resolveCallback = null;

    public function __construct(
        public string $name,
        public string $placeholder = 'Buscar...',
    ) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function setPlaceholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function setSearch(Closure $callback): static
    {
        $this->searchCallback = $callback;

        return $this;
    }

    public function setResolve(Closure $callback): static
    {
        $this->resolveCallback = $callback;

        return $this;
    }

    public function search(string $query): Collection
    {
        $results = $this->searchCallback
            ? ($this->searchCallback)($query)
            : collect();

        return collect($results)
            ->each(function ($result) {
                if (!$result instanceof SearchResult) {
                    throw new InvalidArgumentException(
                        'Search dependencies must return SearchResult objects.'
                    );
                }
            });
    }

    public function resolve(string|int $id): mixed
    {
        if (!$this->resolveCallback) {
            throw new InvalidArgumentException(
                "Dependency [{$this->name}] has no resolver."
            );
        }

        return ($this->resolveCallback)($id);
    }
}
