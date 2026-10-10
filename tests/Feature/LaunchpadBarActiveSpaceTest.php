<?php

use Filament\Facades\Filament;
use Filament\Launchpad\Launchpad\LaunchpadPage;
use Filament\Launchpad\Launchpad\LaunchpadSpace;
use Filament\Launchpad\Launchpad\Tile;
use Filament\Launchpad\Launchpad\TileGroup;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Livewire\LaunchpadBar;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Tests\Support\TestUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Fora do launchpad (uma pagina aberta a partir de um cartao), a barra do topo
 * tinha de voltar sempre ao primeiro espaco: o `mount()` so' olhava para o
 * `?space=` do URL. Agora, sem `?space=`, o espaco activo e' aquele cujo cartao
 * aponta para a pagina em que a pessoa esta'. Estes testes fixam a regra.
 *
 * A barra e' montada a' mao, com o pedido trocado: o Livewire::test() serve o
 * componente num caminho aleatorio, e aqui o caminho do pedido e' o que conta.
 */
const LAST_SPACE_KEY = 'launchpad.last_space.test';

/**
 * Um espaco de uma so' pagina (id "{id}-p1") com um cartao por URL dado.
 *
 * @param  array<int, string>  $urls
 */
function spaceLinking(string $id, array $urls): LaunchpadSpace
{
    return LaunchpadSpace::make(ucfirst($id), $id)->pages([
        LaunchpadPage::make(ucfirst($id), $id.'-p1')->sections([
            TileGroup::make('Atalhos')->tiles(array_map(
                fn (string $url): Tile => Tile::make($url)->url($url),
                $urls,
            )),
        ]),
    ]);
}

/**
 * Monta a barra do topo como se o pedido fosse $uri, com (ou sem) um espaco
 * ja' escolhido guardado na sessao.
 */
function barAt(string $uri, ?string $remembered = null): LaunchpadBar
{
    // In a real request the panel is current; the session key and the panel
    // root (/test) depend on it.
    Filament::setCurrentPanel(Filament::getPanel('test'));

    app()->instance('request', Request::create($uri));

    session()->forget(LAST_SPACE_KEY);

    if ($remembered !== null) {
        session([LAST_SPACE_KEY => $remembered]);
    }

    $bar = new LaunchpadBar;
    $bar->mount(topbarOnly: true);

    return $bar;
}

it('keeps the space whose card links to a resource page active', function () {
    LaunchpadPlugin::get()->spaces([
        spaceLinking('inicio', ['/test/clientes']),
        spaceLinking('documentos', [url('/test/documentos')]),
        spaceLinking('vendas', ['/test/vendas']),
    ]);

    $bar = barAt('/test/documentos');

    expect($bar->activeSpace)->toBe('documentos')
        ->and($bar->activePage)->toBe('documentos-p1');
});

it('keeps the same space on nested paths of the page the card links to', function () {
    LaunchpadPlugin::get()->spaces([
        spaceLinking('inicio', ['/test/clientes']),
        spaceLinking('documentos', ['/test/documentos']),
    ]);

    expect(barAt('/test/documentos/7')->activeSpace)->toBe('documentos')
        ->and(barAt('/test/documentos/7/edit')->activeSpace)->toBe('documentos')
        ->and(barAt('/test/documentos/create')->activeSpace)->toBe('documentos');
});

it('matches whole path segments only and ignores the query string on both sides', function () {
    LaunchpadPlugin::get()->spaces([
        spaceLinking('inicio', ['/test/clientes']),
        spaceLinking('documentos', ['/test/documentos?tab=todos']),
    ]);

    // «documentos-antigos» is another page, not a child of «documentos».
    expect(barAt('/test/documentos-antigos')->activeSpace)->toBe('inicio')
        // The tile's query string never takes part; neither does the request's.
        ->and(barAt('/test/documentos/7?aba=anexos')->activeSpace)->toBe('documentos')
        ->and(barAt('/test/documentos/')->activeSpace)->toBe('documentos');
});

