<?php

namespace Filament\Launchpad\Filament\Resources\Concerns;

use Filament\Launchpad\Support\LaunchpadOverride;
use Filament\Launchpad\Support\LaunchpadTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase H.3 — copy-on-write on edit.
 *
 * When a tenant opens a template record to edit, the edit is redirected onto a
 * private fork of that record (created on first edit, reused after). The
 * template — and therefore every other tenant — is never touched. In the
 * primary context, or for a record the tenant already owns, editing is direct.
 */
trait ForksLaunchpadRecordOnEdit
{
    public function resolveRecord(int|string $key): Model
    {
        $record = parent::resolveRecord($key);

        if (! LaunchpadOverride::enabled($record)) {
            return $record;
        }

        return LaunchpadOverride::resolveForEditing($record, LaunchpadTenant::id());
    }
}
