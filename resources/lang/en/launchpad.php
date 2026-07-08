<?php

return [
    // ========== Field Labels ==========
    'labels' => [
        'nome' => 'Name',
        'icone' => 'Icon',
        'ordem' => 'Order',
        'titulo' => 'Title',
        'subtitulo' => 'Subtitle',
        'tipo' => 'Type',
        'permissao' => 'Permission',
        'unidade' => 'Unit',
        'tendencia' => 'Trend',
        'cor_tendencia' => 'Trend Color',
        'badge' => 'Badge',
        'alvo' => 'Target',
        'url' => 'URL',
        'recurso' => 'Resource',
        'pagina' => 'Page',
        'widget' => 'Widget',
        'largura' => 'Width',
        'largura_total' => 'Full width',
        'fonte_ao_vivo' => 'Source (Live)',
        'valor_fixo' => 'Fixed Value',
    ],

    // ========== Form Section Titles ==========
    'sections' => [
        'conteudo' => 'Content',
        'widget' => 'Widget',
        'indicador_kpi' => 'Indicator (KPI)',
        'acao_ao_clicar' => 'Click Action',
    ],

    // ========== Section Descriptions ==========
    'descriptions' => [
        'conteudo' => 'Identification and appearance of the tile.',
        'widget' => 'Shows a dynamic chart or metrics panel in place of this card.',
        'indicador_kpi' => 'Dynamic value displayed on the tile.',
        'acao_ao_clicar' => 'Where the tile navigates to.',
    ],

    // ========== Table Columns ==========
    'table_columns' => [
        'paginas' => 'Pages',
        'secoes' => 'Sections',
        'cards' => 'Cards',
        'space' => 'Space',
    ],

    // ========== Buttons ==========
    'buttons' => [
        'novo_space' => 'New Space',
        'nova_pagina' => 'New Page',
        'nova_secao' => 'New Section',
        'novo_card' => 'New Card',
        'editar' => 'Edit',
        'editar_card' => 'Edit Card',
        'eliminar' => 'Delete',
        'remover' => 'Remove',
        'anexar_card' => 'Attach Card',
        'remover_da_seccao' => 'Remove from Section',
        'voltar' => 'Back',
    ],

    // ========== Messages ==========
    'messages' => [
        'card_actualizado' => 'Card updated',
        'abrir' => 'Open',
    ],

    // ========== Placeholders ==========
    'placeholders' => [
        'todos_podem_ver' => 'Everyone can see',
        'valor_fixo' => 'Fixed value',
        'pesquisar_cards' => 'Search cards…',
        'badge_exemplo' => 'e.g., 2 pending',
    ],

    // ========== Helper Texts ==========
    'helpers' => [
        'permissao_vazia' => 'Leave empty so everyone can see.',
        'fonte_ou_valor' => 'If you choose a source, the number is calculated live; otherwise uses the Fixed Value below.',
        'valor_sem_fonte' => 'Used only if no live source is selected.',
    ],

    // ========== Builder (Drag-Drop Constructor) ==========
    'builder' => [
        'instrucao_principal' => 'Drag presets from the library into sections, or reorder/move existing cards.',
        'titulo_biblioteca' => 'Card Library',
        'titulo_catalogo' => 'Existing Cards',
        'titulo_widgets' => 'Widgets',
        'label_pesquisa' => 'Search cards…',
        'label_arrastar_secao' => 'Drag section',
        'confirmacao_eliminar_secao' => 'Delete this section and all its cards?',
        'confirm_delete_section_heading' => 'Delete this section?',
        'confirm_delete_section_body' => 'This removes the section and all its cards.',
        'texto_vazio_grid' => 'Drag a card from the library here',
        'texto_vazio_secoes' => 'No sections yet. Click "New Section" to start.',
        'sem_cards_pesquisa' => 'No cards match the search.',
        'cards_ja_usados' => 'All cards in the library have already been used on this page.',
        'sem_widgets_pesquisa' => 'No widgets match the search.',
        'sem_widgets_registados' => 'No widgets registered.',
        'sem_cards_catalogo_pesquisa' => 'No existing cards match the search.',
        'sem_cards_catalogo' => 'No cards created yet.',
        'instrucao_pessoal' => 'Add available cards to your home and drag to reorder. Fixed cards are set by the administrators.',
        'titulo_disponiveis' => 'Available Cards',
        'sem_cards_disponiveis' => 'No cards available to add.',
        'texto_vazio_grid_pessoal' => 'Drag an available card here',
        'tag_fixo' => 'Fixed',
        'tag_disponivel' => 'Available',
    ],

    // ========== Navigation ==========
    'nav' => [
        'todos_os_spaces' => 'All Spaces',
        'mais' => 'More',
        'paginas' => 'Pages',
        'cards' => 'Cards',
        'construtor' => 'Builder',
        'editar_inicio' => 'Edit Home',
        'spaces' => 'Spaces',
    ],

    // ========== Model Labels ==========
    'models' => [
        'space' => 'Space',
        'spaces' => 'Spaces',
        'pagina' => 'Page',
        'paginas' => 'Pages',
        'secao' => 'Section',
        'secoes' => 'Sections',
        'card' => 'Card',
        'cards' => 'Cards',
    ],

    // ========== Icon Options ==========
    'icons' => [
        'home' => 'Home',
        'grid' => 'Grid',
        'notes' => 'Notes',
        'chart' => 'Chart',
        'cart' => 'Cart',
        'cube' => 'Cube',
        'folder' => 'Folder',
        'settings' => 'Settings',
        'users' => 'Users',
        'card' => 'Card',
        'terminal' => 'Terminal',
        'document' => 'Document',
        'archive' => 'Archive',
        'book' => 'Book',
        'alert' => 'Alert',
    ],

    // ========== Card Types ==========
    'card_types' => [
        'kpi' => 'KPI',
        'atalho' => 'Shortcut',
        'widget' => 'Widget',
        'nenhuma' => 'None',
    ],

    // ========== Trend Colors ==========
    'trend_colors' => [
        'success' => 'Success',
        'danger' => 'Danger',
        'warning' => 'Warning',
        'gray' => 'Neutral',
    ],

    // ========== General ==========
    'general' => [
        'title' => 'Launchpad',
        'empty' => 'No tiles configured yet.',
    ],
];
