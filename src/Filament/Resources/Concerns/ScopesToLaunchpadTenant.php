<?php

namespace Filament\Launchpad\Filament\Resources\Concerns;

use Filament\Launchpad\Support\LaunchpadTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase H.2 — makes a management Resource tenant-aware, so a store may run the
 * full Spaces/Pages/Sections/Cards CRUD at /store without ever seeing or
 * touching another store's records, nor the parent's shared template.
 *
 * The rule in one line: a store SEES the template (read-only) plus its own
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
            $query->forTenant(LaunchpadTenant::id());
        }

        return $query;
    }

    /**
     * A record inherited from the parent (null tenant) is read-only for a store:
     * editing it would change the shared template every store depends on.
     * Customising the parent's home is done through the overlay (edit-home),
     * never by mutating the template in place.
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
     * Public so table row actions can hide themselves for inherited records,
     * not just rely on the page-level canEdit/canDelete gate.
     */
    public static function launchpadRecordEditableByCurrentTenant(Model $record): bool
    {
        $tenantId = LaunchpadTenant::id();

        // The parent (no tenant resolved) may edit everything, including the
        // template. A store may edit only records stamped with its own id.
        if (blank($tenantId)) {
            return true;
        }

        return (string) ($record->getAttribute('tenant_id') ?? '') === (string) $tenantId;
    }

    /**
     * A record that belongs to the parent's template while a store is the
     * viewer — shown read-only, badged "inherited".
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
