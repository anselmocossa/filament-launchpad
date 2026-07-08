<?php

namespace Filament\Launchpad\Support;

use Filament\Facades\Filament;
use Illuminate\Support\Arr;

class LaunchpadUrl
{
    /**
     * Returns the current panel root without relying on the Launchpad page
     * route name. A Launchpad page registered with slug "/" may resolve to
     * "filament.{panel}.pages..", which Laravel cannot generate.
     *
     * @param  array<string, mixed>  $query
     */
    public static function panelHome(array $query = []): string
    {
        $base = self::currentRequestPanelUrl() ?? self::filamentPanelUrl() ?? url('/');

        $query = array_filter($query, fn (mixed $value): bool => filled($value));

        if ($query === []) {
            return $base;
        }

        return $base.(str_contains($base, '?') ? '&' : '?').Arr::query($query);
    }

    protected static function currentRequestPanelUrl(): ?string
    {
        try {
            $panel = Filament::getCurrentPanel();
        } catch (\Throwable) {
            return null;
        }

        if ($panel === null || ! app()->bound('request')) {
            return null;
        }

        $request = request();

        if (blank($request->getHost())) {
            return null;
        }

        $path = trim($panel->getPath(), '/');

        return rtrim($request->getSchemeAndHttpHost(), '/').($path === '' ? '' : '/'.$path);
    }

    protected static function filamentPanelUrl(): ?string
    {
        try {
            return Filament::getCurrentPanel()?->getUrl();
        } catch (\Throwable) {
            return null;
        }
    }
}
