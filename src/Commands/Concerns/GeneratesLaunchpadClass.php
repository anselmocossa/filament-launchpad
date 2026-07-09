<?php

namespace Filament\Launchpad\Commands\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\select;

/**
 * Shared placement/scaffolding logic for `make:launchpad-kpi` and
 * `make:launchpad-widget`. Both commands generate a class from a stub into
 * either:
 *
 *   - the generic default, app/Launchpad/{Kpis|Widgets} (namespace
 *     App\Launchpad\{Kpis|Widgets}) — zero-config, works for any app; or
 *   - a "module" subpath, when the consuming app has configured
 *     `launchpad.generators.module_path` + `module_namespace` AND either
 *     passed --module=X or picked one from an interactive prompt.
 *
 * The package itself is generic/public — it never assumes a specific host
 * app's module layout, it only reacts to config the host app opted into.
 */
trait GeneratesLaunchpadClass
{
    /**
     * @return array{directory: string, namespace: string, class: string}
     */
    protected function resolveGeneratorLocation(string $rawName, string $subdirectory): array
    {
        $class = Str::studly(
            Str::of(str_replace('/', '\\', trim($rawName, ' \\/')))->afterLast('\\')->toString()
        );

        [$directory, $namespace] = $this->resolveGeneratorBase($subdirectory);

        return [
            'directory' => $directory,
            'namespace' => $namespace,
            'class' => $class,
        ];
    }

    /**
     * @return array{0: string, 1: string} [directory, namespace]
     */
    protected function resolveGeneratorBase(string $subdirectory): array
    {
        $modulePath = config('launchpad.generators.module_path');
        $moduleNamespace = config('launchpad.generators.module_namespace');
        $moduleOption = $this->option('module');

        $genericDirectory = app_path('Launchpad/'.$subdirectory);
        $genericNamespace = rtrim(app()->getNamespace(), '\\').'\\Launchpad\\'.$subdirectory;

        if (blank($modulePath) || blank($moduleNamespace)) {
            if (filled($moduleOption)) {
                $this->components->warn(
                    "--module was given but 'launchpad.generators.module_path' / 'module_namespace' aren't configured; falling back to {$genericDirectory}."
                );
            }

            return [$genericDirectory, $genericNamespace];
        }

        $module = filled($moduleOption)
            ? $moduleOption
            : $this->promptForModule($modulePath, $subdirectory);

        if (blank($module)) {
            return [$genericDirectory, $genericNamespace];
        }

        $module = Str::studly($module);

        return [
            rtrim((string) $modulePath, '/').'/'.$module.'/'.$subdirectory,
            rtrim((string) $moduleNamespace, '\\').'\\'.$module.'\\'.$subdirectory,
        ];
    }

    protected function promptForModule(string $modulePath, string $subdirectory): ?string
    {
        if (! $this->input->isInteractive()) {
            return null;
        }

        $modules = collect(File::isDirectory($modulePath) ? File::directories($modulePath) : [])
            ->map(fn (string $path): string => basename($path))
            ->values()
            ->all();

        if ($modules === []) {
            return null;
        }

        $none = "Nenhum (app/Launchpad/{$subdirectory})";

        $choice = select(
            label: 'Em que módulo queres criar esta classe?',
            options: [$none, ...$modules],
            default: $none,
        );

        return $choice === $none ? null : $choice;
    }

    /**
     * Writes the stub to disk with {{ namespace }}/{{ class }} replaced.
     * Returns false (after printing an error) when the target exists and
     * --force wasn't given, true otherwise.
     */
    protected function writeGeneratedClass(string $stub, string $directory, string $namespace, string $class, bool $force): bool
    {
        $path = rtrim($directory, '/').'/'.$class.'.php';

        if (File::exists($path) && ! $force) {
            $this->components->error("{$class} already exists at [{$path}]. Use --force to overwrite it.");

            return false;
        }

        File::ensureDirectoryExists($directory);

        $stubPath = dirname(__DIR__, 3).'/stubs/'.$stub;

        $contents = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ key }}', '{{ label }}'],
            [$namespace, $class, Str::of($class)->snake()->toString(), Str::of($class)->headline()->toString()],
            File::get($stubPath),
        );

        File::put($path, $contents);

        return true;
    }
}
