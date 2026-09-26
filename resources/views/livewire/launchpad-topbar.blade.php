<ul class="fi-topbar-nav-groups fi-launchpad-topbar-navigation" data-launchpad-bar>
    @foreach ($spaces as $space)
        @if ($space['hasDropdown'])
            <x-filament::dropdown placement="bottom-start" teleport>
                <x-slot name="trigger">
                    <x-filament-panels::topbar.item
                        :active="$space['active']"
                        :icon="$space['icon'] ?? null"
                    >
                        {{ $space['label'] }}
                    </x-filament-panels::topbar.item>
                </x-slot>

                <x-filament::dropdown.header
                    :icon="$space['icon'] ?? null"
                    :color="$space['active'] ? 'primary' : 'gray'"
                >
                    {{ $space['label'] }}
                </x-filament::dropdown.header>

                <x-filament::dropdown.list>
                    @foreach ($space['pages'] as $page)
                        <x-filament::dropdown.list.item
                            tag="button"
                            :color="$page['active'] ? 'primary' : 'gray'"
                            :icon="$page['icon'] ?? null"
                            wire:click="selectPage('{{ $space['id'] }}', '{{ $page['id'] }}')"
                            x-on:click="close()"
                        >
                            {{ $page['label'] }}
                        </x-filament::dropdown.list.item>
                    @endforeach
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        @else
            <li @class(['fi-topbar-item', 'fi-active' => $space['active']])>
                <button
                    type="button"
                    wire:click="selectSpace('{{ $space['id'] }}')"
                    class="fi-topbar-item-btn"
                >
                    @if ($space['icon'] ?? null)
                        {{ \Filament\Support\generate_icon_html($space['icon'], attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['fi-topbar-item-icon'])) }}
                    @endif

                    <span class="fi-topbar-item-label">
                        {{ $space['label'] }}
                    </span>
                </button>
            </li>
        @endif
    @endforeach
</ul>
