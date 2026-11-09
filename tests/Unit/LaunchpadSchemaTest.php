<?php

use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Support\LaunchpadSchema;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada verificacao da estrutura das tabelas era uma consulta ao catalogo da
 * base de dados. No SQL Server do GASNAC a pagina inicial fazia cinco por
 * pedido. A resposta so' muda com migrations, por isso fica em cache.
 */
function consultasAoCatalogo(Closure $accao): int
{
    $n = 0;
    DB::listen(function ($query) use (&$n) {
        if (! str_contains(strtolower($query->sql), 'from "launchpad_') && ! str_contains($query->sql, 'cache')) {
            $n++;
        }
    });

    $accao();

    return $n;
}

it('responde sobre tabelas e colunas como o Schema', function () {
    expect(LaunchpadSchema::hasTable('launchpad_spaces'))->toBeTrue()
        ->and(LaunchpadSchema::hasTable('nao_existe'))->toBeFalse()
        ->and(LaunchpadSchema::hasColumn('launchpad_spaces', 'label'))->toBeTrue()
        ->and(LaunchpadSchema::hasColumn('launchpad_spaces', 'LABEL'))->toBeTrue()
        ->and(LaunchpadSchema::hasColumn('launchpad_spaces', 'nao_existe'))->toBeFalse()
        ->and(LaunchpadSchema::hasColumn('nao_existe', 'id'))->toBeFalse()
        ->and(LaunchpadSchema::columns('launchpad_spaces'))->toEqualCanonicalizing(
            array_map('strtolower', Schema::getColumnListing('launchpad_spaces'))
        );
});

it('le a estrutura uma vez e depois nao volta ao catalogo', function () {
    LaunchpadSchema::flush();

    $primeira = consultasAoCatalogo(fn () => LaunchpadSchema::hasColumn('launchpad_spaces', 'tenant_id'));
    $seguintes = consultasAoCatalogo(function () {
        LaunchpadSchema::hasColumn('launchpad_spaces', 'tenant_id');
        LaunchpadSchema::hasColumn('launchpad_spaces', 'panel_id');
        LaunchpadSchema::hasTable('launchpad_spaces');
        LaunchpadPlugin::make()->getSpaces();
    });

    expect($primeira)->toBeGreaterThan(0)
        ->and($seguintes)->toBe(0);
});

it('guarda a estrutura na cache da aplicacao, para os pedidos seguintes', function () {
    LaunchpadSchema::flush();
    LaunchpadSchema::hasTable('launchpad_spaces');

    expect(Cache::get(LaunchpadSchema::CACHE_KEY))->toHaveKey(DB::getDefaultConnection().'|launchpad_spaces');
});

it('esquece a estrutura quando as migrations terminam', function () {
    LaunchpadSchema::hasTable('launchpad_spaces');

    event(new MigrationsEnded('up'));

    expect(Cache::get(LaunchpadSchema::CACHE_KEY))->toBeNull();
});

it('nao guarda tabelas que nao existem: criadas depois, sao vistas logo', function () {
    expect(LaunchpadSchema::hasTable('launchpad_extra'))->toBeFalse();

    Schema::create('launchpad_extra', fn ($table) => $table->id());

    expect(LaunchpadSchema::hasTable('launchpad_extra'))->toBeTrue();

    Schema::drop('launchpad_extra');
    LaunchpadSchema::flush();
});
