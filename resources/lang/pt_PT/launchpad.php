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
        'alvo' => 'Alvo',
        'url' => 'URL',
        'recurso' => 'Recurso',
        'pagina' => 'Página',
        'widget' => 'Widget',
        'largura' => 'Largura',
        'largura_total' => 'Largura total',
        'fonte_ao_vivo' => 'Fonte (ao vivo)',
        'valor_fixo' => 'Valor fixo',
    ],

    // ========== Títulos de Secções (Formulário) ==========
    'sections' => [
        'conteudo' => 'Conteúdo',
        'widget' => 'Widget',
        'indicador_kpi' => 'Indicador (KPI)',
        'acao_ao_clicar' => 'Ação ao clicar',
    ],

    // ========== Descrições de Secções ==========
    'descriptions' => [
        'conteudo' => 'Identificação e aparência do tile.',
        'widget' => 'Mostra um gráfico ou quadro de indicadores dinâmico no lugar deste cartão.',
        'indicador_kpi' => 'Valor dinâmico apresentado no tile.',
        'acao_ao_clicar' => 'Para onde o tile navega.',
    ],

    // ========== Colunas de Tabelas ==========
    'table_columns' => [
        'paginas' => 'Páginas',
        'secoes' => 'Secções',
        'cards' => 'Cards',
        'space' => 'Space',
    ],

    // ========== Botões ==========
    'buttons' => [
        'novo_space' => 'Novo Space',
        'nova_pagina' => 'Nova Página',
        'nova_secao' => 'Nova Secção',
        'novo_card' => 'Novo Card',
        'editar' => 'Editar',
        'editar_card' => 'Editar Card',
        'eliminar' => 'Eliminar',
        'remover' => 'Remover',
        'anexar_card' => 'Anexar Card',
        'remover_da_seccao' => 'Remover da Secção',
        'voltar' => 'Voltar',
        'repor_template' => 'Repor Template',
    ],

    // ========== Mensagens ==========
    'messages' => [
        'card_actualizado' => 'Card actualizado',
        'abrir' => 'Abrir',
        'template_reposto' => 'Esquema reposto para o modelo partilhado',
        'repor_template_aviso' => 'Todas as personalizações desta página são descartadas e o modelo partilhado é reposto. Os outros tenants não são afectados.',
        'camada_partilhada' => 'Início partilhado',
        'camada_pessoal' => 'O meu início',
        'badge_template' => 'Modelo',
        'painel' => 'Painel',
        'a_editar' => 'A editar',
        'modelo_global' => 'Modelo global',
        'alteracoes' => ':count alterações',
        'spaces_intro' => 'Os espaços, páginas e cards da sua loja — organize à sua maneira.',
        'sem_alteracoes' => 'sem alterações',
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
        'permissao_vazia' => 'Deixe vazio para todos verem.',
        'fonte_ou_valor' => 'Se escolheres uma fonte, o número é calculado ao vivo; senão usa o Valor fixo abaixo.',
        'valor_sem_fonte' => 'Usado apenas se não houver fonte ao vivo seleccionada.',
    ],

    // ========== Builder (Construtor Drag-Drop) ==========
    'builder' => [
        'instrucao_principal' => 'Arraste presets da biblioteca para as secções, ou reordene/mova os cards existentes.',
        'titulo_biblioteca' => 'Biblioteca de Cards',
        'titulo_catalogo' => 'Cards Existentes',
        'titulo_widgets' => 'Widgets',
        'label_pesquisa' => 'Pesquisar cards…',
        'label_arrastar_secao' => 'Arrastar secção',
        'confirmacao_eliminar_secao' => 'Eliminar esta secção e todos os seus cards?',
        'confirm_delete_section_heading' => 'Eliminar esta secção?',
        'confirm_delete_section_body' => 'Remove a secção e todos os seus cards.',
        'texto_vazio_grid' => 'Arraste um card da biblioteca para aqui',
        'texto_vazio_secoes' => 'Ainda não há secções. Clique em «Nova Secção» para começar.',
        'sem_cards_pesquisa' => 'Nenhum card corresponde à pesquisa.',
        'cards_ja_usados' => 'Todos os cards da biblioteca já foram usados nesta página.',
        'sem_widgets_pesquisa' => 'Nenhum widget corresponde à pesquisa.',
        'sem_widgets_registados' => 'Nenhum widget registado.',
        'sem_cards_catalogo_pesquisa' => 'Nenhum card existente corresponde à pesquisa.',
        'sem_cards_catalogo' => 'Ainda não há cards criados.',
        'instrucao_pessoal' => 'Adicione cards disponíveis ao seu Início e arraste para reordenar. Os cards fixos são definidos pela administração.',
        'titulo_disponiveis' => 'Cards e Widgets Disponíveis',
        'sem_cards_disponiveis' => 'Não há cards disponíveis para adicionar.',
        'texto_vazio_grid_pessoal' => 'Arraste um card disponível para aqui',
        'tag_fixo' => 'Fixo',
        'tag_disponivel' => 'Disponível',
    ],

    // ========== Navegação ==========
    'nav' => [
        'todos_os_spaces' => 'Todos os Spaces',
        'mais' => 'Mais',
        'paginas' => 'Páginas',
        'cards' => 'Cards',
        'construtor' => 'Construtor',
        'editar_inicio' => 'Edit Home',
        'spaces' => 'Spaces',
    ],

    // ========== Labels de Modelos ==========
    'models' => [
        'space' => 'Space',
        'spaces' => 'Spaces',
        'pagina' => 'Página',
        'paginas' => 'Páginas',
        'secao' => 'Secção',
        'secoes' => 'Secções',
        'card' => 'Card',
        'cards' => 'Cards',
    ],

    // ========== Opções de Ícone ==========
    'icons' => [
        'home' => 'Início',
        'grid' => 'Grelha',
        'notes' => 'Notas',
        'chart' => 'Gráfico',
        'cart' => 'Carrinho',
        'cube' => 'Cubo',
        'folder' => 'Pasta',
        'settings' => 'Ajustes',
        'users' => 'Utilizadores',
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
