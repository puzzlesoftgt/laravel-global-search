<?php
namespace PuzzleSoft\GlobalSearch\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;

class MakeGlobalSearchCommand extends GeneratorCommand
{
    protected $signature = 'make:global-search
                            {name : The name of the search command}
                            {--force : Create the class even if it already exists}';

    protected $description = 'Create a new Global Search command';

    public function handle()
    {
        $result = parent::handle();

        if ($result === false) {
            return false;
        }

        $this->components->info('Register the class in the "commands" array of config/global-search.php or with GlobalSearch::register() in AppServiceProvider. The generated file explains each method.');

        return $result;
    }

    protected $type = 'Global Search command';

    protected function getStub(): string
    {
        return __DIR__ . '/../../stubs/global-search-command.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\Search';
    }

    protected function replaceClass($stub, $name)
    {
        $stub = parent::replaceClass($stub, $name);

        $class = class_basename($name);

        return str_replace(
            '{{ name }}',
            Str::headline($class),
            $stub
        );
    }
}
