<?php

namespace Filament\Launchpad\Support;

use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Illuminate\Database\Eloquent\Builder;

/**
 * Personal bookmarks — Fiori's "Save as Tile": a user pins the page they are on
 * to their own launchpad home (LaunchpadPlugin::bookmarks()).
 *
 * A bookmark is an ordinary shortcut Card pointing at the page's URL, attached
 * (pinned) to a PERSONAL section — `launchpad_sections.user_id` set — on the
 * first page of the panel's default space. Personal sections only render for
 * their owner, so nobody else sees another user's bookmarks. The card's
 * library_key ("bookmark:<user>:<hash of the path>") makes saving idempotent
 * and keeps bookmarks out of the shared card catalog, the Cards resource and
 * the global search (Card::scopeWithoutBookmarks()).
 */
class LaunchpadBookmarks
{
    public const KEY_PREFIX = 'bookmark:';

    public static function key(string $userId, string $path): string
    {
        return self::KEY_PREFIX.$userId.':'.md5($path);
    }

    public static function isSaved(string $userId, string $path): bool
    {
        return Card::query()->where('library_key', self::key($userId, $path))->exists();
    }

    /**
     * Saves $path for $userId in $panelId. Returns false when the panel has no
     * home page to hold the bookmark (nothing seeded yet).
     */
    public static function save(string $userId, string $panelId, string $path, string $title, string $icon = 'heroicon-o-bookmark'): bool
    {
        $section = self::section($userId, $panelId);

        if ($section === null) {
            return false;
        }

        $card = Card::query()->firstOrCreate(
            ['library_key' => self::key($userId, $path)],
            [
                'title' => $title,
                'subtitle' => null,
                'icon' => $icon,
                'type' => 'shortcut',
                'target_type' => 'url',
                'target_value' => $path,
            ],
        );

        $section->cards()->syncWithoutDetaching([
            $card->id => ['sort' => $section->cards()->count(), 'is_pinned' => true],
        ]);

        return true;
    }

    public static function remove(string $userId, string $path): void
    {
        $card = Card::query()->where('library_key', self::key($userId, $path))->first();

        if ($card === null) {
            return;
        }

        $card->sections()->detach();
        $card->delete();
    }

    /**
     * The user's own "My shortcuts" section on the home page of the panel's
     * default space, created on first use.
     */
    public static function section(string $userId, string $panelId): ?Section
    {
        $space = Space::query()
            ->where(fn (Builder $query) => $query->where('panel_id', $panelId)->orWhereNull('panel_id'))
            ->where('is_default', true)
            ->orderByRaw('panel_id is null')
            ->first();

        $page = $space === null ? null : Page::query()
            ->where('space_id', $space->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->first();

        if ($page === null) {
            return null;
        }

        return Section::query()->firstOrCreate(
            ['page_id' => $page->id, 'user_id' => $userId, 'title' => __('launchpad::launchpad.bookmarks.section')],
            ['sort' => 999],
        );
    }
}
