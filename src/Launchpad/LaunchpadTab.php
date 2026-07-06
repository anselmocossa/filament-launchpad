<?php

namespace Filament\Launchpad\Launchpad;

/**
 * @deprecated Use LaunchpadSpace (with LaunchpadPage) instead — LaunchpadTab
 *             is kept only so pre-Fase-A configs built with ->groups() keep
 *             working unmodified. It IS a LaunchpadSpace: ->groups() is sugar
 *             that wraps the given sections in a single default LaunchpadPage
 *             sharing this tab's id/label, so a legacy tab renders and
 *             behaves exactly like a one-page space.
 */
class LaunchpadTab extends LaunchpadSpace
{
    /**
     * @deprecated Use LaunchpadSpace::pages() with LaunchpadPage::sections()
     *             instead.
     *
     * @param  array<TileGroup>  $groups
     */
    public function groups(array $groups): static
    {
        return $this->pages([
            LaunchpadPage::make($this->getLabel(), $this->getId())->sections($groups),
        ]);
    }

    /**
     * @deprecated Use getPages()[0]->getSections() (or iterate getPages())
     *             instead. Kept for code that read groups directly off a tab.
     *
     * @return array<TileGroup>
     */
    public function getGroups(): array
    {
        $page = $this->getPages()[0] ?? null;

        return $page?->getSections() ?? [];
    }
}
