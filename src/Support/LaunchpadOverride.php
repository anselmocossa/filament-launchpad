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
     * Find or create the tenant's fork of a template record. A real edit forks
     * DEEP (the tenant gets a working copy of the whole subtree); a pure hide
     * forks SHALLOW (a bare tombstone — no point copying a subtree just to
     * bury it).
     */
    public static function forkFor(Model $record, string $tenantId, bool $deep = true): Model
    {
        $existing = $record->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('origin_id', $record->getKey())
            ->first();

        if ($existing) {
            return $existing;
        }

        $fork = static::newForkFrom($record, $tenantId);
        $fork->save();

        if ($deep) {
            static::deepCopyChildren($record, $fork, $tenantId);
        }

        return $fork;
    }

    /**
     * Build an unsaved fork carrying only REAL table columns.
     *
     * replicate() would copy every loaded attribute, including query-time
     * aggregates like `pages_count` (added by the table's ->counts('pages'))
     * that have no column — inserting those blows up with an "undefined column"
     * error. Copying strictly the schema columns sidesteps that for good.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected static function newForkFrom(Model $record, string $tenantId, array $overrides = []): Model
    {
        $columns = $record->getConnection()
            ->getSchemaBuilder()
            ->getColumnListing($record->getTable());

        $data = array_intersect_key(
            $record->getAttributes(),
            array_flip($columns),
        );

        // Never copy identity/lineage/timestamps — the fork gets its own.
        unset($data['id'], $data['created_at'], $data['updated_at'], $data['origin_id'], $data['is_hidden']);

        $data['tenant_id'] = $tenantId;
        $data['origin_id'] = $record->getKey();

        if (in_array('is_hidden', $columns, true)) {
            $data['is_hidden'] = false;
        }

        // A fork is never the protected default home; that flag belongs to the
        // template row alone.
        if (in_array('is_default', $columns, true)) {
            $data['is_default'] = false;
        }

        return $record->newInstance(array_merge($data, $overrides));
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
        $fork = static::newForkFrom($child, $tenantId, $overrides);
        $fork->save();

        return $fork;
    }

    /**
     * The single entry point every delete action must go through. In a tenant
     * context, deleting an inherited record HIDES it for that tenant alone (a
     * tombstone) — never destroying the shared template, which would remove it
     * for every tenant. A record the tenant owns, or a delete in the primary
     * context, is really deleted.
     */
    public static function deleteOrHide(Model $record): void
    {
        $tenantId = LaunchpadTenant::id();

        if (filled($tenantId)
            && static::enabled($record)
            && (string) ($record->getAttribute('tenant_id') ?? '') !== (string) $tenantId) {
            static::hideFor($record, $tenantId);

            return;
        }

        $record->delete();
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

        // A template row: create (or reuse) a SHALLOW hidden fork over it —
        // just enough to bury it for this tenant, no subtree copy.
        $fork = static::forkFor($record, $tenantId, deep: false);
        $fork->forceFill(['is_hidden' => true])->save();
    }

    protected static function hasColumn(Model $model, string $column): bool
    {
        return $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), $column);
    }
}
