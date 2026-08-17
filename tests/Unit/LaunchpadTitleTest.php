<?php

use Filament\Launchpad\Launchpad\LaunchpadPage;
use Filament\Launchpad\Launchpad\LaunchpadSpace;
use Filament\Launchpad\LaunchpadPlugin;

/**
 * O separador do browser deve dizer ONDE a pessoa esta'.
 *
 * Antes escrevia sempre o nome da marca, o que dava separadores todos iguais
 * ("Launchpad — Portal") e indistinguiveis com varios abertos.
 */

it('escreve o nome da pagina activa', function () {
    $plugin = LaunchpadPlugin::make()->brand('Launchpad');

    expect($plugin->resolveTitle(
        LaunchpadSpace::make('Avaliação Desempenho'),
        LaunchpadPage::make('A minha avaliação'),
    ))->toBe('A minha avaliação');
});

it('cai para o nome do space quando nao ha pagina', function () {
    $plugin = LaunchpadPlugin::make()->brand('Launchpad');

    expect($plugin->resolveTitle(LaunchpadSpace::make('Cursos'), null))->toBe('Cursos');
});

it('nao repete quando a pagina e o space se chamam o mesmo', function () {
    $plugin = LaunchpadPlugin::make()->brand('Launchpad');

    expect($plugin->resolveTitle(
        LaunchpadSpace::make('Cursos'),
        LaunchpadPage::make('Cursos'),
    ))->toBe('Cursos');
});

it('cai para o nome da marca quando nao ha space nem pagina', function () {
    $plugin = LaunchpadPlugin::make()->brand('Portal ENH');

    expect($plugin->resolveTitle(null, null))->toBe('Portal ENH');
});

it('aceita um titulo fixo', function () {
    $plugin = LaunchpadPlugin::make()->brand('Launchpad')->title('Portal do Colaborador');

    expect($plugin->resolveTitle(
        LaunchpadSpace::make('Cursos'),
        LaunchpadPage::make('Aprendizagem'),
    ))->toBe('Portal do Colaborador');
});

it('aceita um closure com o space e a pagina', function () {
    $plugin = LaunchpadPlugin::make()->title(
        fn (?LaunchpadSpace $space, ?LaunchpadPage $page): string => trim(
            ($page?->getLabel() ?? '') . ' · ' . ($space?->getLabel() ?? ''),
            ' ·',
        ),
    );

    expect($plugin->resolveTitle(
        LaunchpadSpace::make('Avaliação Desempenho'),
        LaunchpadPage::make('A minha avaliação'),
    ))->toBe('A minha avaliação · Avaliação Desempenho');
});

it('volta ao comportamento por omissao quando se passa null', function () {
    $plugin = LaunchpadPlugin::make()->brand('Launchpad')->title('Fixo')->title(null);

    expect($plugin->resolveTitle(LaunchpadSpace::make('Cursos'), null))->toBe('Cursos');
});
