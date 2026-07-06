@once
    @push('styles')
        <style>
            .fi-launchpad-bar [x-cloak] {
                display: none !important;
            }

            .fi-launchpad-hidden {
                display: none !important;
            }
        </style>
    @endpush
@endonce

<div class="fi-topbar-ctn fi-launchpad-bar-ctn" style="position:sticky;top:4rem;z-index:20">
    <nav
        class="fi-topbar fi-launchpad-bar"
        style="min-height:auto;padding-top:0.375rem;padding-bottom:0.375rem"
        data-launchpad-bar
        x-data="launchpadOverflow()"
        x-init="init()"
    >
        {{-- One full-width flex row (kept a single block-level <ul> so the injected
             bar stretches edge-to-edge, exactly as the original did) holding three
             flex children: the ☰ shell menu (fixed), the growable overflow list of
             space tabs, and the "Mais" catch-all (fixed). Only the middle child
             clips; the ends never do. --}}
        <ul class="fi-topbar-nav-groups" style="display:flex;align-items:center;margin:0;width:100%">
            {{-- Zone 1 — "Todos os Spaces" (☰): a SAP-Fiori-style shell menu that lists
                 every configured space and its pages in one place, so the user can jump
                 anywhere without hunting through the individual space tabs/dropdowns.
                 It sits OUTSIDE the overflow container below, so it is always visible
                 and never clipped. The trigger mirrors the plain (non-dropdown) topbar
                 item markup — same <li class="fi-topbar-item"> + <button
                 class="fi-topbar-item-btn"> structure and the same generate_icon_html()
                 helper — so it lines up visually with the sub-nav, icon-only, no label. --}}
            <li class="fi-topbar-item" x-ref="hamburger" style="flex:0 0 auto">
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
                            {{-- Multi-page space: a group header naming the space, then its
                                 pages listed underneath (SAP Fiori "All Spaces" shape). --}}
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
                            {{-- Single-page space: one entry only — no redundant header that
                                 would repeat the same label as its sole page. --}}
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

            {{-- Zone 2 — the space tabs. This middle flex child grows to fill the room
                 between the ☰ and "Mais" ends (flex:1 1 auto) and can shrink below its
                 content width (min-width:0), clipping (overflow:hidden) whatever doesn't
                 fit. The inner <ul x-ref=list> is what Alpine measures; each tab is
                 flex:0 0 auto so tabs never squish — they either fit or overflow, and
                 the overflowing ones get hidden here and shown under "Mais". The bar
                 itself never scrolls horizontally. --}}
            <li style="flex:1 1 auto;min-width:0;overflow:hidden;display:flex;list-style:none">
                <ul
                    class="fi-topbar-nav-groups fi-launchpad-bar-nav"
                    style="display:flex;flex-wrap:nowrap;overflow:hidden;min-width:0;width:100%;margin:0"
                    x-ref="list"
                >
            @foreach ($spaces as $space)
                @if ($space['hasDropdown'])
                    {{-- The dropdown's own root (.fi-dropdown) IS the flex child the
                         overflow math measures and hides — data-space-id / x-bind:class
                         are forwarded onto it via the component's $attributes. We must
                         NOT wrap it in an <li>: the trigger is <x-filament-panels::topbar.item>,
                         which renders its own <li>, and an <li> nested (even through divs)
                         inside another <li> gets hoisted out by the HTML parser's
                         list-item-scope rule, emptying the trigger. Being a direct child
                         of the <ul> (as in the original markup) sidesteps that entirely. --}}
                    <x-filament::dropdown
                        placement="bottom-start"
                        teleport
                        class="fi-launchpad-bar-space"
                        data-space-id="{{ $space['id'] }}"
                        x-bind:class="{ 'fi-launchpad-hidden': hidden.includes('{{ $space['id'] }}') }"
                        style="flex:0 0 auto"
                    >
                        <x-slot name="trigger">
                            <x-filament-panels::topbar.item :active="$space['active']" :icon="$space['icon'] ?? null">
                                {{ $space['label'] }}
                            </x-filament-panels::topbar.item>
                        </x-slot>

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
                    {{-- A single-page space is a plain (non-toggle) topbar item: it has
                         no $url (it's a Livewire action, not a real link), so we can't use
                         <x-filament-panels::topbar.item> as-is here — that component renders
                         a dropdown-toggle chevron whenever $url is empty (see
                         vendor/filament/filament resources/views/components/topbar/item.blade.php).
                         We reuse its exact classes/structure by hand instead, omitting the
                         chevron, so hover/active/dark styling still comes from Filament's own
                         topbar.css for free. The icon markup (icon before label, wrapped with
                         the fi-topbar-item-icon class via generate_icon_html) mirrors that same
                         vendor component so it looks identical to the native topbar items. --}}
                    <li
                        @class(['fi-topbar-item', 'fi-launchpad-bar-space', 'fi-active' => $space['active']])
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
                </ul>
            </li>

            {{-- Zone 3 — Overflow ("Mais ▾"): a priority-nav catch-all, a flex child
                 AFTER the tabs container so it is NEVER clipped by that container's
                 overflow:hidden. Shown only once Alpine has measured that at least one
                 space tab doesn't fit. It reuses the exact same native dropdown shapes as
                 the ☰ shell menu (header + list for multi-page spaces, a single list item
                 for single-page ones), but only for the spaces currently hidden from the
                 bar — so nothing is ever unreachable, it just moves here. --}}
            <li
                class="fi-topbar-item"
                x-ref="more"
                x-show="hidden.length > 0"
                x-cloak
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
                            {{ \Filament\Support\generate_icon_html('heroicon-o-chevron-down', attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['fi-topbar-item-icon'])) }}

                            <span class="fi-topbar-item-label">
                                {{ __('launchpad::launchpad.nav.mais') }}
                            </span>
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
    </nav>
</div>

@once
    @push('scripts')
        <script>
            function launchpadOverflow() {
                return {
                    hidden: [],
                    resizeObserver: null,
                    debounceTimer: null,

                    init() {
                        this.$nextTick(() => this.measure());

                        this.resizeObserver = new ResizeObserver(() => this.debouncedMeasure());
                        this.resizeObserver.observe(this.$refs.list);

                        window.addEventListener('resize', () => this.debouncedMeasure());

                        Livewire.hook('morph.updated', () => this.debouncedMeasure());
                    },

                    debouncedMeasure() {
                        clearTimeout(this.debounceTimer);
                        this.debounceTimer = setTimeout(() => this.measure(), 50);
                    },

                    measure() {
                        const list = this.$refs.list;

                        if (! list) {
                            return;
                        }

                        // Reset first, so we measure every tab's natural width rather
                        // than compounding a previous overflow decision.
                        this.hidden = [];

                        this.$nextTick(() => {
                            const items = Array.from(list.querySelectorAll('[data-space-id]'));

                            if (! items.length) {
                                return;
                            }

                            const listStyle = window.getComputedStyle(list);
                            const gap = parseFloat(listStyle.columnGap || listStyle.gap || '0') || 0;

                            // The tabs <ul> already excludes the ☰ and "Mais" siblings,
                            // so its own inner width is exactly the room the tabs have.
                            const available = list.clientWidth;

                            let used = 0;
                            const overflowing = [];

                            items.forEach((item) => {
                                used += item.offsetWidth + gap;

                                if (used > available) {
                                    overflowing.push(item.dataset.spaceId);
                                }
                            });

                            this.hidden = overflowing;
                        });
                    },
                };
            }
        </script>
    @endpush
@endonce
