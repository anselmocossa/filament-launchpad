<?php

namespace Filament\Launchpad\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Minimal authenticatable used only to exercise Phase E's role-visibility
 * gate (LaunchpadVisibility) in tests: a real spatie/laravel-permission
 * HasRoles user, backed by a bare `users` table created ad hoc in
 * TestCase::defineDatabaseMigrations(). Never shipped to consumers of the
 * plugin — the gate itself never assumes this class, it only ever calls
 * `hasRole()`/`roles()` via method_exists() on whatever user model the
 * host application already uses.
 */
class TestUser extends Authenticatable
{
    use HasRoles;

    protected $table = 'users';

    protected $guarded = [];
}
