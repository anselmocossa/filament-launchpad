<?php

namespace Filament\Launchpad\Filament\Resources\Concerns;

use Filament\Launchpad\LaunchpadPlugin;
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
        $tenantId = LaunchpadTenant::id();

        // Primary context (no tenant): a new record joins the shared template.
        if (blank($tenantId)) {
            return $data;
        }

        // Only in 'shared' mode does the main author the template from inside a
        // tenant panel. Under 'fork' (and 'readonly') a tenant's new record
        // stays with that tenant — isolation, even for the platform owner.
        try {
            $mode = LaunchpadPlugin::get()->getTenantInheritance();
        } catch (\Throwable) {
            $mode = 'fork';
        }

        if ($mode === 'shared' && LaunchpadPermission::managesPrimary()) {
            return $data;
        }

        $data['tenant_id'] = $tenantId;

        return $data;
    }
}