it('activates the page of the matching tile, the one with the longest path', function () {
    LaunchpadPlugin::get()->spaces([
        spaceLinking('inicio', ['/test/clientes']),
        LaunchpadSpace::make('Documentos', 'documentos')->pages([
            LaunchpadPage::make('Geral', 'geral')->sections([
                TileGroup::make('Atalhos')->tiles([Tile::make('Lista')->url('/test/documentos')]),
            ]),
            LaunchpadPage::make('Arquivo', 'arquivo')->sections([
                TileGroup::make('Atalhos')->tiles([
                    Tile::make('Pasta')->url('/test/documentos/arquivo'),
                    // Widgets and inert tiles have no URL and never match.
                    Tile::make('Sem alvo'),
                ]),
            ]),
        ]),
    ]);

    expect(barAt('/test/documentos')->activePage)->toBe('geral')
        ->and(barAt('/test/documentos/9')->activePage)->toBe('geral')
        ->and(barAt('/test/documentos/arquivo/9')->activePage)->toBe('arquivo');
});

it('prefers the space with the longest tile path', function () {
    LaunchpadPlugin::get()->spaces([
        spaceLinking('inicio', ['/test/clientes']),
        spaceLinking('documentos', ['/test/documentos']),
        spaceLinking('arquivo', ['/test/documentos/arquivo']),
    ]);

    expect(barAt('/test/documentos/arquivo/3')->activeSpace)->toBe('arquivo')
        ->and(barAt('/test/documentos/3')->activeSpace)->toBe('documentos');
});

it('lets the remembered space win a URL that has a card in several spaces', function () {
    LaunchpadPlugin::get()->spaces([
        spaceLinking('inicio', ['/test/relatorios']),
        spaceLinking('vendas', ['/test/relatorios']),
        spaceLinking('documentos', ['/test/relatorios']),
    ]);

    expect(barAt('/test/relatorios', remembered: 'documentos')->activeSpace)->toBe('documentos')
        ->and(barAt('/test/relatorios/12', remembered: 'documentos')->activeSpace)->toBe('documentos')
        // The first space may be the remembered one too.
        ->and(barAt('/test/relatorios', remembered: 'inicio')->activeSpace)->toBe('inicio');
});

it('prefers a space other than the first one when nothing is remembered', function () {
    LaunchpadPlugin::get()->spaces([
        spaceLinking('inicio', ['/test/relatorios']),
        spaceLinking('vendas', ['/test/relatorios']),
        spaceLinking('documentos', ['/test/relatorios']),
    ]);

    expect(barAt('/test/relatorios')->activeSpace)->toBe('vendas');

    // Only the first space links here: it is still better than nothing.
    LaunchpadPlugin::get()->spaces([
        spaceLinking('inicio', ['/test/relatorios']),
        spaceLinking('vendas', ['/test/vendas']),
    ]);

    expect(barAt('/test/relatorios')->activeSpace)->toBe('inicio');
});

it('ignores a remembered space that has no card linking to the page', function () {
    LaunchpadPlugin::get()->spaces([
        spaceLinking('inicio', ['/test/relatorios']),
        spaceLinking('vendas', ['/test/vendas']),
        spaceLinking('documentos', ['/test/relatorios']),
    ]);

    // «vendas» was chosen, but does not link to /test/relatorios; a space that
    // no longer exists is ignored too.
    expect(barAt('/test/relatorios', remembered: 'vendas')->activeSpace)->toBe('documentos')
        ->and(barAt('/test/relatorios', remembered: 'apagado')->activeSpace)->toBe('documentos');
});

it('keeps the remembered space even when another space links to a deeper URL', function () {
    // Documentos was chosen; its «lista» card opens /test/documentos, and a
    // button in that page leads to /test/documentos/create, which Início also
    // has as a shortcut. The space the user is working in must not change.
    LaunchpadPlugin::get()->spaces([
        spaceLinking('inicio', ['/test/documentos/create']),
        spaceLinking('documentos', ['/test/documentos']),
    ]);

    expect(barAt('/test/documentos/create', remembered: 'documentos')->activeSpace)->toBe('documentos')
        ->and(barAt('/test/documentos/create')->activeSpace)->toBe('inicio');
});

