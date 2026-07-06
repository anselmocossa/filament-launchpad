<x-filament-panels::page>
    {{-- The sub-nav (tabs only) is NOT rendered here. It lives in a standalone
         `LaunchpadBar` Livewire component, injected full-width via
         PanelsRenderHook::CONTENT_BEFORE (see LaunchpadPlugin::boot()), which
         sits OUTSIDE this padded/max-width content area — glued directly
         under the native topbar as a second navbar. This page only owns the
         tile grid below it, and reacts to the bar's `launchpad-tab-selected`
         event (see Launchpad::onTabSelected()). --}}
    <div style="font-family:inherit" wire:poll.keep-alive="$refresh">
        {{-- Content: tile groups for the active tab --}}
        @foreach ($groups as $groupIndex => $group)
            <section style="margin-bottom:34px">
                <h2 style="font-size:13px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin:0 0 14px">{{ $group['title'] }}</h2>
                <div style="display:flex;flex-wrap:wrap;gap:14px">
                    @foreach ($group['tiles'] as $tileIndex => $tile)
                        @php $tileTag = filled($tile['href']) ? 'a' : 'button'; @endphp
                        <{{ $tileTag }}
                            @if ($tileTag === 'a') href="{{ $tile['href'] }}" @else type="button" @endif
                            wire:click.prevent="open({{ $groupIndex }}, {{ $tileIndex }})"
                            x-data="{ hover: false, active: false }"
                            x-on:mouseenter="hover = true"
                            x-on:mouseleave="hover = false; active = false"
                            x-on:mousedown="active = true"
                            x-on:mouseup="active = false"
                            x-bind:style="'position:relative;width:{{ $theme['tileW'] }}px;height:{{ $theme['tileW'] }}px;background:#fff;border:1px solid ' + (hover ? '#d1d5db' : '#e5e7eb') + ';border-radius:12px;padding:14px;display:flex;flex-direction:column;align-items:stretch;text-align:left;cursor:pointer;font-family:inherit;text-decoration:none;transition:box-shadow .15s,transform .15s,border-color .15s;box-shadow:' + (hover ? '0 4px 14px rgba(17,24,39,.08)' : 'none') + ';transform:' + (active ? 'scale(.97)' : 'scale(1)')"
                        >
                            @if ($tile['badge'])
                                <span style="position:absolute;top:10px;right:10px;font-size:10.5px;font-weight:600;padding:2px 7px;border-radius:999px;background:{{ $tile['badgeBg'] }};color:{{ $tile['badgeColor'] }}">{{ $tile['badge'] }}</span>
                            @endif
                            <div style="font-size:13.5px;font-weight:600;color:#111827;line-height:1.3;padding-right:26px">{{ $tile['t'] }}</div>
                            <div style="font-size:11.5px;color:#6b7280;margin-top:2px">{{ $tile['s'] }}</div>
                            <div style="flex:1"></div>

                            @if ($tile['hasKpi'])
                                {{-- Variante KPI --}}
                                <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:8px">
                                    <div style="min-width:0">
                                        <div style="display:flex;align-items:baseline;gap:4px">
                                            <span style="font-size:26px;font-weight:700;color:#111827;letter-spacing:-.02em">{{ $tile['kpi'] }}</span>
                                            @if ($tile['unit'])
                                                <span style="font-size:12px;font-weight:600;color:#6b7280">{{ $tile['unit'] }}</span>
                                            @endif
                                        </div>
                                        @if ($tile['trend'])
                                            <div style="font-size:10.5px;font-weight:500;color:{{ $tile['trendColor'] }};margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $tile['trend'] }}</div>
                                        @endif
                                    </div>
                                    @if ($tile['icon'])
                                        @svg($tile['icon'], '', ['style' => 'width:20px;height:20px;flex:none;color:#d1d5db'])
                                    @endif
                                </div>
                            @else
                                {{-- Variante só-ícone --}}
                                <div style="display:flex;align-items:flex-end;justify-content:space-between">
                                    @if ($tile['icon'])
                                        @svg($tile['icon'], '', ['style' => 'width:28px;height:28px;color:#9ca3af'])
                                    @endif
                                    @if ($tile['nota'])
                                        <span style="font-size:10.5px;color:#9ca3af">{{ $tile['nota'] }}</span>
                                    @endif
                                </div>
                            @endif
                        </{{ $tileTag }}>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
