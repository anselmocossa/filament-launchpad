{{-- Spaces as native Filament topbar navigation, beside the logo. The list is the
     component root (Livewire hangs wire:id on it, see v1.9.1) and collapses like the
     sub-navigation bar: it takes only the room left between the logo and the topbar
     end (flex:1 1 0%, min-width:0), every space keeps its natural width, and the
     ones that don't fit move to a "Mais" dropdown at the end of the list. --}}
<ul
    class="fi-topbar-nav-groups fi-launchpad-topbar-navigation"
    data-launchpad-bar
    x-data="launchpadTopbarOverflow()"
    x-init="init()"
    style="flex:1 1 0%;min-width:0;flex-wrap:nowrap;overflow:hidden"
>
    {{-- Small screens (< 1024 px): only this ☰, listing every space and its pages
         (the "Todos os Spaces" shell menu of the sub-navigation bar). From 1024 px it
         hides and the spaces themselves show. --}}
    <li class="fi-topbar-item fi-launchpad-topbar-menu" style="flex:0 0 auto">
        <x-filament::dropdown
            placement="bottom-start"
            teleport
        >
            <x-slot name="trigger">
                <button
                    type="button"
                    class="fi-topbar-item-btn"
                    aria-label="{{ __('launchpad::launchpad.nav.todos_os_spaces') }}"
                >
                    {{ \Filament\Support\generate_icon_html('heroicon-o-bars-3', attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['fi-topbar-item-icon'])) }}
                </button>
            </x-slot>

            @foreach ($spaces as $space)
                @if ($space['hasDropdown'])
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
                @else
                    <x-filament::dropdown.list>
                        <x-filament::dropdown.list.item
                            tag="button"
                            :color="$space['active'] ? 'primary' : 'gray'"
                            :icon="$space['icon'] ?? null"
                            wire:click="selectSpace('{{ $space['id'] }}')"
                            x-on:click="close()"
                        >
                            {{ $space['label'] }}
                        </x-filament::dropdown.list.item>
                    </x-filament::dropdown.list>
                @endif
            @endforeach
        </x-filament::dropdown>
    </li>

    @foreach ($spaces as $space)
        @if ($space['hasDropdown'])
            {{-- The dropdown wrapper is the direct child of the list (the trigger renders
                 its own <li>), so it is what gets measured and hidden. --}}
            <x-filament::dropdown
                placement="bottom-start"
                teleport
                data-space-id="{{ $space['id'] }}"
                x-bind:class="{ 'fi-launchpad-hidden': hidden.includes('{{ $space['id'] }}') }"
                style="flex:0 0 auto"
            >
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
            <li
                @class(['fi-topbar-item', 'fi-active' => $space['active']])
                data-space-id="{{ $space['id'] }}"
                x-bind:class="{ 'fi-launchpad-hidden': hidden.includes('{{ $space['id'] }}') }"
                style="flex:0 0 auto"
            >
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

    {{-- "Mais": only the spaces that don't fit, with the same shapes as above
         (header + pages for multi-page spaces, one item otherwise). Active when the
         current space is one of the hidden ones. --}}
    <li
        class="fi-topbar-item"
        x-ref="more"
        x-show="hidden.length > 0"
        x-cloak
        x-bind:class="{ 'fi-active': hidden.includes(activeSpace) }"
        style="flex:0 0 auto"
    >
        <x-filament::dropdown
            placement="bottom-end"
            teleport
        >
            <x-slot name="trigger">
                <button
                    type="button"
                    class="fi-topbar-item-btn"
                    aria-label="{{ __('launchpad::launchpad.nav.mais') }}"
                >
                    <span class="fi-topbar-item-label">
                        {{ __('launchpad::launchpad.nav.mais') }}
                    </span>

                    {{ \Filament\Support\generate_icon_html('heroicon-m-chevron-down', attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['fi-topbar-group-toggle-icon'])) }}
                </button>
            </x-slot>

            @foreach ($spaces as $space)
                <div x-show="hidden.includes('{{ $space['id'] }}')">
                    @if ($space['hasDropdown'])
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
                    @else
                        <x-filament::dropdown.list>
                            <x-filament::dropdown.list.item
                                tag="button"
                                :color="$space['active'] ? 'primary' : 'gray'"
                                :icon="$space['icon'] ?? null"
                                wire:click="selectSpace('{{ $space['id'] }}')"
                                x-on:click="close()"
                            >
                                {{ $space['label'] }}
                            </x-filament::dropdown.list.item>
                        </x-filament::dropdown.list>
                    @endif
                </div>
            @endforeach
        </x-filament::dropdown>
    </li>
