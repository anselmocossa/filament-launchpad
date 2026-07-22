<?php

namespace Filament\Launchpad\Models\Concerns;

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
}
