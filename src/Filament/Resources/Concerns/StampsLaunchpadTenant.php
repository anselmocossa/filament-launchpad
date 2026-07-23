<?php

namespace Filament\Launchpad\Filament\Resources\Concerns;

use Filament\Launchpad\Support\LaunchpadPermission;
use Filament\Launchpad\Support\LaunchpadTenant;

/**
 * Stamps the layer a newly created Space/Page/Section/Card belongs to.
 *
 * A plain tenant user's record lands in their own tenant — it stays with them
 * and never touches the shared template. Someone who manages the primary layer
 * (the "main") creates INTO the shared template instead, so what they create is
 * distributed to every tenant — even when they happen to be inside a tenant
 * panel. A single-tenant install (no resolver) always leaves the column null.
 */
trait StampsLaunchpadTenant
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->stampLaunchpadTenant(parent::mutateFormDataBeforeCreate($data));
    }

    /**
     * Exposed so a create page that already overrides
     * mutateFormDataBeforeCreate (e.g. CreateSpace, which also stamps panel_id)
     * can add the tenant without giving up its own logic.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stampLaunchpadTenant(array $data): array
    {
        // The main authors the shared template, so their new records stay
        // null-tenant and distribute to everyone.
        if (LaunchpadPermission::managesPrimary()) {
            return $data;
        }

        if (filled($tenantId = LaunchpadTenant::id())) {
            $data['tenant_id'] = $tenantId;
        }

        return $data;
    }
}
