<?php

use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    actingAsLaunchpadAdmin();
});

it('runs the full launchpad migration stack in the consuming app', function () {
    foreach ([
        'launchpad_spaces',
        'launchpad_pages',
        'launchpad_sections',
        'launchpad_cards',
        'launchpad_role_visibility',
        'launchpad_section_card',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Expected {$table} to exist.");
    }

    expect(Schema::hasColumns('launchpad_spaces', [
        'id',
        'label',
        'icon',
        'sort',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('launchpad_pages', [
        'id',
        'space_id',
        'label',
        'icon',
        'sort',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('launchpad_sections', [
        'id',
        'page_id',
        'user_id',
        'title',
        'sort',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('launchpad_cards', [
        'id',
        'library_key',
        'widget_key',
        'widget_column_span',
        'title',
        'subtitle',
        'icon',
        'type',
        'kpi_value',
        'kpi_source',
        'unit',
        'trend',
        'trend_color',
        'badge',
        'badge_bg',
        'badge_color',
        'note',
        'target_type',
        'target_value',
        'sort',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('launchpad_role_visibility', [
        'id',
        'visible_type',
        'visible_id',
        'role_id',
    ]))->toBeTrue();

    expect(Schema::hasColumns('launchpad_section_card', [
        'id',
        'section_id',
        'card_id',
        'sort',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

it('loads the package config, views and translations after installation', function () {
    expect(config('launchpad.branding.name'))->toBe('Launchpad')
        ->and(config('launchpad.accent_color'))->toBe('#16a34a')
        ->and(view()->exists('launchpad::pages.launchpad'))->toBeTrue()
        ->and(view()->exists('launchpad::livewire.launchpad-bar'))->toBeTrue()
        ->and(view()->exists('launchpad::hooks.launchpad-bar'))->toBeTrue()
        ->and(view()->exists('launchpad::hooks.back-button'))->toBeTrue()
        ->and(__('launchpad::launchpad.general.title'))->toBe('Launchpad');

    app()->setLocale('pt');

    expect(__('launchpad::launchpad.general.empty'))->toBe('Ainda não há tiles configurados.');
});

it('keeps Portuguese translations in parity with the English source keys', function () {
    $flatten = function (array $items, string $prefix = '') use (&$flatten): array {
        $keys = [];

        foreach ($items as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $keys = [...$keys, ...$flatten($value, $path)];

                continue;
            }

            $keys[] = $path;
        }

        sort($keys);

        return $keys;
    };

    $englishKeys = $flatten(require __DIR__.'/../../resources/lang/en/launchpad.php');
    $portugueseKeys = $flatten(require __DIR__.'/../../resources/lang/pt/launchpad.php');

    expect(array_values(array_diff($englishKeys, $portugueseKeys)))->toBe([])
        ->and(array_values(array_diff($portugueseKeys, $englishKeys)))->toBe([]);
});

it('boots the installed panel with the launchpad hooks in the native Filament shell', function () {
    $response = $this->get('/test');

    $response
        ->assertOk()
        ->assertSeeHtml('data-launchpad-bar')
        ->assertSeeHtml('fi-launchpad-bar-nav')
        ->assertSeeHtml('fi-launchpad-back')
        ->assertSeeHtml('launchpadOverflow()')
        ->assertSeeHtml('launchpad-back');

    $html = $response->getContent();

    expect(strpos($html, 'fi-launchpad-back'))->not->toBeFalse()
        ->and(strpos($html, 'data-launchpad-bar'))->not->toBeFalse()
        ->and(strpos($html, '<main'))->not->toBeFalse()
        ->and(strpos($html, 'data-launchpad-bar'))->toBeLessThan(strpos($html, '<main'));
});

it('renders installed tile links with real hrefs while preserving Livewire handling', function () {
    $response = $this->get('/test');

    $response
        ->assertOk()
        ->assertSeeHtml('href="/vendas"')
        ->assertSeeHtml('href="/produtos"')
        ->assertSeeHtml('wire:click.prevent="open(0, 0)"')
        ->assertSeeHtml('wire:click.prevent="open(1, 0)"');
});
