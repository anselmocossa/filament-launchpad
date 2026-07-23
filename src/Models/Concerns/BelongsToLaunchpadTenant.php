<?php

namespace Filament\Launchpad\Models\Concerns;

use Filament\Launchpad\Support\LaunchpadOverride;
use Filament\Launchpad\Support\LaunchpadTenant;

/**
 * Phase H tenant scoping for the structural models (Space / Page / Section).
 *
 * Deliberately the same shape as Space::scopeForPanel(): a null `tenant_id`
 * means "belongs to everyone" — the parent's shared template — so pre-Phase H
 * rows become that template with no data migration, and an install that never
 * wires a tenant resolver keeps every row null and every query unchanged.
 */
trait BelongsToLaunchpadTenant
{
    /**
     * Central safety net: whatever triggers a delete — a table row action, a
     * header action, a relation manager, a bulk action — a tenant deleting an
     * inherited (template) record must never destroy the shared row. It is
     * converted into a per-tenant hide and the real delete is aborted, so the
     * template survives for every other tenant. A tenant's OWN record, and any
     * delete in the primary context, deletes for real.
     */
    public static function bootBelongsToLaunchpadTenant(): void
    {
        static::deleting(function ($model): ?bool {
            $tenantId = LaunchpadTenant::id();

            if (blank($tenantId) || ! LaunchpadOverride::enabled($model)) {
                return null;
            }

            if ((string) ($model->getAttribute('tenant_id') ?? '') === (string) $tenantId) {
                return null; // the tenant's own row — delete for real
            }

            // An inherited template row: hide it for this tenant, abort the delete.
            LaunchpadOverride::hideFor($model, $tenantId);

            return false;
        });
    }

    /**
     * A tenant sees its own rows PLUS the shared template. A null $tenantId
     * (single-tenant install, or the parent authoring the template itself) sees
     * ONLY the template — one tenant's private rows must never leak into the
     * shared view, which is the asymmetry that makes this safe in both
     * directions.
     */
    public function scopeForTenant($query, ?string $tenantId)
    {
        if (blank($tenantId)) {
            return $query->whereNull($this->qualifyColumn('tenant_id'));
        }

        return $query->where(function ($query) use ($tenantId): void {
            $query->where($this->qualifyColumn('tenant_id'), $tenantId)
                ->orWhereNull($this->qualifyColumn('tenant_id'));
        });
    }

    public function isLaunchpadTemplate(): bool
    {
        return blank($this->tenant_id);
    }

    /**
     * Whether this row is a tenant's OVERRIDE of a template row (copy-on-write),
     * as opposed to a template row or a tenant's brand-new row.
     */
    public function isLaunchpadOverride(): bool
    {
        return filled($this->tenant_id) && filled($this->origin_id);
    }

    /**
     * Phase H.3 — the EFFECTIVE set a tenant sees under copy-on-write: its own
     * rows (overrides + brand-new, minus the ones it hid) PLUS every template
     * row it has NOT overridden or hidden. This is what makes a tenant a
     * "profile" over the shared defaults — it diverges without touching the
     * template or any other tenant.
     *
     * A null $tenantId (the primary context) resolves to the template alone.
     * Falls back to the plain forTenant() shape on a schema that predates the
     * override columns.
     */
    public function scopeEffectiveForTenant($query, ?string $tenantId)
    {
        if (! $this->launchpadHasOverrideColumns()) {
            return $this->scopeForTenant($query, $tenantId);
        }

        if (blank($tenantId)) {
            return $query->whereNull($this->qualifyColumn('tenant_id'));
        }

        $table = $this->getTable();
        $hasHidden = $this->launchpadHasHiddenColumn();

        return $query->where(function ($query) use ($tenantId, $table, $hasHidden): void {
            // The tenant's own visible rows.
            $query->where(function ($own) use ($tenantId, $hasHidden): void {
                $own->where($this->qualifyColumn('tenant_id'), $tenantId);

                if ($hasHidden) {
                    $own->where($this->qualifyColumn('is_hidden'), false);
                }
            })
                // ...plus template rows this tenant has neither overridden nor hidden
                // (both are rows carrying origin_id under that tenant).
                ->orWhere(function ($tpl) use ($tenantId, $table): void {
                    $tpl->whereNull($this->qualifyColumn('tenant_id'))
                        ->whereNotIn($this->qualifyColumn('id'), function ($sub) use ($tenantId, $table): void {
                            $sub->select('origin_id')
                                ->from($table)
                                ->where('tenant_id', $tenantId)
                                ->whereNotNull('origin_id');
                        });
                });
        });
    }

    protected function launchpadHasOverrideColumns(): bool
    {
        return $this->getConnection()->getSchemaBuilder()->hasColumn($this->getTable(), 'origin_id');
    }

    protected function launchpadHasHiddenColumn(): bool
    {
        return $this->getConnection()->getSchemaBuilder()->hasColumn($this->getTable(), 'is_hidden');
    }
}