</ul>

@once
    @push('styles')
        <style>
            .fi-launchpad-topbar-navigation [x-cloak] {
                display: none !important;
            }

            .fi-launchpad-topbar-navigation .fi-launchpad-hidden {
                display: none !important;
            }

            .fi-launchpad-topbar-navigation .fi-topbar-item-label {
                white-space: nowrap;
            }

            /* Filament hides the topbar navigation below 1024 px; the ☰ stays, before
               the logo, and the spaces wait for the wider layout. */
            @media (max-width: 63.999rem) {
                .fi-topbar .fi-launchpad-topbar-navigation {
                    display: flex;
                    flex: 0 0 auto !important;
                    order: -1;
                    margin-inline: 0;
                }

                .fi-launchpad-topbar-navigation > :not(.fi-launchpad-topbar-menu) {
                    display: none !important;
                }
            }

            @media (min-width: 64rem) {
                .fi-launchpad-topbar-navigation > .fi-launchpad-topbar-menu {
                    display: none !important;
                }
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            function launchpadTopbarOverflow() {
                return {
                    hidden: [],
                    activeSpace: null,
                    debounceTimer: null,

                    init() {
                        // The list sits inside the topbar's start zone, next to the logo.
                        // Let that zone take the room up to the topbar end, so the list
                        // gets everything that is left and nothing more.
                        const start = this.$el.parentElement;

                        if (start) {
                            start.style.flex = '1 1 auto';
                            start.style.minWidth = '0';
                        }

                        this.$nextTick(() => this.measure());

                        new ResizeObserver(() => this.debouncedMeasure()).observe(this.$el);
                        window.addEventListener('resize', () => this.debouncedMeasure());
                        Livewire.hook('morph.updated', () => this.debouncedMeasure());
                    },

                    debouncedMeasure() {
                        clearTimeout(this.debounceTimer);
                        this.debounceTimer = setTimeout(() => this.measure(), 50);
                    },

                    measure() {
                        const list = this.$el;

                        // Reset first, so every space is measured at its natural width.
                        this.hidden = [];

                        this.$nextTick(() => {
                            const items = Array.from(list.querySelectorAll(':scope > [data-space-id]'));

                            if (! items.length) {
                                return;
                            }

                            const activeIndex = items.findIndex((item) => item.matches('.fi-active') || item.querySelector('.fi-active'));
                            this.activeSpace = activeIndex >= 0 ? items[activeIndex].dataset.spaceId : null;

                            const style = window.getComputedStyle(list);
                            const gap = parseFloat(style.columnGap || style.gap || '0') || 0;
                            const widths = items.map((item) => item.offsetWidth + gap);
                            const total = widths.reduce((sum, width) => sum + width, 0);

                            if (total <= list.clientWidth) {
                                return;
                            }

                            // Room for the "Mais" button itself, measured while visible.
                            const more = this.$refs.more;
                            more.style.display = '';
                            const moreWidth = more.offsetWidth + gap;
                            more.style.display = 'none';

                            // The active space always stays in the bar: its width is reserved
                            // first, and the spaces before "Mais" give way to it instead. Picking
                            // a space from "Mais" brings it into the bar and pushes the last
                            // visible one into "Mais".
                            const available = list.clientWidth - moreWidth;
                            let used = activeIndex >= 0 ? widths[activeIndex] : 0;
                            let full = false;
                            const overflowing = [];

                            items.forEach((item, index) => {
                                if (index === activeIndex) {
                                    return;
                                }

                                if (! full && used + widths[index] <= available) {
                                    used += widths[index];

                                    return;
                                }

                                full = true;
                                overflowing.push(item.dataset.spaceId);
                            });

                            this.hidden = overflowing;
                        });
                    },
                };
            }
        </script>
    @endpush
@endonce
