<?php
namespace PuzzleSoft\GlobalSearch\Commands;

use Illuminate\Support\Collection;

abstract class GlobalSearchCommand
{
    abstract public static function name(): string;

    public static function description(): ?string
    {
        return null;
    }

    public static function icon(): ?string
    {
        return null;
    }

    public static function keywords(): array
    {
        return [];
    }

    public static function category(): string
    {
        return static::name();
    }

    public function authorized(): bool
    {
        return true;
    }

    /**
     * Actions that should only appear when typing ">" return false.
     */
    public function universal(): bool
    {
        return true;
    }

    abstract public function search(string $query): Collection;

    public function open(string|int $id): ?string
    {
        return $this->execute($id);
    }

    public function execute(mixed ...$values): ?string
    {
        return null;
    }

    public function dependencies(): array
    {
        return [];
    }
    
}
