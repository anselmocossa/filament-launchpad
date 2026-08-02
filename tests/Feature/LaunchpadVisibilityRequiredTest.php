<?php

use Filament\Launchpad\Filament\Resources\SectionResource\Pages\EditSection;
use Filament\Launchpad\Filament\Resources\SpaceResource\Pages\CreateSpace;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    actingAsLaunchpadAdmin();
});

afterEach(function () {
    LaunchpadPlugin::get()->visibilityRolesRequired(false);
});

// ---------------------------------------------------------------------
// Off by default. A host that never asks for it keeps the old behaviour:
// an empty visibility field means "everyone can see".
// ---------------------------------------------------------------------

it('leaves the visibility field optional unless the host asks otherwise', function () {
    expect(LaunchpadPlugin::get()->getVisibilityRolesRequired())->toBeFalse();

    Livewire::test(CreateSpace::class)
        ->fillForm(['label' => 'Espaço Aberto', 'sort' => 0])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Space::query()->where('label', 'Espaço Aberto')->firstOrFail()->isRestricted())
        ->toBeFalse();
});

it('refuses to save an item with no roles once the host requires them', function () {
    LaunchpadPlugin::get()->visibilityRolesRequired();

    Livewire::test(CreateSpace::class)
        ->fillForm(['label' => 'Espaço Restrito', 'sort' => 0])
        ->call('create')
        ->assertHasFormErrors(['visibilityRoles']);

    expect(Space::query()->where('label', 'Espaço Restrito')->exists())->toBeFalse();
});

it('saves normally once a role is chosen', function () {
    LaunchpadPlugin::get()->visibilityRolesRequired();

    $vendedor = Role::create(['name' => 'Vendedor', 'guard_name' => 'web']);

    Livewire::test(CreateSpace::class)
        ->fillForm([
            'label' => 'Espaço Restrito',
            'sort' => 0,
            'visibilityRoles' => [$vendedor->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Space::query()->where('label', 'Espaço Restrito')->firstOrFail()->isRestricted())
        ->toBeTrue();
});

/**
 * The field is shared by every launchpad item, so the switch has to reach all
 * of them — a Space that demands roles while a Section below it does not would
 * leave the same hole one level down.
 */
it('applies to the other items that share the field', function () {
    LaunchpadPlugin::get()->visibilityRolesRequired();

    $space = Space::query()->create(['label' => 'Espaço', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Página', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Secção', 'sort' => 0]);

    Livewire::test(EditSection::class, ['record' => $section->getRouteKey()])
        ->call('save')
        ->assertHasFormErrors(['visibilityRoles']);
});

/**
 * A closure decides per form. The host may want it mandatory only inside a
 * tenant panel, where every item belongs to somebody, and optional in the
 * primary panel that authors the shared template.
 */
it('accepts a closure so the host can decide case by case', function () {
    $exigir = false;

    // Por referência de propósito: a opção guarda o closure e o Filament
    // avalia-o a cada montagem, portanto o valor tem de ser lido nessa altura
    // e não fixado quando o closure foi escrito.
    LaunchpadPlugin::get()->visibilityRolesRequired(function () use (&$exigir): bool {
        return $exigir;
    });

    Livewire::test(CreateSpace::class)
        ->fillForm(['label' => 'Espaço Um', 'sort' => 0])
        ->call('create')
        ->assertHasNoFormErrors();

    $exigir = true;

    Livewire::test(CreateSpace::class)
        ->fillForm(['label' => 'Espaço Dois', 'sort' => 0])
        ->call('create')
        ->assertHasFormErrors(['visibilityRoles']);
});

/**
 * The placeholder and the hint state the rule for an empty field. Once empty
 * stops being accepted they would be telling the person the opposite of what
 * the form does.
 */
it('stops promising that an empty field lets everyone see', function () {
    Livewire::test(CreateSpace::class)
        ->assertSee('Todos podem ver');

    LaunchpadPlugin::get()->visibilityRolesRequired();

    Livewire::test(CreateSpace::class)
        ->assertDontSee('Todos podem ver');
});
