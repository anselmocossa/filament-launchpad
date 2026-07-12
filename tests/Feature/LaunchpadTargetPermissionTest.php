<?php

use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;

/**
 * Um card cujo Resource/Page de destino o utilizador não pode aceder não deve
 * ser renderizado (antes aparecia e dava 403 ao clicar). Deferimos ao
 * canAccess() do alvo — o mesmo gate que o Filament/Shield usam.
 */
class LpBlockedTarget
{
    public static function canAccess(): bool
    {
        return false;
    }
}

class LpAllowedTarget
{
    public static function canAccess(): bool
    {
        return true;
    }
}

function makeTargetCard(array $attrs): Card
{
    $space = Space::query()->create(['label' => 'S', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'P', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Sec', 'sort' => 0]);

    return $section->cards()->create(array_merge(['title' => 'C', 'type' => 'shortcut'], $attrs));
}

function invokeProtected(object $plugin, string $method, mixed ...$args): mixed
{
    $ref = new ReflectionMethod($plugin, $method);
    $ref->setAccessible(true);

    return $ref->invoke($plugin, ...$args);
}

it('cardTargetAccessible: esconde alvo sem permissão, permite com permissão e não gateia URLs/none/desconhecido', function () {
    $plugin = LaunchpadPlugin::make();

    $blockedRes = makeTargetCard(['target_type' => 'resource', 'target_value' => LpBlockedTarget::class]);
    $allowedRes = makeTargetCard(['target_type' => 'resource', 'target_value' => LpAllowedTarget::class]);
    $blockedPage = makeTargetCard(['target_type' => 'page', 'target_value' => LpBlockedTarget::class]);
    $url = makeTargetCard(['target_type' => 'url', 'target_value' => 'https://example.com']);
    $none = makeTargetCard(['target_type' => 'none']);
    $unknown = makeTargetCard(['target_type' => 'resource', 'target_value' => 'App\\Nope\\DoesNotExist']);

    expect(invokeProtected($plugin, 'cardTargetAccessible', $blockedRes))->toBeFalse()
        ->and(invokeProtected($plugin, 'cardTargetAccessible', $allowedRes))->toBeTrue()
        ->and(invokeProtected($plugin, 'cardTargetAccessible', $blockedPage))->toBeFalse()
        ->and(invokeProtected($plugin, 'cardTargetAccessible', $url))->toBeTrue()
        ->and(invokeProtected($plugin, 'cardTargetAccessible', $none))->toBeTrue()
        ->and(invokeProtected($plugin, 'cardTargetAccessible', $unknown))->toBeTrue();
});

it('mapCardToDto devolve null (esconde o tile) quando o destino não é acessível', function () {
    $plugin = LaunchpadPlugin::make();
    $blocked = makeTargetCard(['target_type' => 'resource', 'target_value' => LpBlockedTarget::class]);

    expect(invokeProtected($plugin, 'mapCardToDto', $blocked))->toBeNull();
});
