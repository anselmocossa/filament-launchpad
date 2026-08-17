<?php

namespace Filament\Launchpad\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * Role-based visibility gate for launchpad items (Space/Page/Section/Card),
 * SOFT-integrated with spatie/laravel-permission: if the package is absent,
 * or the item was never restricted to any role, everyone sees it — exactly
 * today's behaviour. Only once BOTH the package is present AND the item has
 * at least one role recorded in `launchpad_role_visibility` does the gate
 * start narrowing visibility down to users holding one of those roles (or
 * the Shield super_admin role, who always sees everything).
 */
class LaunchpadVisibility
{
    public static function canView(mixed $model, mixed $user = null): bool
    {
        if (! static::spatieAvailable()) {
            return true;
        }

        if (! is_object($model) || ! method_exists($model, 'isRestricted')) {
            return true;
        }

        try {
            if (! $model->isRestricted()) {
                return true;
            }
        } catch (Throwable) {
            // Never let a misconfigured relation take the launchpad down —
            // degrade to "visible", the same as if it were unrestricted.
            return true;
        }

        $user ??= static::resolveAuthUser();

        if (! $user instanceof Authenticatable) {
            // Guests only ever see non-restricted items (already returned
            // above); a restricted item is hidden from a guest.
            return false;
        }

        if (static::isSuperAdmin($user)) {
            return true;
        }

        if (! method_exists($model, 'visibleToRoleIds')) {
            return true;
        }

        try {
            $allowedRoleIds = $model->visibleToRoleIds();
        } catch (Throwable) {
            return true;
        }

        if (blank($allowedRoleIds)) {
            return true;
        }

        $userRoleIds = static::userRoleIds($user);

        return count(array_intersect($allowedRoleIds, $userRoleIds)) > 0;
    }

    public static function spatieAvailable(): bool
    {
        return class_exists(static::roleModelClass());
    }

    /**
     * @return class-string
     */
    public static function roleModelClass(): string
    {
        return config('permission.models.role', Role::class);
    }

    protected static function resolveAuthUser(): mixed
    {
        try {
            return auth()->user();
        } catch (Throwable) {
            return null;
        }
    }

    protected static function isSuperAdmin(mixed $user): bool
    {
        if (! method_exists($user, 'hasRole')) {
            return false;
        }

        $superAdminRole = config('filament-shield.super_admin.name', 'super_admin');

        try {
            return (bool) $user->hasRole($superAdminRole);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, int|string>
     */
    protected static function userRoleIds(mixed $user): array
    {
        if (! method_exists($user, 'roles')) {
            return [];
        }

        // Os papeis de quem esta' a ver nao mudam a meio de um pedido, e isto
        // era chamado uma vez por CARD.
        //
        // A chave e' a INSTANCIA, nao o id: num pedido o auth()->user() devolve
        // sempre o mesmo objecto, portanto guarda-se na mesma; entre testes (ou
        // entre pedidos) o objecto e' outro, e nao ha' resposta de um a servir
        // ao seguinte. Com o id, o utilizador 1 de um teste respondia pelo
        // utilizador 1 do teste a seguir.
        $chave = (string) spl_object_id($user);

        if (array_key_exists($chave, static::$papeisPorUtilizador)) {
            return static::$papeisPorUtilizador[$chave];
        }

        try {
            $relation = $user->roles();

            return static::$papeisPorUtilizador[$chave] = $relation
                ->pluck($relation->getRelated()->getQualifiedKeyName())
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Papeis ja' lidos neste pedido, por utilizador.
     *
     * @var array<string, array<int, int|string>>
     */
    protected static array $papeisPorUtilizador = [];

    /** Esquece os papeis em memoria — usado pelos testes e por quem muda papeis. */
    public static function esquecerPapeis(): void
    {
        static::$papeisPorUtilizador = [];
    }
}
