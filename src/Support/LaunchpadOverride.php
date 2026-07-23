<?php

namespace Filament\Launchpad\Support;

use Filament\Launchpad\LaunchpadPlugin;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase H.3 — copy-on-write between a template record and a tenant's own copy.
 *
 * A tenant editing an inherited record never mutates the template: it edits a
 * private fork instead (created on first edit), exactly like customising a
 * Windows profile leaves every other profile untouched.
 */
class LaunchpadOverride
{
    /**
     * Whether the copy-on-write model is active: a fork resolver AND the
     * override columns AND the 'fork' inheritance mode all in place.
     */
    public static function enabled(Model $record): bool
    {
        try {
            $mode = LaunchpadPlugin::get()->getTenantInheritance();
        } catch (\Throwable) {
            return false;
        }

        return $mode === 'fork'
            && $record->getConnection()->getSchemaBuilder()->hasColumn($record->getTable(), 'origin_id');
    }

    /**
     * The tenant's editable version of $record. A template record edited inside
     * a tenant context resolves to that tenant's fork (created once, reused
     * after). A record the tenant already owns is returned untouched.
     */
    public static function resolveForEditing(Model $record, ?string $tenantId): Model
    {
        // No tenant (the primary context) edits the template in place; a record
        // the tenant already owns is edited directly.
        if (blank($tenantId) || (string) ($record->getAttribute('tenant_id') ?? '') === (string) $tenantId) {
            return $record;
        }

        // Only a template row (no tenant) is ever forked.
        if (filled($record->getAttribute('tenant_id'))) {
            return $record;
        }

        return static::forkFor($record, $tenantId);
    }

    /**
     * Find or create the tenant's fork of a template record.
     */
    public static function forkFor(Model $record, string $tenantId): Model
    {
        $existing = $record->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('origin_id', $record->getKey())
            ->first();

        if ($existing) {
            return $existing;
        }

        $fork = $record->replicate(['origin_id', 'is_hidden']);
        $fork->tenant_id = $tenantId;
        $fork->origin_id = $record->getKey();

        if (static::hasColumn($fork, 'is_hidden')) {
            $fork->is_hidden = false;
        }

        // A fork is never the protected default home; that flag belongs to the
        // template row alone.
        if (static::hasColumn($fork, 'is_default')) {
            $fork->is_default = false;
        }

        $fork->save();

        static::deepCopyChildren($record, $fork, $tenantId);

        return $fork;
    }

    /**
     * A fork must be a WORKING copy, so the tenant gets the whole subtree, not
     * an empty shell: a forked Space carries its Pages, a forked Page its
     * Sections, a forked Section its card links. Each child is itself stamped
     * with the tenant and points back at its origin, so the tenant owns an
     * independent, fully editable copy — the "profile" over the defaults.
     */
    protected static function deepCopyChildren(Model $origin, Model $fork, string $tenantId): void
    {
        // Space -> Pages
        if (method_exists($origin, 'pages')) {
            foreach ($origin->pages()->whereNull('tenant_id')->get() as $page) {
                $pageFork = static::forkChild($page, $tenantId, ['space_id' => $fork->getKey()]);
                static::deepCopyChildren($page, $pageFork, $tenantId);
            }

            return;
        }

        // Page -> Sections
        if (method_exists($origin, 'sections')) {
            foreach ($origin->sections()->whereNull('tenant_id')->whereNull('user_id')->get() as $section) {
                $sectionFork = static::forkChild($section, $tenantId, ['page_id' => $fork->getKey()]);
                static::deepCopyChildren($section, $sectionFork, $tenantId);
            }

            return;
        }

        // Section -> Card links (the cards themselves stay shared catalogue
        // rows; only the placement is copied onto the tenant's section).
        if (method_exists($origin, 'cards')) {
            foreach ($origin->cards()->get() as $card) {
                $fork->cards()->attach($card->getKey(), [
                    'sort' => $card->pivot->sort ?? 0,
                    'is_pinned' => $card->pivot->is_pinned ?? true,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected static function forkChild(Model $child, string $tenantId, array $overrides): Model
    {
        $fork = $child->replicate(['origin_id', 'is_hidden']);
        $fork->tenant_id = $tenantId;
        $fork->origin_id = $child->getKey();

        if (static::hasColumn($fork, 'is_hidden')) {
            $fork->is_hidden = false;
        }

        foreach ($overrides as $key => $value) {
            $fork->{$key} = $value;
        }

        $fork->save();

        return $fork;
    }

    /**
     * Hide an inherited record for one tenant (a tombstone), without touching
     * the template. If the tenant already forked it, the fork is hidden instead.
     */
    public static function hideFor(Model $record, string $tenantId): void
    {
        if (! static::hasColumn($record, 'is_hidden')) {
            return;
        }

        // The tenant's own row is simply flagged hidden.
        if ((string) ($record->getAttribute('tenant_id') ?? '') === (string) $tenantId) {
            $record->forceFill(['is_hidden' => true])->save();

            return;
        }

        // A template row: create (or reuse) a hidden fork over it.
        $fork = static::forkFor($record, $tenantId);
        $fork->forceFill(['is_hidden' => true])->save();
    }

    protected static function hasColumn(Model $model, string $column): bool
    {
        return $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), $column);
    }
}
