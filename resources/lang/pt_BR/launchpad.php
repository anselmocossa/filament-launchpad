<?php

return [
    // ========== Labels de Campos ==========
    'labels' => [
        'nome' => 'Nome',
        'icone' => 'Ícone',
        'ordem' => 'Ordem',
        'titulo' => 'Título',
        'subtitulo' => 'Subtítulo',
        'tipo' => 'Tipo',
        'permissao' => 'Permissão',
        'unidade' => 'Unidade',
        'tendencia' => 'Tendência',
        'cor_tendencia' => 'Cor da tendência',
        'badge' => 'Badge',
        'alvo' => 'Destino',
        'url' => 'URL',
        'recurso' => 'Recurso',
        'pagina' => 'Página',
        'widget' => 'Widget',
        'largura' => 'Largura',
        'largura_total' => 'Largura total',
        'fonte_ao_vivo' => 'Fonte (ao vivo)',
        'valor_fixo' => 'Valor fixo',
    ],

    // ========== Títulos de Seções (Formulário) ==========
    'sections' => [
        'conteudo' => 'Conteúdo',
        'widget' => 'Widget',
        'indicador_kpi' => 'Indicador (KPI)',
        'acao_ao_clicar' => 'Ação ao clicar',
    ],

    // ========== Descrições de Seções ==========
    'descriptions' => [
        'conteudo' => 'Identificação e aparência do tile.',
        'widget' => 'Mostra um gráfico ou quadro de indicadores dinâmico no lugar deste cartão.',
        'indicador_kpi' => 'Valor dinâmico apresentado no tile.',
        'acao_ao_clicar' => 'Para onde o tile navega.',
    ],

    // ========== Colunas de Tabelas ==========
    'table_columns' => [
        'paginas' => 'Páginas',
        'secoes' => 'Seções',
        'cards' => 'Cards',
        'space' => 'Space',
    ],

    // ========== Botões ==========
    'buttons' => [
        'novo_space' => 'Novo Space',
        'nova_pagina' => 'Nova Página',
        'nova_secao' => 'Nova Seção',
        'novo_card' => 'Novo Card',
        'editar' => 'Editar',
        'editar_card' => 'Editar Card',
        'eliminar' => 'Excluir',
        'remover' => 'Remover',
        'anexar_card' => 'Anexar Card',
        'remover_da_seccao' => 'Remover da Seção',
        'voltar' => 'Voltar',
    ],

    // ========== Mensagens ==========
    'messages' => [
        'card_actualizado' => 'Card atualizado',
        'abrir' => 'Abrir',
    ],

    // ========== Placeholders ==========
    'placeholders' => [
        'todos_podem_ver' => 'Todos podem ver',
        'valor_fixo' => 'Valor fixo',
        'pesquisar_cards' => 'Pesquisar cards…',
        'badge_exemplo' => 'ex: 2 pendentes',
    ],

    // ========== Helper Texts ==========
    'helpers' => [
        'permissao_vazia' => 'Deixe em branco para todos verem.',
        'fonte_ou_valor' => 'Se você escolher uma fonte, o número é calculado em tempo real; caso contrário, usa o Valor fixo abaixo.',
        'valor_sem_fonte' => 'Usado apenas se não houver uma fonte ao vivo selecionada.',
    ],

    // ========== Builder (Construtor Drag-Drop) ==========
    'builder' => [
        'instrucao_principal' => 'Arraste presets da biblioteca para as seções, ou reordene/mova os cards existentes.',
        'titulo_biblioteca' => 'Biblioteca de Cards',
        'titulo_catalogo' => 'Cards Existentes',
        'titulo_widgets' => 'Widgets',
        'label_pesquisa' => 'Pesquisar cards…',
        'label_arrastar_secao' => 'Arrastar seção',
        'confirmacao_eliminar_secao' => 'Excluir esta seção e todos os seus cards?',
        'confirm_delete_section_heading' => 'Excluir esta seção?',
        'confirm_delete_section_body' => 'Remove a seção e todos os seus cards.',
        'texto_vazio_grid' => 'Arraste um card da biblioteca para aqui',
        'texto_vazio_secoes' => 'Ainda não há seções. Clique em «Nova Seção» para começar.',
        'sem_cards_pesquisa' => 'Nenhum card corresponde à pesquisa.',
        'cards_ja_usados' => 'Todos os cards da biblioteca já foram usados nesta página.',
        'sem_widgets_pesquisa' => 'Nenhum widget corresponde à pesquisa.',
        'sem_widgets_registados' => 'Nenhum widget registrado.',
        'sem_cards_catalogo_pesquisa' => 'Nenhum card existente corresponde à pesquisa.',
        'sem_cards_catalogo' => 'Ainda não há cards criados.',
    ],

    // ========== Navegação ==========
    'nav' => [
        'todos_os_spaces' => 'Todos os Spaces',
        'mais' => 'Mais',
        'paginas' => 'Páginas',
        'cards' => 'Cards',
        'construtor' => 'Construtor',
        'editar_inicio' => 'Editar Início',
        'spaces' => 'Spaces',
    ],

    // ========== Labels de Modelos ==========
    'models' => [
        'space' => 'Space',
        'spaces' => 'Spaces',
        'pagina' => 'Página',
        'paginas' => 'Páginas',
        'secao' => 'Seção',
        'secoes' => 'Seções',
        'card' => 'Card',
        'cards' => 'Cards',
    ],

    // ========== Opções de Ícone ==========
    'icons' => [
        'home' => 'Início',
        'grid' => 'Grade',
        'notes' => 'Notas',
        'chart' => 'Gráfico',
        'cart' => 'Carrinho',
        'cube' => 'Cubo',
        'folder' => 'Pasta',
        'settings' => 'Configurações',
        'users' => 'Usuários',
        'card' => 'Cartão',
        'terminal' => 'Terminal',
        'document' => 'Documento',
        'archive' => 'Arquivo',
        'book' => 'Livro',
        'alert' => 'Alerta',
    ],

    // ========== Tipos de Cards ==========
    'card_types' => [
        'kpi' => 'KPI',
        'atalho' => 'Atalho',
        'widget' => 'Widget',
        'nenhuma' => 'Nenhuma',
    ],

    // ========== Cores de Tendência ==========
    'trend_colors' => [
        'success' => 'Sucesso',
        'danger' => 'Perigo',
        'warning' => 'Aviso',
        'gray' => 'Neutro',
    ],

    // ========== Gerais ==========
    'general' => [
        'title' => 'Launchpad',
        'empty' => 'Ainda não há tiles configurados.',
    ],
];
