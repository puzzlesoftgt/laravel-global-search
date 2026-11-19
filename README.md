# Laravel Global Search

Global search and command palette for Laravel. Open it with a keyboard shortcut, search records across your application, or type `>` to run commands.

This package is inspired by [Wire Elements Spotlight](https://github.com/wire-elements/spotlight). It is built with Livewire and Alpine.

## Requirements

- PHP 8.2 or higher
- Laravel 12 or 13
- Livewire 3.6 or higher
- Alpine.js (included with Livewire)

## Installation

```bash
composer require puzzlesoft/laravel-global-search
```

You do not need to register the service provider. Composer discovers it from the `extra.laravel.providers` entry in this package. Add the component once, before the closing `</body>` tag:

```blade
@livewire('global-search')
```

Open it with `Ctrl+K` on Windows and Linux, or `Cmd+K` on macOS.

## Publish the configuration

```bash
php artisan vendor:publish --tag=global-search-config
```

This copies the package config to `config/global-search.php`. If you already published it and want the latest keys, run the command with `--force`. That overwrites your local file.

Clear a cached config afterwards:

```bash
php artisan config:clear
```

## Configuration

```php
return [
    'commands' => [
        // \App\Search\OrdersSearch::class,
    ],

    'keyboard' => [
        'shortcut' => 'mod+k',
        'enabled' => true,
        'prevent_default' => true,
    ],

    'search' => [
        'debounce' => 200,
        'min_characters' => 2,
        'max_results' => 10,
    ],

    'ui' => [
        'theme' => 'auto',
        'placeholder' => 'Search...',
        'show_icons' => true,
        'show_descriptions' => true,
    ],
];
```

| Key | Purpose |
| --- | --- |
| `commands` | Command classes to register. |
| `keyboard.shortcut` | Keys that open the palette. `mod` is Ctrl on Windows/Linux and Command on macOS. Examples: `mod+k`, `ctrl+shift+p`, `alt+k`. |
| `keyboard.enabled` | Turn the shortcut off without removing the component. |
| `keyboard.prevent_default` | Stop the browser from handling that shortcut. |
| `search.debounce` | Milliseconds to wait after typing before searching. |
| `search.min_characters` | Characters required before a record search runs. Command filtering with `>` ignores this. |
| `search.max_results` | Maximum records returned per command or dependency. |
| `ui.theme` | `light`, `dark`, or `auto` (follows the operating system). |
| `ui.placeholder` | Placeholder for the direct search. |
| `ui.show_icons` | Show the icon of each result. |
| `ui.show_descriptions` | Show the secondary line of each result. |

## Open it from your own UI

From Alpine or any element in the page:

```html
<button type="button" @click="$dispatch('global-search-open')">
    Search
</button>
```

From a Livewire component:

```php
$this->dispatch('global-search-open');
```

`global-search-close` closes it. The same shortcut toggles it.

## Create a command

```bash
php artisan make:global-search OrdersSearch
```

The class is created in `app/Search`. Register it in `config/global-search.php`:

```php
'commands' => [
    \App\Search\OrdersSearch::class,
],
```

Or from a service provider:

```php
use PuzzleSoft\GlobalSearch\Facades\GlobalSearch;

public function boot(): void
{
    GlobalSearch::register([
        \App\Search\OrdersSearch::class,
    ]);
}
```

Registering the same class in both places does not duplicate it.

## Two ways to search

**Direct search.** The user types a query. Every command with `universal()` returning `true` runs its `search()` method. Results are grouped by `category()`. Pressing Enter opens the result `url`. If the result has no URL, `open()` runs, which calls `execute()` with the result id.

**Commands.** The user types `>`. The palette lists every authorized command and filters it by `name()`, `description()`, and `keywords()`. Choosing one runs `execute()` immediately, or asks for each dependency first.

A command can be both. Set `universal()` to `false` when it should only appear after `>`.

## Search records

`title` and `description` are rendered as HTML. Escape user data with `e()`.

```php
use Illuminate\Support\Collection;
use PuzzleSoft\GlobalSearch\Commands\GlobalSearchCommand;
use PuzzleSoft\GlobalSearch\SearchResult;

class OrdersSearch extends GlobalSearchCommand
{
    public static function name(): string
    {
        return 'Orders';
    }

    public static function description(): ?string
    {
        return 'Find an order by number';
    }

    public static function category(): string
    {
        return 'Orders';
    }

    public static function keywords(): array
    {
        return ['invoice', 'sale'];
    }

    public static function icon(): ?string
    {
        return 'fa fa-search';
    }

    public function authorized(): bool
    {
        return true;
    }

    public function universal(): bool
    {
        return true;
    }

    public function search(string $query): Collection
    {
        $limit = max(1, (int) config('global-search.search.max_results', 10));

        return Order::query()
            ->where('number', 'like', '%'.$query.'%')
            ->limit($limit)
            ->get()
            ->map(fn (Order $order) => SearchResult::make(
                id: $order->id,
                title: e($order->number),
                description: 'Client: '.e($order->client),
                url: route('orders.show', $order),
            ));
    }
}
```

`icon()` accepts an icon class such as `fa fa-search` (the icon font has to be loaded by your app) or a single character. `null` hides it.

`authorized()` hides the command. The package also rejects its execution with a 403 response.

An exception thrown inside `search()` is reported and that command contributes no results. The other commands still appear.

## Run an action

Leave `search()` empty and return `false` from `universal()` when the command is only an action:

```php
public function universal(): bool
{
    return false;
}

public function search(string $query): Collection
{
    return collect();
}

public function execute(mixed ...$values): ?string
{
    return route('orders.create');
}
```

`execute()` returns a URL to redirect to, or `null` to close the palette.

## Dependencies

Dependencies are only used after the user picks a command with `>`. Each one asks for a value, in order, before `execute()` runs.

```php
use PuzzleSoft\GlobalSearch\Dependencies\SearchDependency;

public function dependencies(): array
{
    return [
        SearchDependency::make('order')
            ->setPlaceholder('Which order?')
            ->setSearch(fn (string $query) => $this->search($query))
            ->setResolve(function (string|int $id) {
                $order = Order::query()->find($id);

                abort_unless($order !== null, 404);

                return $order;
            }),
    ];
}

public function execute(mixed ...$values): ?string
{
    /** @var \App\Models\Order $order */
    $order = $values[0];

    return route('orders.show', $order);
}
```

`setSearch()` must return `SearchResult` objects. `setResolve()` receives the chosen id and its return value is what `execute()` receives. Values arrive in the same order as `dependencies()`, not by name.

A dependency search receives only the text the user typed. It does not receive values chosen for earlier dependencies.

The back button removes the last choice. On the first dependency, it returns to the command list.

## Results without a URL

If a direct-search result has no `url`, Enter calls `open()`, which by default calls `execute()` with that result id. Override `open()` when opening a record should do something other than `execute()`.
