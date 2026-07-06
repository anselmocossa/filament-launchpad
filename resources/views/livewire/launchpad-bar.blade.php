<nav
    style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;background:#fff;border-bottom:1px solid #e5e7eb;position:sticky;top:0;z-index:10;padding:0 24px;font-family:inherit"
>
    @foreach ($spaces as $space)
        @if ($space['hasDropdown'])
            <div x-data="{ open: false }" x-on:click.outside="open = false" style="position:relative">
                <button
                    type="button"
                    x-on:click="open = ! open"
                    @class(['fi-launchpad-tab', 'fi-launchpad-tab-active' => $space['active']])
                    style="border:none;background:transparent;cursor:pointer;display:inline-flex;align-items:center;gap:4px;padding:14px 14px 12px;font-size:13.5px;font-weight:{{ $space['weight'] }};color:{{ $space['active'] ? '#111827' : '#6b7280' }};border-bottom:2px solid {{ $space['border'] }};font-family:inherit;margin-bottom:-1px"
                >
                    <span>{{ $space['label'] }}</span>
                    <span style="font-size:10px;line-height:1" x-bind:style="open ? 'transform:rotate(180deg)' : ''">▾</span>
                </button>

                <div
                    x-show="open"
                    x-cloak
                    style="position:absolute;top:100%;left:0;margin-top:2px;min-width:190px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 8px 24px rgba(17,24,39,.12);padding:6px;z-index:20"
                >
                    @foreach ($space['pages'] as $page)
                        <button
                            type="button"
                            wire:click="selectPage('{{ $space['id'] }}', '{{ $page['id'] }}')"
                            x-on:click="open = false"
                            style="display:block;width:100%;text-align:left;border:none;cursor:pointer;padding:8px 10px;font-size:13px;border-radius:6px;font-family:inherit;background:{{ $page['active'] ? '#f3f4f6' : 'transparent' }};font-weight:{{ $page['active'] ? 600 : 400 }};color:#111827"
                        >{{ $page['label'] }}</button>
                    @endforeach
                </div>
            </div>
        @else
            <button
                type="button"
                wire:click="selectSpace('{{ $space['id'] }}')"
                @class(['fi-launchpad-tab', 'fi-launchpad-tab-active' => $space['active']])
                style="border:none;background:transparent;cursor:pointer;padding:14px 14px 12px;font-size:13.5px;font-weight:{{ $space['weight'] }};color:{{ $space['active'] ? '#111827' : '#6b7280' }};border-bottom:2px solid {{ $space['border'] }};font-family:inherit;margin-bottom:-1px"
            >{{ $space['label'] }}</button>
        @endif
    @endforeach
</nav>
