<?php

namespace Filament\Launchpad\Filament\Resources\Concerns;

use Filament\Launchpad\Support\LaunchpadTenant;

/**
 * Phase H.2 — stamps the resolved tenant on records a store creates through the
 * management resources, so a new Space/Page/Section/Card lands in that store's
 * layer and never in the shared template.
 *
 * A null tenant (the parent authoring the template, or a single-tenant install)
 * leaves the column untouched, which keeps the record part of the template —
 * exactly the pre-Phase H behaviour.
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
        if (filled($tenantId = LaunchpadTenant::id())) {
            $data['tenant_id'] = $tenantId;
        }

        return $data;
    }
}
