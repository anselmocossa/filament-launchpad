<?php

namespace Filament\Launchpad\Commands\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Shared placement/scaffolding logic for `make:launchpad-kpi` and
 * `make:launchpad-widget`. Both commands generate a class from a stub into:
 *
 *   - the configured default, `config('launchpad.generators.path')`
 *     (namespace `config('launchpad.generators.namespace')`) — defaults to
 *     app/Filament/Launchpad (App\Filament\Launchpad); or
 *   - a model subfolder, when `--model=X` is given (StudlyCase'd), e.g.
 *     `--model=User` places the class at .../Launchpad/User/{Name}.php with
 *     namespace ...\Launchpad\User. No directory scan / interactive prompt —
 *     omitting --model in a non-interactive run simply means "no model".
 *
 * Also ensures the generated class name carries the caller's $suffix (e.g.
 * "Kpi", "Widget") — à la Filament's own ...Exporter/...Resource convention
 * — without duplicating it when the given name already ends with it.
 */
trait GeneratesLaunchpadClass
{
    /**
     * @return array{directory: string, namespace: string, class: string}
     */
    protected function resolveGeneratorLocation(string $rawName, string $suffix): array
    {
        $class = $this->classNameWithSuffix($rawName, $suffix);

        [$directory, $namespace] = $this->resolveGeneratorBase();

        return [
            'directory' => $directory,
            'namespace' => $namespace,
            'class' => $class,
        ];
    }

    /**
     * StudlyCases the given name and appends $suffix unless it's already
     * there, e.g. classNameWithSuffix('TopUser', 'Kpi') === 'TopUserKpi',
     * and classNameWithSuffix('TopUserKpi', 'Kpi') === 'TopUserKpi' (no
     * duplication).
     */
    protected function classNameWithSuffix(string $rawName, string $suffix): string
    {
        $class = Str::studly(
            Str::of(str_replace('/', '\\', trim($rawName, ' \\/')))->afterLast('\\')->toString()
        );

        if (! Str::endsWith($class, $suffix)) {
            $class .= $suffix;
        }

        return $class;
    }

    /**
     * @return array{0: string, 1: string} [directory, namespace]
     */
    protected function resolveGeneratorBase(): array
    {
        $basePath = (string) (config('launchpad.generators.path') ?? app_path('Filament/Launchpad'));
        $baseNamespace = (string) (config('launchpad.generators.namespace') ?? 'App\\Filament\\Launchpad');

        $model = $this->option('model');

        if (blank($model)) {
            return [$basePath, $baseNamespace];
        }

        $model = Str::studly($model);

        return [
            rtrim($basePath, '/').'/'.$model,
            rtrim($baseNamespace, '\\').'\\'.$model,
        ];
    }

    /**
     * Writes the stub to disk with {{ namespace }}/{{ class }}/{{ key }}/
     * {{ label }} replaced. key/label are derived from the class name with
     * $suffix stripped back off first (e.g. TopUserKpi + suffix "Kpi" ->
     * key top_user, label "Top User" — the suffix itself never leaks into
     * either), matching BaseKpiSource::key()/label()'s own derivation.
     * Returns false (after printing an error) when the target exists and
     * --force wasn't given, true otherwise.
     */
    protected function writeGeneratedClass(string $stub, string $directory, string $namespace, string $class, string $suffix, bool $force): bool
    {
        $path = rtrim($directory, '/').'/'.$class.'.php';

        if (File::exists($path) && ! $force) {
            $this->components->error("{$class} already exists at [{$path}]. Use --force to overwrite it.");

            return false;
        }

        File::ensureDirectoryExists($directory);

        $stubPath = dirname(__DIR__, 3).'/stubs/'.$stub;

        $bareName = $this->stripSuffix($class, $suffix);

        $contents = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ key }}', '{{ label }}'],
            [$namespace, $class, Str::of($bareName)->snake()->toString(), Str::of($bareName)->headline()->toString()],
            File::get($stubPath),
        );

        File::put($path, $contents);

        return true;
    }

    /**
     * Removes a trailing $suffix from $class, e.g. stripSuffix('TopUserKpi',
     * 'Kpi') === 'TopUser'. Falls back to the untouched $class if stripping
     * would leave nothing behind (e.g. a class literally named "Kpi").
     */
    protected function stripSuffix(string $class, string $suffix): string
    {
        if ($suffix === '' || ! Str::endsWith($class, $suffix)) {
            return $class;
        }

        $bare = Str::substr($class, 0, -Str::length($suffix));

        return filled($bare) ? $bare : $class;
    }
}
