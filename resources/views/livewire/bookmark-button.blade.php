{{-- "Guardar no Início" no cabecalho da pagina (LaunchpadPlugin::bookmarks()). O titulo
     vem do separador do browser: e o unico que todas as paginas Filament expoem iguais. --}}
<div>
    <x-filament::button
        :color="$saved ? 'warning' : 'gray'"
        :icon="$saved ? 'heroicon-s-star' : 'heroicon-o-star'"
        size="sm"
        x-on:click="$wire.toggle(document.title)"
        wire:loading.attr="disabled"
    >
        {{ $saved ? __('launchpad::launchpad.bookmarks.in_home') : __('launchpad::launchpad.bookmarks.save') }}
    </x-filament::button>
</div>
