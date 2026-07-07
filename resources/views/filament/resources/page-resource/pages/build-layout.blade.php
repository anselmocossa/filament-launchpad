<x-filament-panels::page>
    {{-- ================================================================
         Construtor de Layout (drag&drop). UI inerente do plugin, por isso
         custom HTML/CSS é aceitável aqui (como na grelha do launchpad).

         Drag&drop = HTML5 Drag and Drop API NATIVA (sem SortableJS nem
         nenhuma lib externa — evita CDN/CSP e build). Todo o estado do
         arrasto vive num pequeno store Alpine (`$store.lpDnd`): o dragstart
         guarda o payload (preset da biblioteca, card do catálogo, OU card a
         mover dentro do canvas); o drop calcula o índice-alvo pela posição
         do rato e chama o método Livewire correspondente. O componente
         re-renderiza a partir da BD.

         Cards são um catálogo reutilizável (belongsToMany com Section): o
         mesmo Card pode estar em várias secções. O "×" de um tile faz apenas
         DETACH da secção actual (removeCard(sectionId, cardId)) — nunca
         apaga o Card, por isso não pede confirmação (é instantâneo,
         não-destrutivo). O Card só é apagado definitivamente em /admin/cards.
    ================================================================= --}}
    <style>
        :root{
            --lp-surface:#ffffff; --lp-border:#e5e7eb; --lp-text:#111827; --lp-muted:#6b7280;
            --lp-badge-bg:#f3f4f6; --lp-badge-text:#374151; --lp-icon-muted:#9ca3af;
            --lp-canvas-bg:#f9fafb; --lp-drop:#16a34a;
        }
        html.dark{
            --lp-surface:#18181b; --lp-border:rgba(255,255,255,.1); --lp-text:#f4f4f5; --lp-muted:#a1a1aa;
            --lp-badge-bg:rgba(255,255,255,.08); --lp-badge-text:#d4d4d8; --lp-icon-muted:#a1a1aa;
            --lp-canvas-bg:#111113; --lp-drop:#22c55e;
        }
        .lp-build{display:flex;gap:20px;align-items:flex-start}
        .lp-build__canvas{flex:1;min-width:0;display:flex;flex-direction:column;gap:18px}
        .lp-build__library{width:280px;flex:none;position:sticky;top:16px}
        .lp-section{background:var(--lp-canvas-bg);border:1px solid var(--lp-border);border-radius:14px;padding:14px}
        .lp-section--over{border-color:var(--lp-drop);box-shadow:0 0 0 2px var(--lp-drop) inset}
        .lp-section__head{display:flex;align-items:center;gap:10px;margin-bottom:12px}
        .lp-handle{cursor:grab;color:var(--lp-icon-muted);font-size:16px;line-height:1;user-select:none}
        .lp-section__title{flex:1;font-size:14px;font-weight:600;color:var(--lp-text);background:transparent;border:1px solid transparent;border-radius:6px;padding:3px 6px;font-family:inherit}
        .lp-section__title:hover,.lp-section__title:focus{border-color:var(--lp-border);outline:none}
        .lp-count{font-size:11px;color:var(--lp-muted);background:var(--lp-badge-bg);padding:2px 8px;border-radius:999px}
        .lp-del{font-size:11px;color:#ef4444;background:transparent;border:none;cursor:pointer;padding:3px 6px;font-family:inherit}
        .lp-grid{display:flex;flex-wrap:wrap;gap:12px;min-height:64px}
        .lp-empty{width:100%;border:2px dashed var(--lp-border);border-radius:10px;padding:20px;text-align:center;color:var(--lp-muted);font-size:12.5px}
        .lp-tile{position:relative;width:168px;height:168px;background:var(--lp-surface);border:1px solid var(--lp-border);border-radius:12px;padding:14px;display:flex;flex-direction:column;text-align:left;cursor:grab;transition:box-shadow .15s,border-color .15s}
        .lp-tile:hover{border-color:var(--lp-drop);box-shadow:0 4px 14px rgba(0,0,0,.08)}
        .lp-tile--dragging{opacity:.4}
        .lp-tile__x{position:absolute;top:8px;right:8px;width:20px;height:20px;border-radius:999px;border:none;background:var(--lp-badge-bg);color:var(--lp-muted);cursor:pointer;font-size:12px;line-height:1;display:flex;align-items:center;justify-content:center;z-index:2}
        .lp-tile__x:hover{background:#ef4444;color:#fff}
        .lp-tile__title{font-size:13.5px;font-weight:600;color:var(--lp-text);line-height:1.3;padding-right:22px}
        .lp-tile__sub{font-size:11.5px;color:var(--lp-muted);margin-top:2px}
        .lp-tile__kpi{font-size:26px;font-weight:700;color:var(--lp-text);letter-spacing:-.02em}
        .lp-tile__unit{font-size:12px;font-weight:600;color:var(--lp-muted)}
        .lp-tile__trend{font-size:10.5px;font-weight:500;margin-top:2px}
        .lp-tile__badge{position:absolute;top:10px;right:34px;font-size:10.5px;font-weight:600;padding:2px 7px;border-radius:999px;background:var(--lp-badge-bg);color:var(--lp-badge-text)}
        .lp-lib{background:var(--lp-canvas-bg);border:1px solid var(--lp-border);border-radius:14px;padding:14px}
        .lp-lib__title{font-size:12px;font-weight:600;color:var(--lp-muted);text-transform:uppercase;letter-spacing:.05em;margin:0 0 12px}
        .lp-lib__search{width:100%;box-sizing:border-box;font-family:inherit;font-size:12.5px;color:var(--lp-text);background:var(--lp-surface);border:1px solid var(--lp-border);border-radius:8px;padding:7px 10px;margin-bottom:12px}
        .lp-lib__search:focus{outline:none;border-color:var(--lp-drop);box-shadow:0 0 0 2px rgba(22,163,74,.15)}
        .lp-lib__empty{font-size:12px;color:var(--lp-muted);text-align:center;padding:14px 6px;margin:0}
        .lp-lib__item{display:flex;align-items:center;gap:10px;background:var(--lp-surface);border:1px solid var(--lp-border);border-radius:10px;padding:10px;margin-bottom:8px;cursor:grab}
        .lp-lib__item:hover{border-color:var(--lp-drop)}
        .lp-lib__ico{color:var(--lp-icon-muted);flex:none}
        .lp-lib__name{flex:1;min-width:0;font-size:12.5px;font-weight:600;color:var(--lp-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .lp-lib__tag{font-size:9.5px;font-weight:600;padding:2px 6px;border-radius:999px}
        .lp-lib__tag--kpi{background:rgba(22,163,74,.12);color:#16a34a}
        .lp-lib__tag--shortcut{background:var(--lp-badge-bg);color:var(--lp-badge-text)}
        .lp-lib__tag--widget{background:rgba(37,99,235,.12);color:#2563eb}
        .lp-tile--widget{cursor:pointer}
        .lp-tile__widget-tag{align-self:flex-start;margin-top:auto}
        .lp-lib__scroll{max-height:252px;overflow-y:auto;overflow-x:hidden;margin:0 -4px;padding:0 4px}
        .lp-lib__scroll::-webkit-scrollbar{width:8px}
        .lp-lib__scroll::-webkit-scrollbar-thumb{background:var(--lp-border);border-radius:999px;border:2px solid transparent;background-clip:content-box}
        .lp-lib__scroll::-webkit-scrollbar-thumb:hover{background:var(--lp-icon-muted);background-clip:content-box}
        .lp-lib__scroll{scrollbar-width:thin;scrollbar-color:var(--lp-border) transparent}
    </style>

    <div
        class="lp-build"
        x-data
        x-init="
            if (! Alpine.store('lpDnd')) {
                Alpine.store('lpDnd', {
                    kind:null, presetKey:null, widgetKey:null, cardId:null, sectionId:null, catalogCardId:null,
                    startPreset(key){ this.kind='preset'; this.presetKey=key; this.widgetKey=null; this.cardId=null; this.sectionId=null; this.catalogCardId=null; },
                    startWidget(key){ this.kind='widget'; this.widgetKey=key; this.presetKey=null; this.cardId=null; this.sectionId=null; this.catalogCardId=null; },
                    startCard(id, sectionId){ this.kind='card'; this.cardId=id; this.sectionId=sectionId; this.presetKey=null; this.widgetKey=null; this.catalogCardId=null; },
                    startSection(id){ this.kind='section'; this.sectionId=id; this.presetKey=null; this.widgetKey=null; this.cardId=null; this.catalogCardId=null; },
                    startCatalogCard(id){ this.kind='catalogCard'; this.catalogCardId=id; this.presetKey=null; this.widgetKey=null; this.cardId=null; this.sectionId=null; },
                    clear(){ this.kind=null; this.presetKey=null; this.widgetKey=null; this.cardId=null; this.sectionId=null; this.catalogCardId=null; },
                });
            }
        "
    >
        {{-- ========================== CANVAS ========================== --}}
        <div class="lp-build__canvas">
            <div style="display:flex;align-items:center;justify-content:space-between">
                <p style="font-size:12.5px;color:var(--lp-muted);margin:0">
                    {{ __('launchpad::launchpad.builder.instrucao_principal') }}
                </p>
                <x-filament::button size="sm" icon="heroicon-o-plus" wire:click="addSection">
                    {{ __('launchpad::launchpad.buttons.nova_secao') }}
                </x-filament::button>
            </div>

            @forelse ($page->sections as $section)
                <div
                    class="lp-section"
                    wire:key="section-{{ $section->id }}"
                    x-data="{ over:false }"
                    :class="over && 'lp-section--over'"
                    data-section-id="{{ $section->id }}"
                    draggable="false"
                    x-on:dragover.prevent="over = true"
                    x-on:dragleave="over = false"
                    x-on:drop.prevent="
                        over = false;
                        const s = $store.lpDnd;
                        if (s.kind === 'section') {
                            if (s.sectionId !== {{ $section->id }}) {
                                $wire.reorderSections(window.lpSectionOrder($el.closest('.lp-build__canvas'), s.sectionId, {{ $section->id }}));
                            }
                            s.clear();
                            return;
                        }
                        const grid = $el.querySelector('.lp-grid');
                        const index = window.lpDropIndex(grid, $event.clientX, $event.clientY);
                        if (s.kind === 'preset') {
                            $wire.addCardFromLibrary({{ $section->id }}, s.presetKey, index);
                        } else if (s.kind === 'widget') {
                            $wire.addWidgetFromLibrary({{ $section->id }}, s.widgetKey, index);
                        } else if (s.kind === 'catalogCard') {
                            $wire.attachCardFromCatalog({{ $section->id }}, s.catalogCardId, index);
                        } else if (s.kind === 'card') {
                            $wire.moveCard(s.cardId, s.sectionId, {{ $section->id }}, index);
                        }
                        s.clear();
                    "
                >
                    <div class="lp-section__head">
                        <span class="lp-handle"
                              draggable="true"
                              title="{{ __('launchpad::launchpad.builder.label_arrastar_secao') }}"
                              x-on:dragstart="$store.lpDnd.startSection({{ $section->id }}); $event.dataTransfer.setData('text/plain','section:{{ $section->id }}')"
                              x-on:dragend="$store.lpDnd.clear()"
                        >⠿</span>
                        <input
                            type="text"
                            class="lp-section__title"
                            value="{{ $section->title }}"
                            x-on:change="$wire.renameSection({{ $section->id }}, $event.target.value)"
                            x-on:keydown.enter.prevent="$event.target.blur()"
                        />
                        <span class="lp-count">{{ $section->cards->count() }} {{ Str::plural('card', $section->cards->count()) }}</span>
                        <button type="button" class="lp-del"
                                wire:click="mountAction('deleteSection', { section: {{ $section->id }} })">{{ __('launchpad::launchpad.buttons.eliminar') }}</button>
                    </div>

                    <div class="lp-grid" data-section-id="{{ $section->id }}">
                        @forelse ($section->cards as $card)
                            <div
                                class="lp-tile @if ($card->type === 'widget') lp-tile--widget @endif"
                                wire:key="card-{{ $section->id }}-{{ $card->id }}"
                                data-card-id="{{ $card->id }}"
                                draggable="true"
                                x-on:dragstart="$store.lpDnd.startCard({{ $card->id }}, {{ $section->id }}); $event.dataTransfer.setData('text/plain','card:{{ $card->id }}'); $el.classList.add('lp-tile--dragging')"
                                x-on:dragend="$el.classList.remove('lp-tile--dragging')"
                                wire:click="mountAction('editCard', { card: {{ $card->id }} })"
                            >
                                {{-- Non-destructive: only detaches this card from THIS
                                     section (removeCard), never deletes the Card — so no
                                     confirmation modal, the removal is instant. --}}
                                <button type="button" class="lp-tile__x"
                                        x-on:click.stop="$wire.removeCard({{ $section->id }}, {{ $card->id }})"
                                        title="{{ __('launchpad::launchpad.buttons.remover') }}">&times;</button>

                                @if (filled($card->badge))
                                    <span class="lp-tile__badge">{{ $card->badge }}</span>
                                @endif

                                <div class="lp-tile__title">{{ $card->title }}</div>
                                @if (filled($card->subtitle))
                                    <div class="lp-tile__sub">{{ $card->subtitle }}</div>
                                @endif
                                <div style="flex:1"></div>

                                @if ($card->type === 'widget')
                                    <div style="display:flex;align-items:flex-end;justify-content:space-between">
                                        @if (filled($card->icon))
                                            @svg($card->icon, '', ['style' => 'width:24px;height:24px;color:var(--lp-icon-muted)'])
                                        @endif
                                        <span class="lp-lib__tag lp-lib__tag--widget lp-tile__widget-tag">{{ __('launchpad::launchpad.card_types.widget') }}</span>
                                    </div>
                                @elseif ($card->type === 'kpi')
                                    <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:8px">
                                        <div style="min-width:0">
                                            <div style="display:flex;align-items:baseline;gap:4px">
                                                <span class="lp-tile__kpi">{{ $card->kpi_value ?: '—' }}</span>
                                                @if (filled($card->unit))
                                                    <span class="lp-tile__unit">{{ $card->unit }}</span>
                                                @endif
                                            </div>
                                            @if (filled($card->trend))
                                                <div class="lp-tile__trend" style="color:var(--lp-muted)">{{ $card->trend }}</div>
                                            @endif
                                        </div>
                                        @if (filled($card->icon))
                                            @svg($card->icon, '', ['style' => 'width:20px;height:20px;flex:none;color:var(--lp-icon-muted)'])
                                        @endif
                                    </div>
                                @else
                                    <div style="display:flex;align-items:flex-end;justify-content:space-between">
                                        @if (filled($card->icon))
                                            @svg($card->icon, '', ['style' => 'width:28px;height:28px;color:var(--lp-icon-muted)'])
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="lp-empty">{{ __('launchpad::launchpad.builder.texto_vazio_grid') }}</div>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="lp-empty" style="padding:34px">
                    {{ __('launchpad::launchpad.builder.texto_vazio_secoes') }}
                </div>
            @endforelse
        </div>

        {{-- ========================= BIBLIOTECA ======================= --}}
        <div class="lp-build__library">
            <div class="lp-lib">
                <p class="lp-lib__title">{{ __('launchpad::launchpad.builder.titulo_biblioteca') }}</p>

                <input
                    type="search"
                    class="lp-lib__search"
                    placeholder="{{ __('launchpad::launchpad.builder.label_pesquisa') }}"
                    wire:model.live.debounce.300ms="librarySearch"
                    autocomplete="off"
                />

                <div
                    class="lp-lib__scroll"
                    x-data="{ shown: 8 }"
                    x-on:scroll.passive="if ($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 24) shown += 8"
                >
                    @forelse ($library as $preset)
                        <div
                            class="lp-lib__item"
                            x-show="{{ $loop->index }} < shown"
                            wire:key="preset-{{ $preset['key'] }}"
                            draggable="true"
                            x-on:dragstart="$store.lpDnd.startPreset('{{ $preset['key'] }}'); $event.dataTransfer.setData('text/plain','preset:{{ $preset['key'] }}')"
                            x-on:dragend="$store.lpDnd.clear()"
                        >
                            @if (filled($preset['icon'] ?? null))
                                @svg($preset['icon'], '', ['class' => 'lp-lib__ico', 'style' => 'width:18px;height:18px'])
                            @endif
                            <span class="lp-lib__name">{{ $preset['title'] ?? $preset['key'] }}</span>
                            <span class="lp-lib__tag lp-lib__tag--{{ ($preset['type'] ?? 'kpi') === 'kpi' ? 'kpi' : 'shortcut' }}">
                                {{ ($preset['type'] ?? 'kpi') === 'kpi' ? __('launchpad::launchpad.card_types.kpi') : __('launchpad::launchpad.card_types.atalho') }}
                            </span>
                        </div>
                    @empty
                        <p class="lp-lib__empty">
                            @if (filled(trim($this->librarySearch)))
                                {{ __('launchpad::launchpad.builder.sem_cards_pesquisa') }}
                            @else
                                {{ __('launchpad::launchpad.builder.cards_ja_usados') }}
                            @endif
                        </p>
                    @endforelse
                </div>
            </div>

            {{-- Catálogo de cards existentes: qualquer Card já criado (em
                 qualquer secção, de qualquer página) pode ser arrastado para
                 outra secção — attachCardFromCatalog() liga o MESMO registo,
                 nunca cria um novo. --}}
            <div class="lp-lib" style="margin-top:14px">
                <p class="lp-lib__title">{{ __('launchpad::launchpad.builder.titulo_catalogo') }}</p>

                <div
                    class="lp-lib__scroll"
                    x-data="{ shown: 8 }"
                    x-on:scroll.passive="if ($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 24) shown += 8"
                >
                    @forelse ($cardCatalog as $catalogCard)
                        <div
                            class="lp-lib__item"
                            x-show="{{ $loop->index }} < shown"
                            wire:key="catalog-card-{{ $catalogCard['id'] }}"
                            draggable="true"
                            x-on:dragstart="$store.lpDnd.startCatalogCard({{ $catalogCard['id'] }}); $event.dataTransfer.setData('text/plain','catalog-card:{{ $catalogCard['id'] }}')"
                            x-on:dragend="$store.lpDnd.clear()"
                        >
                            @if (filled($catalogCard['icon'] ?? null))
                                @svg($catalogCard['icon'], '', ['class' => 'lp-lib__ico', 'style' => 'width:18px;height:18px'])
                            @endif
                            <span class="lp-lib__name">{{ $catalogCard['title'] ?? $catalogCard['id'] }}</span>
                            <span class="lp-lib__tag lp-lib__tag--{{ ($catalogCard['type'] ?? 'kpi') === 'kpi' ? 'kpi' : (($catalogCard['type'] ?? '') === 'widget' ? 'widget' : 'shortcut') }}">
                                {{ match ($catalogCard['type'] ?? 'kpi') {
                                    'kpi' => __('launchpad::launchpad.card_types.kpi'),
                                    'widget' => __('launchpad::launchpad.card_types.widget'),
                                    default => __('launchpad::launchpad.card_types.atalho'),
                                } }}
                            </span>
                        </div>
                    @empty
                        <p class="lp-lib__empty">
                            @if (filled(trim($this->librarySearch)))
                                {{ __('launchpad::launchpad.builder.sem_cards_catalogo_pesquisa') }}
                            @else
                                {{ __('launchpad::launchpad.builder.sem_cards_catalogo') }}
                            @endif
                        </p>
                    @endforelse
                </div>
            </div>

            <div class="lp-lib" style="margin-top:14px">
                <p class="lp-lib__title">{{ __('launchpad::launchpad.builder.titulo_widgets') }}</p>

                <div
                    class="lp-lib__scroll"
                    x-data="{ shown: 8 }"
                    x-on:scroll.passive="if ($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 24) shown += 8"
                >
                    @forelse ($widgetLibrary as $widget)
                        <div
                            class="lp-lib__item"
                            x-show="{{ $loop->index }} < shown"
                            wire:key="widget-{{ $widget['key'] }}"
                            draggable="true"
                            x-on:dragstart="$store.lpDnd.startWidget('{{ $widget['key'] }}'); $event.dataTransfer.setData('text/plain','widget:{{ $widget['key'] }}')"
                            x-on:dragend="$store.lpDnd.clear()"
                        >
                            @if (filled($widget['icon'] ?? null))
                                @svg($widget['icon'], '', ['class' => 'lp-lib__ico', 'style' => 'width:18px;height:18px'])
                            @endif
                            <span class="lp-lib__name">{{ $widget['label'] ?? $widget['key'] }}</span>
                            <span class="lp-lib__tag lp-lib__tag--widget">{{ __('launchpad::launchpad.card_types.widget') }}</span>
                        </div>
                    @empty
                        <p class="lp-lib__empty">
                            @if (filled(trim($this->librarySearch)))
                                {{ __('launchpad::launchpad.builder.sem_widgets_pesquisa') }}
                            @else
                                {{ __('launchpad::launchpad.builder.sem_widgets_registados') }}
                            @endif
                        </p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Índice de largada: conta os cards cujo centro está antes do ponto do
         rato, para inserir na posição correcta. Global, definido uma vez. --}}
    <script>
        window.lpDropIndex = function (grid, x, y) {
            if (! grid) return null;
            const tiles = [...grid.querySelectorAll('.lp-tile:not(.lp-tile--dragging)')];
            let index = tiles.length;
            for (let i = 0; i < tiles.length; i++) {
                const r = tiles[i].getBoundingClientRect();
                if (y < r.top + r.height / 2 || (y < r.bottom && x < r.left + r.width / 2)) {
                    index = i;
                    break;
                }
            }
            return index;
        };

        // Move dragged section id to just before the target section id, in the
        // current DOM order, and return the resulting ordered id list.
        window.lpSectionOrder = function (canvas, draggedId, targetId) {
            if (! canvas) return [];
            let ids = [...canvas.querySelectorAll('[data-section-id]')]
                .map(el => parseInt(el.getAttribute('data-section-id')))
                .filter((v, i, a) => a.indexOf(v) === i);
            ids = ids.filter(id => id !== draggedId);
            const at = ids.indexOf(targetId);
            ids.splice(at < 0 ? ids.length : at, 0, draggedId);
            return ids;
        };
    </script>

    <x-filament-actions::modals />
</x-filament-panels::page>
