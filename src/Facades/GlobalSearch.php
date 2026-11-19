<?php
namespace PuzzleSoft\GlobalSearch\Facades;

use Illuminate\Support\Facades\Facade;
use PuzzleSoft\GlobalSearch\Services\SearchManager;

class GlobalSearch extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SearchManager::class;
    }
}
