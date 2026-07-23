<?php

namespace Filament\Launchpad\Support;

/**
 * The three overlay layers a launchpad is composed of, and the storage key that
 * keeps their rows unique in `launchpad_user_cards`.
 *
 * Layer precedence, lowest first:
 *   GLOBAL  tenant_id NULL, user_id NULL  — the parent's template
 *   TENANT  tenant_id set,  user_id NULL  — one tenant's layout
 *   USER    user_id set                   — one person's own additions
 *
 * The key is derived rather than stored as a composite UNIQUE because the
 * natural key has nullable parts, and PostgreSQL treats NULLs in a unique index
 * as distinct — which would let the same card be added to the same section
 * twice for the same scope. Deriving a total string sidesteps the whole
 * portability question (PG's NULLS NOT DISTINCT is 15+, MySQL/SQLite differ
 * again).
 */
class LaunchpadScope
{
    public const GLOBAL = 'global';

    public const TENANT = 'tenant';

    public const USER = 'user';

    public static function key(?string $tenantId, ?string $userId): string
    {
        return 't:'.($tenantId ?? '').'|u:'.($userId ?? '');
    }

    /**
     * Storage attributes for one overlay row, so callers never hand-build the
     * key and drift out of sync with the unique index.
     *
     * @return array<string, mixed>
     */
    public static function attributes(?string $tenantId, ?string $userId): array
    {
        return [
            'tenant_id' => blank($tenantId) ? null : $tenantId,
            'user_id' => blank($userId) ? null : $userId,
            'scope_key' => static::key($tenantId, $userId),
        ];
    }

    public static function name(?string $tenantId, ?string $userId): string
    {
        if (filled($userId)) {
            return static::USER;
        }

        return filled($tenantId) ? static::TENANT : static::GLOBAL;
    }
}
