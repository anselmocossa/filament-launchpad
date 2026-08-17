<?php

use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Tests\Support\TestUser;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * O launchpad construia a arvore inteira a cada chamada do getSpaces(), e cada
 * space, pagina, seccao e card perguntava por si a' base de dados se estava
 * restrito a algum papel. No portal da ENH davam SETE construcoes e 636
 * consultas numa unica pagina — 532 delas exactamente iguais.
 *
 * Estes testes fixam as duas garantias: constroi-se uma vez, e a visibilidade
 * vem toda de uma vez.
 */
function contarConsultas(Closure $accao): int
{
    $n = 0;
    DB::listen(function () use (&$n) {
        $n++;
    });

    $accao();

    return $n;
}

function arvoreComCards(int $quantosCards): void
{
    $space = Space::create(['label' => 'Avaliação', 'sort' => 1]);
    $page = $space->pages()->create(['label' => 'A minha avaliação', 'sort' => 1]);
    $section = $page->sections()->create(['title' => 'Atalhos', 'sort' => 1]);

    for ($i = 0; $i < $quantosCards; $i++) {
        $section->cards()->create([
            'title' => 'Atalho '.$i,
            'type' => 'shortcut',
            'target_type' => 'none',
        ]);
    }
}

it('constroi os spaces uma vez, por muitas vezes que lhe perguntem', function () {
    arvoreComCards(6);

    $plugin = LaunchpadPlugin::make();

    $primeira = contarConsultas(fn () => $plugin->getSpaces());
    $seguintes = contarConsultas(function () use ($plugin) {
        $plugin->getSpaces();
        $plugin->getSpaces();
        $plugin->getSpaces();
    });

    expect($primeira)->toBeGreaterThan(0)
        // Tres perguntas a seguir a' primeira: zero consultas. Antes disto,
        // cada uma reconstruia tudo de novo.
        ->and($seguintes)->toBe(0);
});

it('nao pergunta a cada card se esta restrito — a visibilidade vem toda de uma vez', function () {
    Role::create(['name' => 'Vendedor', 'guard_name' => 'web']);
    arvoreComCards(12);

    auth()->login(TestUser::create(['name' => 'Quem Ve']));

    $comDoze = contarConsultas(fn () => LaunchpadPlugin::make()->getSpaces());

    // Com o dobro dos cards, o numero de consultas nao pode acompanhar.
    Space::query()->delete();
    arvoreComCards(24);

    $comVinteQuatro = contarConsultas(fn () => LaunchpadPlugin::make()->getSpaces());

    expect($comVinteQuatro)->toBe($comDoze);

    auth()->logout();
});

it('volta a construir quando um space e alterado, mesmo no mesmo pedido', function () {
    arvoreComCards(3);

    $plugin = LaunchpadPlugin::make();

    expect($plugin->getSpaces())->toHaveCount(1);

    $novo = Space::create(['label' => 'Cursos', 'sort' => 2]);
    $novo->pages()->create(['label' => 'Aprendizagem', 'sort' => 1])
        ->sections()->create(['title' => 'Atalhos', 'sort' => 1])
        ->cards()->create(['title' => 'Atalho', 'type' => 'shortcut', 'target_type' => 'none']);

    // Guardar em memoria nao pode esconder uma edicao de quem a acabou de fazer.
    expect($plugin->getSpaces())->toHaveCount(2);
});
