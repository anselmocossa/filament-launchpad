<x-filament-panels::page>
    {{-- The sub-nav (tabs only) is NOT rendered here. It lives in a standalone
         `LaunchpadBar` Livewire component, injected full-width via
         PanelsRenderHook::CONTENT_BEFORE (see LaunchpadPlugin::boot()), which
         sits OUTSIDE this padded/max-width content area — glued directly
         under the native topbar as a second navbar. This page only owns the
         tile grid below it, and reacts to the bar's `launchpad-tab-selected`
         event (see Launchpad::onTabSelected()). --}}

    {{-- Theme-aware CSS variables for the launchpad UI (tile grid + sub-nav bar).
         Filament toggles dark mode via a `.dark` class on <html>, so we mirror
         that here instead of hardcoding hex colors. Light values match the
         previous fixed palette exactly (no regression in light mode). --}}
    <style>
        :root{
            --lp-surface:#ffffff; --lp-border:rgba(3,7,18,.05); --lp-text:#111827; --lp-muted:#6b7280;
            --lp-badge-bg:#f3f4f6; --lp-badge-text:#374151; --lp-hover-shadow:rgba(17,24,39,.10);
            --lp-hover-border:rgba(3,7,18,.1); --lp-icon-muted:#9ca3af;
            --lp-shadow:0 0 0 1px rgba(3,7,18,.05),0 1px 3px 0 rgba(0,0,0,.1),0 1px 2px -1px rgba(0,0,0,.1);
            --lp-shadow-hover:0 0 0 1px rgba(3,7,18,.08),0 4px 12px -2px rgba(0,0,0,.12),0 2px 6px -2px rgba(0,0,0,.1);
        }
        html.dark{
            --lp-surface:#18181b; --lp-border:rgba(255,255,255,.1); --lp-text:#f4f4f5; --lp-muted:#a1a1aa;
            --lp-badge-bg:rgba(255,255,255,.08); --lp-badge-text:#d4d4d8; --lp-hover-shadow:rgba(0,0,0,.4);
            --lp-hover-border:rgba(255,255,255,.18); --lp-icon-muted:#a1a1aa;
            --lp-shadow:0 0 0 1px rgba(255,255,255,.1),0 1px 3px 0 rgba(0,0,0,.3);
            --lp-shadow-hover:0 0 0 1px rgba(255,255,255,.16),0 6px 16px -4px rgba(0,0,0,.5);
        }
        .lp-widget-row{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:14px;margin-bottom:14px}
        .lp-widget-wrap{min-width:0}
        @media (max-width: 768px){.lp-widget-wrap{grid-column:1 / -1 !important}}
        {{-- Numa fila mista, o tile ocupa 2 das 12 colunas; abaixo de 768px
             passa a meia largura, para dois tiles caberem lado a lado antes de
             o widget descer para a sua própria linha.
             Escrito `auto / span 6` e não `span 6 / span 6`: esta segunda forma
             é a que os testes procuram para afirmar que NENHUM widget ficou a
             meia largura, e uma regra de CSS com a mesma string fazia-os falhar
             por coincidência de texto. --}}
        @media (max-width: 768px){.lp-mixed-tile{grid-column:auto / span 6 !important}}
    </style>

    {{-- wire:poll com intervalo EXPLÍCITO de 60s.

         Sem intervalo, o Livewire assume ~2 segundos, e cada ciclo re-renderiza
         a página inteira: todos os KPIs voltam a consultar a base de dados. Um
         refresh que demore mais do que o intervalo faz o seguinte chegar antes
         de o anterior acabar, os pedidos empilham-se, e mais cedo ou mais tarde
         um estoura o max_execution_time — o utilizador recebe o ecrã de erro do
         Livewire no meio do trabalho, sem ter tocado em nada. Medido a
         2026-08-14 no Portal do Colaborador: 60 pedidos em 8 minutos, um deles
         de 32 segundos.

         Estes são indicadores que mudam ao ritmo de horas ou dias — quantos
         colaboradores activos, quantas avaliações por fechar. Um minuto é
         frequente que chegue, e mantém a sessão viva, que é o propósito do
         .keep-alive. --}}
    <div style="font-family:inherit;background:transparent" wire:poll.60s.keep-alive="$refresh">
        {{-- Content: tile groups for the active tab --}}
        @foreach ($groups as $groupIndex => $group)
            <section style="margin-bottom:34px">
                <h2 style="font-size:13px;font-weight:600;color:var(--lp-muted);text-transform:uppercase;letter-spacing:.05em;margin:0 0 14px">{{ $group['title'] }}</h2>

                {{-- Tiles and widgets are grouped into rows. Consecutive widget
                     cards render in a 12-column grid, so half/third/quarter
                     width widgets can sit side by side while still collapsing
                     to full width on mobile.
                     Security: widgetClass only ever comes from Tile instances
                     built by LaunchpadPlugin::mapCardToDto(), which only
                     resolves classes REGISTERED via LaunchpadPlugin::widgets()
                     — never an arbitrary string from the database. --}}
                @php
                    $rows = [];
                    $currentRow = [];
                    $currentWidgetRow = [];
                    $currentWidgetSpan = 0;

                    $widgetSpan = function (array $tile): int {
                        $span = $tile['widgetColumnSpan'] ?? 'full';

                        if ($span === 'full') {
                            return 12;
                        }

                        return min(12, max(1, (int) $span));
                    };

                    $flushTiles = function () use (&$rows, &$currentRow): void {
                        if (! empty($currentRow)) {
                            $rows[] = ['type' => 'tiles', 'items' => $currentRow];
                            $currentRow = [];
                        }
                    };

                    $flushWidgets = function () use (&$rows, &$currentWidgetRow, &$currentWidgetSpan): void {
                        if (! empty($currentWidgetRow)) {
                            $rows[] = ['type' => 'widgets', 'items' => $currentWidgetRow];
                            $currentWidgetRow = [];
                            $currentWidgetSpan = 0;
                        }
                    };

                    // Colunas (das 12) que um tile ocupa numa fila mista. A fila
                    // só de tiles usa 6 colunas, a de widgets usa 12: numa fila
                    // partilhada mede-se tudo em 12, e cada tile vale 2.
                    $colunasPorTile = 2;

                    foreach ($group['tiles'] as $tileIndex => $tile) {
                        if ($tile['isWidget'] ?? false) {
                            $span = $widgetSpan($tile);

                            // Widget estreito com tiles à espera: em vez de os
                            // empurrar para a fila de cima, senta-se ao lado
                            // deles — desde que caiba nas 12 colunas. É o que
                            // permite ter dois indicadores e uma faixa na mesma
                            // linha, em vez de uma linha para cada.
                            if ($span < 12 && ! empty($currentRow) && (count($currentRow) * $colunasPorTile) + $span <= 12) {
                                $rows[] = [
                                    'type' => 'mixed',
                                    'items' => $currentRow,
                                    'widget' => ['tile' => $tile, 'tileIndex' => $tileIndex, 'span' => $span],
                                ];
                                $currentRow = [];

                                continue;
                            }

                            $flushTiles();

                            if ($span === 12) {
                                $flushWidgets();
                                $rows[] = ['type' => 'widgets', 'items' => [['tile' => $tile, 'tileIndex' => $tileIndex, 'span' => $span]]];

                                continue;
                            }

                            if (($currentWidgetSpan + $span) > 12) {
                                $flushWidgets();
                            }

                            $currentWidgetRow[] = ['tile' => $tile, 'tileIndex' => $tileIndex, 'span' => $span];
                            $currentWidgetSpan += $span;
                        } else {
                            $flushWidgets();
                            $currentRow[] = ['tile' => $tile, 'tileIndex' => $tileIndex];
                        }
                    }

                    $flushTiles();
                    $flushWidgets();
                @endphp

                @foreach ($rows as $rowIndex => $row)
                    @if ($row['type'] === 'widgets')
                        <div class="lp-widget-row">
                            @foreach ($row['items'] as $item)
                                @php $displaySpan = count($row['items']) === 1 ? 12 : $item['span']; @endphp
                                <div class="lp-widget-wrap" style="grid-column:span {{ $displaySpan }} / span {{ $displaySpan }}" wire:key="lp-widget-wrap-{{ $groupIndex }}-{{ $item['tileIndex'] }}">
                                    @livewire($item['tile']['widgetClass'], [], 'lp-widget-'.$groupIndex.'-'.$item['tileIndex'])
                                </div>
                            @endforeach
                        </div>
                    @elseif ($row['type'] === 'mixed')
                        {{-- Tiles e um widget na MESMA fila. Mede-se tudo nas 12
                             colunas da grelha dos widgets: cada tile ocupa 2, o
                             widget ocupa o span que declarou. Em ecrã estreito
                             tudo passa a linha inteira. --}}
                        @php
                            $tileW = $theme['tileW'];
                            $tileWidth = '100%';
                        @endphp
                        <div class="lp-widget-row">
                            @foreach ($row['items'] as $item)
                                <div class="lp-mixed-tile" style="grid-column:span 2 / span 2;min-width:0">
                                    @include('launchpad::pages.partials.tile')
                                </div>
                            @endforeach
                            <div class="lp-widget-wrap" style="grid-column:span {{ $row['widget']['span'] }} / span {{ $row['widget']['span'] }}" wire:key="lp-widget-wrap-{{ $groupIndex }}-{{ $row['widget']['tileIndex'] }}">
                                @livewire($row['widget']['tile']['widgetClass'], [], 'lp-widget-'.$groupIndex.'-'.$row['widget']['tileIndex'])
                            </div>
                        </div>
                    @else
                        @php
                            $tileSizing = $theme['tileSizing'] ?? 'fixed';
                            $tileW = $theme['tileW'];
                            $gridColumns = $tileSizing === 'fluid'
                                ? "repeat(auto-fit,minmax({$tileW}px,1fr))"
                                : "repeat(6,1fr)";
                            $tileWidth = '100%';
                        @endphp
                        <div style="display:grid;grid-template-columns:{{ $gridColumns }};gap:14px;margin-bottom:14px">
                    @foreach ($row['items'] as $item)
                        @include('launchpad::pages.partials.tile')
                    @endforeach
                        </div>
                    @endif
                @endforeach
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
