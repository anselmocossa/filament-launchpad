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
    </style>

    <div style="font-family:inherit;background:transparent" wire:poll.keep-alive="$refresh">
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

                    foreach ($group['tiles'] as $tileIndex => $tile) {
                        if ($tile['isWidget'] ?? false) {
                            $flushTiles();

                            $span = $widgetSpan($tile);

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
                    @else
                        @php
                            $tileSizing = $theme['tileSizing'] ?? 'fixed';
                            $tileW = $theme['tileW'];
                            $gridColumns = $tileSizing === 'fluid'
                                ? "repeat(auto-fit,{$tileW}px)"
                                : "repeat(auto-fill,{$tileW}px)";
                            $tileWidth = "{$tileW}px";
                        @endphp
                        <div style="display:grid;grid-template-columns:{{ $gridColumns }};gap:14px;margin-bottom:14px">
                    @foreach ($row['items'] as $item)
                        @php $tile = $item['tile']; $tileIndex = $item['tileIndex']; @endphp
                        @php $tileTag = filled($tile['href']) ? 'a' : 'button'; @endphp
                        <{{ $tileTag }}
                            @if ($tileTag === 'a') href="{{ $tile['href'] }}" @else type="button" @endif
                            wire:click.prevent="open({{ $groupIndex }}, {{ $tileIndex }})"
                            x-data="{ hover: false, active: false }"
                            x-on:mouseenter="hover = true"
                            x-on:mouseleave="hover = false; active = false"
                            x-on:mousedown="active = true"
                            x-on:mouseup="active = false"
                            x-bind:style="'position:relative;width:{{ $tileWidth }};box-sizing:border-box;height:{{ $tileW }}px;background:var(--lp-surface);border:0;border-radius:12px;padding:14px;display:flex;flex-direction:column;align-items:stretch;text-align:left;cursor:pointer;font-family:inherit;text-decoration:none;transition:box-shadow .15s,transform .15s;box-shadow:' + (hover ? 'var(--lp-shadow-hover)' : 'var(--lp-shadow)') + ';transform:' + (active ? 'scale(.97)' : 'scale(1)')"
                        >
                            @if ($tile['badge'])
                                @php
                                    // The default gray badge palette (from Tile::$badgeBg/$badgeColor)
                                    // is theme-aware via CSS vars; explicit colored badges (amber,
                                    // green, etc.) set via ->badge($text, $bg, $color) are left as-is.
                                    $isDefaultGrayBadge = $tile['badgeBg'] === '#f3f4f6' && $tile['badgeColor'] === '#374151';
                                    $badgeBg = $isDefaultGrayBadge ? 'var(--lp-badge-bg)' : $tile['badgeBg'];
                                    $badgeColor = $isDefaultGrayBadge ? 'var(--lp-badge-text)' : $tile['badgeColor'];
                                @endphp
                                <span style="position:absolute;top:10px;right:10px;font-size:10.5px;font-weight:600;padding:2px 7px;border-radius:999px;background:{{ $badgeBg }};color:{{ $badgeColor }}">{{ $tile['badge'] }}</span>
                            @endif
                            <div style="font-size:13.5px;font-weight:600;color:var(--lp-text);line-height:1.3;padding-right:26px">{{ $tile['t'] }}</div>
                            <div style="font-size:11.5px;color:var(--lp-muted);margin-top:2px">{{ $tile['s'] }}</div>
                            <div style="flex:1"></div>

                            @if ($tile['hasKpi'])
                                {{-- Variante KPI --}}
                                <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:8px">
                                    <div style="min-width:0">
                                        <div style="display:flex;align-items:baseline;gap:4px">
                                            <span style="font-size:26px;font-weight:700;color:var(--lp-text);letter-spacing:-.02em">{{ $tile['kpi'] }}</span>
                                            @if ($tile['unit'])
                                                <span style="font-size:12px;font-weight:600;color:var(--lp-muted)">{{ $tile['unit'] }}</span>
                                            @endif
                                        </div>
                                        @if ($tile['trend'])
                                            <div style="font-size:10.5px;font-weight:500;color:{{ $tile['trendColor'] }};margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $tile['trend'] }}</div>
                                        @endif
                                    </div>
                                    @if ($tile['icon'])
                                        @svg($tile['icon'], '', ['style' => 'width:20px;height:20px;flex:none;color:var(--lp-icon-muted)'])
                                    @endif
                                </div>
                            @else
                                {{-- Variante só-ícone --}}
                                <div style="display:flex;align-items:flex-end;justify-content:space-between">
                                    @if ($tile['icon'])
                                        @svg($tile['icon'], '', ['style' => 'width:28px;height:28px;color:var(--lp-icon-muted)'])
                                    @endif
                                    @if ($tile['nota'])
                                        <span style="font-size:10.5px;color:var(--lp-icon-muted)">{{ $tile['nota'] }}</span>
                                    @endif
                                </div>
                            @endif
                        </{{ $tileTag }}>
                    @endforeach
                        </div>
                    @endif
                @endforeach
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
