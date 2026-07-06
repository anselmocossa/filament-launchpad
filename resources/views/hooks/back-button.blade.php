{{-- "‹" back control injected right before the brand in the native Filament
     topbar (via the TOPBAR_LOGO_BEFORE render hook). On the launchpad it walks
     ONE level up the breadcrumb path (Página → the space's first page → the
     root space), stepping toward "/" — handled by LaunchpadBar::goUp() through
     the `launchpad-back` Livewire event. On any other panel page (where the
     launchpad bar isn't present) it falls back to the browser history. Uses the
     native Filament icon-button + the outline chevron so its size/stroke match
     Filament's own topbar controls in light and dark themes. --}}
<x-filament::icon-button
    tag="button"
    icon="heroicon-o-chevron-left"
    color="gray"
    label="{{ __('launchpad::launchpad.buttons.voltar') }}"
    x-on:click="document.querySelector('[data-launchpad-bar]') ? window.Livewire.dispatch('launchpad-back') : window.history.back()"
    class="fi-launchpad-back"
/>