it('falls back to the first space when no tile links to the page', function () {
    LaunchpadPlugin::get()->spaces([
        spaceLinking('inicio', ['/test/clientes']),
        spaceLinking('documentos', ['/test/documentos']),
    ]);

    $bar = barAt('/test/outra-pagina', remembered: 'documentos');

    expect($bar->activeSpace)->toBe('inicio')
        ->and($bar->activePage)->toBe('inicio-p1');
});

it('never lets a tile for the panel root match every page', function () {
    LaunchpadPlugin::get()->spaces([
        spaceLinking('inicio', ['/test/clientes']),
        // «Voltar ao início»: the panel root, with and without a query string.
        spaceLinking('vendas', [url('/test'), '/test?space=vendas', '/']),
    ]);

    expect(barAt('/test/outra-pagina')->activeSpace)->toBe('inicio')
        // The panel root itself is the launchpad page: the first space, as ever.
        ->and(barAt('/test', remembered: 'vendas')->activeSpace)->toBe('inicio');
});

it('leaves an explicit ?space= alone, whatever the tiles say', function () {
    LaunchpadPlugin::get()->spaces([
        spaceLinking('inicio', ['/test/clientes']),
        LaunchpadSpace::make('Vendas', 'vendas')->pages([
            LaunchpadPage::make('Geral', 'geral')->sections([TileGroup::make('G')->tiles([Tile::make('A')->url('/test/vendas')])]),
            LaunchpadPage::make('Extra', 'extra')->sections([TileGroup::make('G')->tiles([Tile::make('B')->url('/test/extra')])]),
        ]),
        spaceLinking('documentos', ['/test/documentos']),
    ]);

    $bar = barAt('/test/documentos?space=vendas&page=extra');

    expect($bar->activeSpace)->toBe('vendas')
        ->and($bar->activePage)->toBe('extra');

    // An id that names no space keeps today's answer: the first space.
    expect(barAt('/test/documentos?space=apagado')->activeSpace)->toBe('inicio')
        // The launchpad page is untouched: no ?space= on the root is the first space.
        ->and(barAt('/test', remembered: 'documentos')->activeSpace)->toBe('inicio');
});

it('remembers the space chosen by ?space=, by selectSpace and by selectPage', function () {
    LaunchpadPlugin::get()->spaces([
        spaceLinking('inicio', ['/test/clientes']),
        spaceLinking('vendas', ['/test/vendas']),
        spaceLinking('documentos', ['/test/documentos']),
    ]);

    barAt('/test?space=vendas');
    expect(session(LAST_SPACE_KEY))->toBe('vendas');

    Filament::setCurrentPanel(Filament::getPanel('test'));

    Livewire::test(LaunchpadBar::class)->call('selectSpace', 'documentos');
    expect(session(LAST_SPACE_KEY))->toBe('documentos');

    Livewire::test(LaunchpadBar::class)->call('selectPage', 'vendas', 'vendas-p1');
    expect(session(LAST_SPACE_KEY))->toBe('vendas');

    // Ids come from the browser: one that names no space is not remembered.
    Livewire::test(LaunchpadBar::class)->call('selectSpace', 'inventado');
    expect(session(LAST_SPACE_KEY))->toBe('vendas');

    // Resolving from the path is not a choice: it remembers nothing.
    barAt('/test/documentos/3', remembered: 'vendas');
    expect(session(LAST_SPACE_KEY))->toBe('vendas');
});

