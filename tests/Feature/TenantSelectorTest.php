<?php

use Filament\Launchpad\Filament\Concerns\HasLaunchpadTenantSelector;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Models\UserCard;
use Filament\Launchpad\Support\LaunchpadTenant;

beforeEach(function () {
    actingAsLaunchpadAdmin();
    LaunchpadTenant::clearOverride();
    session()->forget(LaunchpadTenant::SESSION_KEY);
});

afterEach(function () {
    LaunchpadTenant::clearOverride();
    session()->forget(LaunchpadTenant::SESSION_KEY);
    LaunchpadPlugin::get()->tenantResolver(null)->tenants(null);
});

function selectorFixture(): Section
{
    $space = Space::query()->create(['label' => 'Início', 'sort' => -100]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Início', 'sort' => -100]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'KPIs', 'sort' => 0]);

    LaunchpadPlugin::get()
        ->tenantResolver(fn () => null /* the parent panel resolves no tenant of its own */)
        ->tenants(fn () => ['demo' => 'Loja Demo', 'tech' => 'Tech Store']);

    return $section;
}

/**
 * The selector lives on a builder page; exercising the trait directly keeps the
 * assertion on the behaviour rather than on Filament's action plumbing.
 */
function selectorHarness(): object
{
    return new class
    {
        use HasLaunchpadTenantSelector;

        public function options(): array
        {
            return $this->tenantSelectorOptions();
        }

        public function shows(): bool
        {
            return $this->showsTenantSelector();
        }

        public function label(): string
        {
            return $this->tenantSelectorLabel();
        }
    };
}

it('annotates each store with how far it drifted from the template', function () {
    $section = selectorFixture();
    $card = Card::query()->create(['title' => 'Vendas POS', 'type' => 'kpi']);

    UserCard::query()->create(['tenant_id' => 'demo', 'section_id' => $section->id, 'card_id' => $card->id, 'sort' => 0]);
    Section::query()->create(['page_id' => $section->page_id, 'tenant_id' => 'demo', 'title' => 'Da Loja', 'sort' => 1]);

    $options = selectorHarness()->options();

    expect($options['demo'])->toContain('Loja Demo')->toContain('2')
        ->and($options['tech'])->toContain('Tech Store')
        ->and($options[''])->not->toBeEmpty();
});

it('shows the selector only where no tenant resolves on its own', function () {
    selectorFixture();

    expect(selectorHarness()->shows())->toBeTrue();

    LaunchpadTenant::setOverride('demo');
    expect(selectorHarness()->shows())->toBeTrue(); // an override is not the host resolving

    LaunchpadPlugin::get()->tenantResolver(fn () => 'demo');
    LaunchpadTenant::clearOverride();
    expect(selectorHarness()->shows())->toBeFalse();
});

it('refuses a session override once the host resolves a real tenant', function () {
    selectorFixture();

    session([LaunchpadTenant::SESSION_KEY => 'tech']);
    LaunchpadPlugin::get()->tenantResolver(fn () => 'demo');
    LaunchpadTenant::clearOverride();

    LaunchpadTenant::applySelectorOverride();

    expect(LaunchpadTenant::id())->toBe('demo');
});

it('refuses a selected id the host never declared', function () {
    selectorFixture();

    session([LaunchpadTenant::SESSION_KEY => 'intruder']);
    LaunchpadTenant::clearOverride();

    LaunchpadTenant::applySelectorOverride();

    expect(LaunchpadTenant::id())->toBeNull();
});

it('applies a legitimate selection when nothing else resolves', function () {
    selectorFixture();

    session([LaunchpadTenant::SESSION_KEY => 'tech']);
    LaunchpadTenant::clearOverride();

    LaunchpadTenant::applySelectorOverride();

    expect(LaunchpadTenant::id())->toBe('tech')
        ->and(selectorHarness()->label())->toBe('Tech Store');
});
