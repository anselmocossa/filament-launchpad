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
    {{-- Hidden on the first page load until laid out (1 s CSS fallback); never on a
         Livewire update, or the morph would hide the bar again. --}}
    style="flex:1 1 0%;min-width:0;flex-wrap:nowrap;overflow:hidden{{ \Livewire\Livewire::isLivewireRequest() ? '' : ';visibility:hidden;animation:fi-launchpad-reveal 0s linear 1s forwards' }}"
>
    {{-- Small screens (< 1024 px): only this ☰, listing every space and its pages
         (the "Todos os Spaces" shell menu of the sub-navigation bar). From 1024 px it
         hides and the spaces themselves show. --}}
    <li class="fi-topbar-item fi-launchpad-topbar-menu" style="flex:0 0 auto">
        <x-filament::dropdown
            placement="bottom-start"
            teleport
            size
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
                size
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
            size
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
            @keyframes fi-launchpad-reveal {
                to {
                    visibility: visible;
                }
            }

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

                /* ☰ + logo only: the "‹" back control would sit between them. */
                .fi-topbar .fi-launchpad-back {
                    display: none;
                }

                /* The start zone grows with a zero basis on wide screens (set inline in
                   init()); here it keeps the ☰'s size, or the topbar end covers it. */
                .fi-topbar .fi-topbar-start {
                    flex: 0 0 auto !important;
                    min-width: auto !important;
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
                    // Natural width of each space (by id) and of «Mais», measured once:
                    // later layouts reuse them, so the bar never has to show every space
                    // again just to measure (that was the flicker after picking from «Mais»).
                    sizes: {},
                    moreWidth: null,
                    debounceTimer: null,

                    init() {
                        // The list sits inside the topbar's start zone, next to the logo.
                        // That zone takes the free room up to the topbar end with a ZERO
                        // basis: with `auto` its width followed the list's content, so the
                        // list was measured wider (all spaces showing) than it ended up
                        // (some hidden) and «Mais» got clipped.
                        const start = this.$el.parentElement;

                        if (start) {
                            start.style.flex = '1 1 0%';
                            start.style.minWidth = '0';
                        }

                        this.$nextTick(() => this.measure());

                        // Labels change width once the web font arrives: measure afresh.
                        if (document.fonts && document.fonts.ready) {
                            document.fonts.ready.then(() => {
                                this.sizes = {};
                                this.moreWidth = null;
                                this.debouncedMeasure();
                            });
                        }

                        new ResizeObserver(() => this.debouncedMeasure()).observe(this.$el);
                        window.addEventListener('resize', () => this.debouncedMeasure());
                        // A morph of this list (a space picked in the bar or in «Mais»)
                        // lays out at once, before the browser paints; others debounce.
                        Livewire.hook('morph.updated', ({ el }) => {
                            if (el === this.$el || this.$el.contains(el)) {
                                this.measure();

                                return;
                            }

                            this.debouncedMeasure();
                        });
                    },

                    debouncedMeasure() {
                        clearTimeout(this.debounceTimer);
                        this.debounceTimer = setTimeout(() => this.measure(), 50);
                    },

                    items() {
                        return Array.from(this.$el.querySelectorAll(':scope > [data-space-id]'));
                    },

                    measure() {
                        const list = this.$el;
                        const items = this.items();

                        if (! items.length) {
                            this.reveal();

                            return;
                        }

                        const known = this.moreWidth !== null && items.every((item) => item.dataset.spaceId in this.sizes);

                        if (known) {
                            this.layout(items);

                            return;
                        }

                        // First time (or new fonts): every space at its natural width, with
                        // the list invisible — it keeps its layout, but nothing flashes.
                        list.style.animation = 'none';
                        list.style.visibility = 'hidden';
                        this.hidden = [];

                        this.$nextTick(() => {
                            items.forEach((item) => {
                                this.sizes[item.dataset.spaceId] = Math.ceil(item.getBoundingClientRect().width);
                            });

                            // «Mais» measured while showing, then left exactly as x-show had it
                            // (forcing `none` hid it for good when x-show's value didn't change).
                            const more = this.$refs.more;
                            const display = more.style.display;
                            more.style.display = '';
                            this.moreWidth = Math.ceil(more.getBoundingClientRect().width);
                            more.style.display = display;

                            this.layout(items);
                        });
                    },

                    layout(items) {
                        const list = this.$el;

                        const activeIndex = items.findIndex((item) => item.matches('.fi-active') || item.querySelector('.fi-active'));
                        this.activeSpace = activeIndex >= 0 ? items[activeIndex].dataset.spaceId : null;

                        const style = window.getComputedStyle(list);
                        const gap = parseFloat(style.columnGap || style.gap || '0') || 0;
                        const widths = items.map((item) => (this.sizes[item.dataset.spaceId] || 0) + gap);
                        const total = widths.reduce((sum, width) => sum + width, 0);

                        // Generous room — about one space — so «Mais» and its chevron are
                        // never clipped: better one more space inside «Mais» than a
                        // squeezed bar (rounding, hover background, late fonts).
                        const slack = 120;

                        if (total + slack / 2 <= list.clientWidth) {
                            this.hidden = [];
                            this.reveal();

                            return;
                        }

                        // The active space always stays in the bar: its width is reserved
                        // first, and the spaces before «Mais» give way to it instead. Picking
                        // a space from «Mais» brings it into the bar and pushes the last
                        // visible one into «Mais».
                        const available = list.clientWidth - (this.moreWidth + gap) - slack;
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
                        this.reveal();
                    },

                    // Shown only once laid out (the server renders it hidden, with a 1 s
                    // CSS fallback in case this script never runs).
                    reveal() {
                        this.$nextTick(() => {
                            this.$el.style.animation = 'none';
                            this.$el.style.visibility = 'visible';
                        });
                    },
                };
            }
        </script>
    @endpush
@endonce
