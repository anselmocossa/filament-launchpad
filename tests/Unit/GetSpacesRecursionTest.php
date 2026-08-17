<?php

use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Pages\Launchpad;

/**
 * O ciclo que deitou o painel de administracao abaixo a 17/08/2026.
 *
 * Construir os spaces avalia o canAccess() de cada card. Um desses cards
 * apontava para uma pagina cujo canAccess() pergunta ao Shield pelos rotulos
 * das permissoes; o Shield le esses rotulos chamando getTitle() em CADA pagina
 * registada — incluindo a do proprio launchpad. E o getTitle(), desde a v1.8.0,
 * resolvia o nome do space activo... voltando a construir os spaces.
 *
 * Ida e volta sem fim: 500 na cara do utilizador, memoria esgotada, e nem uma
 * linha no log da aplicacao, porque o processo morre antes de a escrever.
 */

class PaginaQuePerguntaPelosSpaces
{
    /** @var Closure|null */
    public static $aoVerificar = null;

    public static function canAccess(): bool
    {
        if (self::$aoVerificar instanceof Closure) {
            (self::$aoVerificar)();
        }

        return true;
    }
}

afterEach(function () {
    PaginaQuePerguntaPelosSpaces::$aoVerificar = null;
});

it('nao volta a construir os spaces quando um card pergunta por eles a meio da construcao', function () {
    $space = Space::create(['label' => 'Avaliação Desempenho', 'sort' => 1]);
    $page = $space->pages()->create(['label' => 'A minha avaliação', 'sort' => 1]);
    $section = $page->sections()->create(['title' => 'Atalhos', 'sort' => 1]);
    $section->cards()->create([
        'title' => 'Registo de alterações',
        'type' => 'shortcut',
        'target_type' => 'page',
        'target_value' => PaginaQuePerguntaPelosSpaces::class,
    ]);

    $plugin = LaunchpadPlugin::make();

    $profundidade = 0;
    $profundidadeMaxima = 0;

    PaginaQuePerguntaPelosSpaces::$aoVerificar = function () use ($plugin, &$profundidade, &$profundidadeMaxima) {
        // Rede de seguranca do proprio teste: sem trava no plugin isto seria
        // infinito, e um teste que esgota a memoria leva a suite toda atras.
        if ($profundidade >= 3) {
            return;
        }

        $profundidade++;
        $profundidadeMaxima = max($profundidadeMaxima, $profundidade);

        $plugin->getSpaces();

        $profundidade--;
    };

    $spaces = $plugin->getSpaces();

    expect($spaces)->toHaveCount(1)
        ->and($spaces[0]->getLabel())->toBe('Avaliação Desempenho')
        // 1 = o card foi verificado uma vez, e a pergunta de dentro foi
        // respondida sem reconstruir nada. 2 ou mais = a trava caiu.
        ->and($profundidadeMaxima)->toBe(1);
});

it('devolve nada a quem pergunta pelos spaces a meio da construcao, em vez de recomecar', function () {
    $space = Space::create(['label' => 'Cursos', 'sort' => 1]);
    $page = $space->pages()->create(['label' => 'Aprendizagem', 'sort' => 1]);
    $section = $page->sections()->create(['title' => 'Atalhos', 'sort' => 1]);
    $section->cards()->create([
        'title' => 'Atalho',
        'type' => 'shortcut',
        'target_type' => 'page',
        'target_value' => PaginaQuePerguntaPelosSpaces::class,
    ]);

    $plugin = LaunchpadPlugin::make();

    $respostaDeDentro = 'nao chamado';
    $jaPerguntou = false;

    PaginaQuePerguntaPelosSpaces::$aoVerificar = function () use ($plugin, &$respostaDeDentro, &$jaPerguntou) {
        // Perguntar uma unica vez: sem trava no plugin, perguntar sempre seria
        // infinito, e um teste que esgota a memoria leva a suite toda atras.
        if ($jaPerguntou) {
            return;
        }

        $jaPerguntou = true;
        $respostaDeDentro = $plugin->getSpaces();
    };

    $plugin->getSpaces();

    expect($respostaDeDentro)->toBe([]);
});

it('o titulo de uma pagina por montar nao vai a base de dados', function () {
    Space::create(['label' => 'Avaliação Desempenho', 'sort' => 1]);

    // O painel de teste traz spaces de configuracao, que nunca vao a' base de
    // dados. Aqui interessa o caso real do portal: launchpad guardado na BD.
    $plugin = LaunchpadPlugin::get();
    $configurados = $plugin->getSpaces();
    $plugin->spaces([]);

    try {
        // E' assim que o Shield a instancia: nova, sem mount(), sem space
        // activo. Ir buscar o space nesse estado era o primeiro passo do ciclo.
        $pagina = new Launchpad;

        $consultas = 0;
        DB::listen(function () use (&$consultas) {
            $consultas++;
        });

        $titulo = $pagina->getTitle();

        expect($consultas)->toBe(0)
            ->and($titulo)->toBe('Launchpad');
    } finally {
        $plugin->spaces($configurados);
    }
});
