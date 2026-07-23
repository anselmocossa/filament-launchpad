<?php

namespace Filament\Launchpad\Filament\Resources\Concerns;

use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Support\LaunchpadPermission;
use Filament\Launchpad\Support\LaunchpadTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase H.2 — makes a management Resource tenant-aware, so a tenant may run the
 * full Spaces/Pages/Sections/Cards CRUD at /tenant without ever seeing or
 * touching another tenant's records, nor the parent's shared template.
 *
 * The rule in one line: a tenant SEES the template (read-only) plus its own
 * (editable); it CREATES into its own layer; it EDITS/DELETES only its own.
 * The parent (no tenant resolves) sees and edits the template, exactly as
 * before. A single-tenant install (no resolver) is unchanged — every record is
 * null-tenant and every gate below is a no-op.
 */
trait ScopesToLaunchpadTenant
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (static::tenantColumnExists()) {
            // Copy-on-write resolution: the tenant sees the template it hasn't
            // diverged, plus its own overrides and new records — its "profile".
            $query->effectiveForTenant(LaunchpadTenant::id());
        }

        return $query;
    }

    /**
     * The shared template (a null-tenant record) is authored by whoever manages
     * the PRIMARY layer — the "main" who creates and distributes to every
     * tenant. That user edits it freely, from any panel, and the change reaches
     * every tenant that inherits it. A plain tenant user cannot touch it; they
     * customise by creating their own records, which stay in their own tenant.
     */
    public static function canEdit(Model $record): bool
    {
        return static::launchpadRecordEditableByCurrentTenant($record)
            && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::launchpadRecordEditableByCurrentTenant($record)
            && parent::canDelete($record);
    }

    /**
     * Public so table row actions can hide themselves, not just rely on the
     * page-level canEdit/canDelete gate.
     */
    public static function launchpadRecordEditableByCurrentTenant(Model $record): bool
    {
        // No tenant resolved (the primary panel): everything is editable.
        if (blank(LaunchpadTenant::id())) {
            return true;
        }

        $recordTenant = $record->getAttribute('tenant_id');

        // A tenant-owned record: only its own tenant may edit it.
        if (filled($recordTenant)) {
            return (string) $recordTenant === (string) LaunchpadTenant::id();
        }

        // A shared-template record, seen inside a tenant panel — behaviour is
        // the host's choice (LaunchpadPlugin::tenantInheritance):
        return match (static::launchpadInheritanceMode()) {
            // copy-on-write: editable — the edit forks a private copy.
            'fork' => true,
            // the template changes for everyone: only the main may.
            'shared' => static::canManageLaunchpadPrimary(),
            // read-only: never editable in place.
            default => false,
        };
    }

    protected static function launchpadInheritanceMode(): string
    {
        try {
            return LaunchpadPlugin::get()->getTenantInheritance();
        } catch (\Throwable) {
            return 'fork';
        }
    }

    /**
     * Whether the current user authors the shared template ("the main").
     * Soft-gated like every ability in the plugin: absent spatie/permission,
     * everyone qualifies (the host owns auth); present, the Shield super_admin
     * or a holder of `Manage:LaunchpadPrimary` qualifies.
     */
    public static function canManageLaunchpadPrimary(): bool
    {
        return LaunchpadPermission::managesPrimary();
    }

    /**
     * A shared-template record (null tenant) seen from inside a tenant panel —
     * badged, and read-only unless the viewer manages the primary layer.
     */
    public static function launchpadRecordIsInherited(Model $record): bool
    {
        return filled(LaunchpadTenant::id())
            && blank($record->getAttribute('tenant_id'));
    }

    protected static function tenantColumnExists(): bool
    {
        $model = static::getModel();

        return (new $model)->getConnection()
            ->getSchemaBuilder()
            ->hasColumn((new $model)->getTable(), 'tenant_id');
    }
}