it('does not count tiles the user cannot see', function () {
    // Database-driven launchpad: the space tree is filtered by role.
    LaunchpadPlugin::get()->spaces([]);

    $gestor = Role::create(['name' => 'Gestor', 'guard_name' => 'web']);

    $tree = function (string $label, int $sort, array $cards) {
        $space = Space::create(['label' => $label, 'sort' => $sort]);
        $section = $space->pages()->create(['label' => $label, 'sort' => 0])
            ->sections()->create(['title' => 'Atalhos', 'sort' => 0]);

        return [
            $space,
            collect($cards)->map(fn (string $url) => $section->cards()->create([
                'title' => $url,
                'type' => 'shortcut',
                'target_type' => 'url',
                'target_value' => $url,
            ])),
        ];
    };

    $tree('Início', 0, ['/test/clientes']);
    [$documentos, $cardsDocumentos] = $tree('Documentos', 1, ['/test/relatorios', '/test/documentos']);
    [$vendas] = $tree('Vendas', 2, ['/test/relatorios']);

    // Documentos' «relatorios» card is for managers only.
    $cardsDocumentos[0]->visibilityRoles()->sync([$gestor->id]);

    auth()->login(TestUser::create(['name' => 'Sem Papel']));
    LaunchpadPlugin::esquecerSpaces();

    // Documentos stays visible (through its other card) but its card for this
    // URL does not count: the other space that links here wins.
    expect(barAt('/test/relatorios')->activeSpace)->toBe((string) $vendas->id)
        ->and(barAt('/test/relatorios', remembered: (string) $documentos->id)->activeSpace)->toBe((string) $vendas->id);

    // For a manager the same URL belongs to both; Documentos comes first.
    $manager = TestUser::create(['name' => 'Gestor']);
    $manager->assignRole('Gestor');
    auth()->login($manager);
    LaunchpadPlugin::esquecerSpaces();

    expect(barAt('/test/relatorios')->activeSpace)->toBe((string) $documentos->id);
});

it('resolves without a single database query once the spaces are built', function () {
    // The built spaces are memoised per panel: warm them under the same panel
    // the bar will run in.
    Filament::setCurrentPanel(Filament::getPanel('test'));
    LaunchpadPlugin::get()->spaces([]);

    $space = Space::create(['label' => 'Início', 'sort' => 0]);
    $section = $space->pages()->create(['label' => 'Início', 'sort' => 0])->sections()->create(['title' => 'A', 'sort' => 0]);
    $other = Space::create(['label' => 'Documentos', 'sort' => 1]);
    $otherSection = $other->pages()->create(['label' => 'Documentos', 'sort' => 0])->sections()->create(['title' => 'A', 'sort' => 0]);

    foreach (range(1, 40) as $i) {
        $section->cards()->create(['title' => "I{$i}", 'type' => 'shortcut', 'target_type' => 'url', 'target_value' => "/test/inicio-{$i}"]);
        $otherSection->cards()->create(['title' => "D{$i}", 'type' => 'shortcut', 'target_type' => 'url', 'target_value' => "/test/documentos-{$i}"]);
    }

    auth()->login(TestUser::create(['name' => 'Utilizador']));
    LaunchpadPlugin::esquecerSpaces();
    LaunchpadPlugin::get()->getSpaces();

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $bar = barAt('/test/documentos-40/edit');

    expect($bar->activeSpace)->toBe((string) $other->id)
        ->and($queries)->toBe(0);
});

it('renders the topbar with the space of the page opened from its card selected', function () {
    Filament::setCurrentPanel(Filament::getPanel('test'));

    LaunchpadPlugin::get()->spaces([
        spaceLinking('inicio', ['/test/clientes']),
        spaceLinking('documentos', [url('/test/documentos')]),
    ]);

    // A page outside the launchpad, served at a real URL with the bar on it.
    Route::get('/test/documentos/{id}', fn () => Blade::render(
        '@livewire(\Filament\Launchpad\Livewire\LaunchpadBar::class, ["topbarOnly" => true])',
    ));

    $html = $this->get('/test/documentos/7')->assertOk()->getContent();

    expect($html)->toMatch('/fi-active[^>]*data-space-id="documentos"/')
        ->and($html)->not->toMatch('/fi-active[^>]*data-space-id="inicio"/');
});
