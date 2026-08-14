{{--
    Um tile do launchpad. Extraído da página para poder ser usado tanto numa
    fila só de tiles como numa fila mista (tiles + widget lado a lado), sem
    duplicar sessenta linhas de markup em dois sítios.

    Recebe: $item (com 'tile' e 'tileIndex'), $groupIndex, $theme, $tileW,
    $tileWidth.
--}}
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
                            {{-- Title and badge share one flex row rather than the badge being
                                 absolutely positioned over the corner. It used to be
                                 `position:absolute` with the title reserving a fixed
                                 `padding-right:26px` — enough for a two-character badge and
                                 nothing more, so any worded badge ("3 waiting on HR") printed
                                 straight over the title. Here the title takes the leftover space
                                 and truncates; the badge keeps its natural width. --}}
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px">
                                <div style="flex:1;min-width:0;font-size:13.5px;font-weight:600;color:var(--lp-text);line-height:1.3;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">{{ $tile['t'] }}</div>
                                @if ($tile['badge'])
                                    @php
                                        // The default gray badge palette (from Tile::$badgeBg/$badgeColor)
                                        // is theme-aware via CSS vars; explicit colored badges (amber,
                                        // green, etc.) set via ->badge($text, $bg, $color) are left as-is.
                                        $isDefaultGrayBadge = $tile['badgeBg'] === '#f3f4f6' && $tile['badgeColor'] === '#374151';
                                        $badgeBg = $isDefaultGrayBadge ? 'var(--lp-badge-bg)' : $tile['badgeBg'];
                                        $badgeColor = $isDefaultGrayBadge ? 'var(--lp-badge-text)' : $tile['badgeColor'];
                                    @endphp
                                    <span style="flex:none;max-width:60%;font-size:10.5px;font-weight:600;padding:2px 7px;border-radius:999px;background:{{ $badgeBg }};color:{{ $badgeColor }};white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $tile['badge'] }}</span>
                                @endif
                            </div>
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
