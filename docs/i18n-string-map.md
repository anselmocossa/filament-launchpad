# Mapa de Tradução: String Original → Chave de Tradução

Este documento mapeia cada string UI encontrada no código do plugin `filament-launchpad` para a sua chave de tradução correspondente em `resources/lang/{locale}/launchpad.php`.

**Formato:** A chave de tradução segue o padrão `launchpad::{categoria}.{chave}` e será utilizada como `__('launchpad::{categoria}.{chave}')` no código.

---

## Estrutura de Categorias

Os ficheiros de tradução estão organizados em categorias:
- `labels` — labels de campos de formulário
- `sections` — títulos de secções de formulário
- `descriptions` — descrições/helper-text de secções
- `table_columns` — labels de colunas de tabelas
- `buttons` — labels de botões
- `messages` — mensagens (sucesso, erro, etc.)
- `placeholders` — placeholders de inputs
- `helpers` — helper texts de campos
- `builder` — strings do construtor drag-drop
- `nav` — navegação (breadcrumbs, navs, labels de aba)
- `models` — labels de modelos (singular/plural)
- `card_types` — tipos de cards e opções
- `trend_colors` — cores de tendência
- `general` — strings gerais/globais

---

## Mapa Completo

| String Original (PT) | Chave de Tradução | Arquivo | Localização |
|---|---|---|---|
| Nome | `launchpad::labels.nome` | SpaceResource.php | line 45 |
| Ícone | `launchpad::labels.icone` | SpaceResource.php | line 50 |
| Ordem | `launchpad::labels.ordem` | SpaceResource.php | line 54 |
| Páginas | `launchpad::table_columns.paginas` | SpaceResource.php | line 81 |
| Space | `launchpad::models.space` | PageResource.php | line 77 |
| Título | `launchpad::labels.titulo` | SectionResource.php | line 42 |
| Secções | `launchpad::table_columns.secoes` | PageResource.php | line 79 |
| Página | `launchpad::models.pagina` | SectionResource.php | line 67 |
| Cards | `launchpad::table_columns.cards` | SectionResource.php | line 69 |
| Ícone | `launchpad::labels.icone` | CardResource.php | line 125 |
| Título | `launchpad::labels.titulo` | CardResource.php | line 130 |
| Tipo | `launchpad::labels.tipo` | CardResource.php | line 133 |
| KPI | `launchpad::card_types.kpi` | CardResource.php | line 135 |
| Atalho | `launchpad::card_types.atalho` | CardResource.php | line 135 |
| Secção | `launchpad::table_columns.secoes` | CardResource.php | line 138 |
| Página | `launchpad::models.pagina` | CardResource.php | line 142 |
| Space | `launchpad::models.space` | CardResource.php | line 146 |
| Conteúdo | `launchpad::sections.conteudo` | HasCardForm.php | line 30 |
| Identificação e aparência do tile. | `launchpad::descriptions.conteudo` | HasCardForm.php | line 31 |
| Título | `launchpad::labels.titulo` | HasCardForm.php | line 34 |
| Subtítulo | `launchpad::labels.subtitulo` | HasCardForm.php | line 38 |
| Ícone | `launchpad::labels.icone` | HasCardForm.php | line 41 |
| Tipo | `launchpad::labels.tipo` | HasCardForm.php | line 45 |
| KPI | `launchpad::card_types.kpi` | HasCardForm.php | line 47 |
| Atalho | `launchpad::card_types.atalho` | HasCardForm.php | line 48 |
| Widget | `launchpad::card_types.widget` | HasCardForm.php | line 49 |
| Widget | `launchpad::sections.widget` | HasCardForm.php | line 59 |
| Widget Filament nativo... | `launchpad::descriptions.widget` | HasCardForm.php | line 60 |
| Widget | `launchpad::labels.widget` | HasCardForm.php | line 64 |
| Indicador (KPI) | `launchpad::sections.indicador_kpi` | HasCardForm.php | line 79 |
| Valor dinâmico apresentado no tile. | `launchpad::descriptions.indicador_kpi` | HasCardForm.php | line 80 |
| Fonte (ao vivo) | `launchpad::labels.fonte_ao_vivo` | HasCardForm.php | line 84 |
| Valor fixo | `launchpad::placeholders.valor_fixo` | HasCardForm.php | line 96 |
| Se escolheres uma fonte... | `launchpad::helpers.fonte_ou_valor` | HasCardForm.php | line 97 |
| Valor fixo | `launchpad::labels.valor_fixo` | HasCardForm.php | line 99 |
| Usado apenas se não houver fonte ao vivo... | `launchpad::helpers.valor_sem_fonte` | HasCardForm.php | line 100 |
| Unidade | `launchpad::labels.unidade` | HasCardForm.php | line 103 |
| Tendência | `launchpad::labels.tendencia` | HasCardForm.php | line 106 |
| Cor da tendência | `launchpad::labels.cor_tendencia` | HasCardForm.php | line 109 |
| Sucesso | `launchpad::trend_colors.success` | HasCardForm.php | line 111 |
| Perigo | `launchpad::trend_colors.danger` | HasCardForm.php | line 112 |
| Aviso | `launchpad::trend_colors.warning` | HasCardForm.php | line 113 |
| Neutro | `launchpad::trend_colors.gray` | HasCardForm.php | line 114 |
| Badge | `launchpad::labels.badge` | HasCardForm.php | line 124 |
| ex: 2 pendentes | `launchpad::placeholders.badge_exemplo` | HasCardForm.php | line 125 |
| Ação ao clicar | `launchpad::sections.acao_ao_clicar` | HasCardForm.php | line 131 |
| Para onde o tile navega. | `launchpad::descriptions.acao_ao_clicar` | HasCardForm.php | line 132 |
| Alvo | `launchpad::labels.alvo` | HasCardForm.php | line 136 |
| Nenhuma | `launchpad::card_types.nenhuma` | HasCardForm.php | line 138 |
| URL | `launchpad::labels.url` | HasCardForm.php | line 139 |
| Recurso | `launchpad::labels.recurso` | HasCardForm.php | line 140 |
| Página | `launchpad::labels.pagina` | HasCardForm.php | line 141 |
| Permissão | `launchpad::labels.permissao` | HasLaunchpadVisibilityField.php | line 38 |
| Todos podem ver | `launchpad::placeholders.todos_podem_ver` | HasLaunchpadVisibilityField.php | line 43 |
| Deixe vazio para todos verem. | `launchpad::helpers.permissao_vazia` | HasLaunchpadVisibilityField.php | line 44 |
| Edit Home | `launchpad::nav.editar_inicio` | EditHome.php | line 42 |
| Spaces | `launchpad::nav.spaces` | BuildLayout.php | line 46 |
| Construtor | `launchpad::nav.construtor` | BuildLayout.php | line 49 |
| Arraste presets... | `launchpad::builder.instrucao_principal` | build-layout.blade.php | line 85 |
| Nova Secção | `launchpad::buttons.nova_secao` | build-layout.blade.php | line 88 |
| Arrastar secção | `launchpad::builder.label_arrastar_secao` | build-layout.blade.php | line 127 |
| Eliminar esta secção e todos os seus cards? | `launchpad::builder.confirmacao_eliminar_secao` | build-layout.blade.php | line 141 |
| Eliminar | `launchpad::buttons.eliminar` | build-layout.blade.php | line 141 |
| Arraste um card da biblioteca para aqui | `launchpad::builder.texto_vazio_grid` | build-layout.blade.php | line 202 |
| Ainda não há secções... | `launchpad::builder.texto_vazio_secoes` | build-layout.blade.php | line 208 |
| Biblioteca de Cards | `launchpad::builder.titulo_biblioteca` | build-layout.blade.php | line 216 |
| Pesquisar cards… | `launchpad::builder.label_pesquisa` | build-layout.blade.php | line 221 |
| KPI | `launchpad::card_types.kpi` | build-layout.blade.php | line 239 |
| Atalho | `launchpad::card_types.atalho` | build-layout.blade.php | line 239 |
| Nenhum card corresponde à pesquisa. | `launchpad::builder.sem_cards_pesquisa` | build-layout.blade.php | line 245 |
| Todos os cards da biblioteca já foram usados... | `launchpad::builder.cards_ja_usados` | build-layout.blade.php | line 247 |
| Widgets | `launchpad::builder.titulo_widgets` | build-layout.blade.php | line 254 |
| Widget | `launchpad::card_types.widget` | build-layout.blade.php | line 268 |
| Nenhum widget corresponde à pesquisa. | `launchpad::builder.sem_widgets_pesquisa` | build-layout.blade.php | line 273 |
| Nenhum widget registado. | `launchpad::builder.sem_widgets_registados` | build-layout.blade.php | line 275 |
| Remover | `launchpad::buttons.remover` | build-layout.blade.php | line 157 |
| Nova Secção | `launchpad::buttons.nova_secao` | InteractsWithLaunchpadBuilder.php | line 125 |
| Novo Card | `launchpad::messages.abrir` | InteractsWithLaunchpadBuilder.php | line 128 (construído dinamicamente) |
| Editar Card | `launchpad::buttons.editar_card` | InteractsWithLaunchpadBuilder.php | line 367 |
| Editar Card | `launchpad::buttons.editar_card` | InteractsWithLaunchpadBuilder.php | line 368 |
| Card actualizado | `launchpad::messages.card_actualizado` | InteractsWithLaunchpadBuilder.php | line 394 |
| Páginas | `launchpad::nav.paginas` | ListSpaces.php | line 20 |
| Cards | `launchpad::nav.cards` | ListSpaces.php | line 25 |
| Novo Space | `launchpad::buttons.novo_space` | ListSpaces.php | line 30 |
| Nova Página | `launchpad::buttons.nova_pagina` | ListPages.php | line 17 |
| Nova Secção | `launchpad::buttons.nova_secao` | ListSections.php | line 17 |
| Páginas | `launchpad::nav.paginas` | PagesRelationManager.php | line 24 |
| Nome | `launchpad::labels.nome` | PagesRelationManager.php | line 30 |
| Ícone | `launchpad::labels.icone` | PagesRelationManager.php | line 34 |
| Ordem | `launchpad::labels.ordem` | PagesRelationManager.php | line 38 |
| Nome | `launchpad::labels.nome` | PagesRelationManager.php | line 53 |
| Secções | `launchpad::table_columns.secoes` | PagesRelationManager.php | line 56 |
| Nova Página | `launchpad::buttons.nova_pagina` | PagesRelationManager.php | line 62 |
| Editar | `launchpad::buttons.editar` | PagesRelationManager.php | line 66 |
| Secções | `launchpad::nav.paginas` | SectionsRelationManager.php | line 20 |
| Título | `launchpad::labels.titulo` | SectionsRelationManager.php | line 26 |
| Ordem | `launchpad::labels.ordem` | SectionsRelationManager.php | line 30 |
| Título | `launchpad::labels.titulo` | SectionsRelationManager.php | line 45 |
| Cards | `launchpad::table_columns.cards` | SectionsRelationManager.php | line 48 |
| Nova Secção | `launchpad::buttons.nova_secao` | SectionsRelationManager.php | line 54 |
| Editar | `launchpad::buttons.editar` | SectionsRelationManager.php | line 58 |
| Cards | `launchpad::models.cards` | CardsRelationManager.php | line 23 |
| Ícone | `launchpad::labels.icone` | CardsRelationManager.php | line 38 |
| Título | `launchpad::labels.titulo` | CardsRelationManager.php | line 43 |
| Tipo | `launchpad::labels.tipo` | CardsRelationManager.php | line 46 |
| KPI | `launchpad::card_types.kpi` | CardsRelationManager.php | line 48 |
| Atalho | `launchpad::card_types.atalho` | CardsRelationManager.php | line 48 |
| Novo Card | `launchpad::buttons.novo_card` | CardsRelationManager.php | line 53 |
| Todos os Spaces | `launchpad::nav.todos_os_spaces` | launchpad-bar.blade.php | line 46 |
| Mais | `launchpad::nav.mais` | launchpad-bar.blade.php | line 204 |
| Voltar | `launchpad::buttons.voltar` | back-button.blade.php | line 13 |

---

## Notas de Implementação

1. **Namespacing:** Todas as chaves começam com `launchpad::` porque o package é registado com o nome `launchpad` (ver `LaunchpadServiceProvider::$name`).

2. **Categorização:** As strings estão organizadas em 13 categorias para facilitar a manutenção e melhorar a legiblidade do código.

3. **Chaves em snake_case:** As chaves de tradução utilizam `snake_case` em conformidade com as convenções Laravel/Spatie.

4. **Strings dinâmicas:** Algumas strings construídas dinamicamente (ex: breadcrumbs com valor do record) só têm a parte traduzível mapeada. O resto mantém-se como concatenação.

5. **Substituição futura:** Quando o código for actualizado, substitua cada string literal pelo correspondente `__('launchpad::{chave}')` usando este mapa como referência.

---

## Variantes de Idioma

Os catálogos de tradução estão disponíveis em:
- `resources/lang/pt/launchpad.php` — Português genérico (fallback)
- `resources/lang/pt_PT/launchpad.php` — Português de Portugal (variante específica)
- `resources/lang/pt_BR/launchpad.php` — Português do Brasil (variante específica)
- `resources/lang/en/launchpad.php` — Inglês

As diferenças entre `pt`, `pt_PT` e `pt_BR` são mínimas e restritas a:
- Ortografia: "actualizado" (PT) vs "atualizado" (BR)
- Vocabulário: "Permissão" vs "Permissão"; "Deixe vazio" vs "Deixe em branco"
- Verbos: "escolheres" (PT) vs "você escolher" (BR)
- Plurais: "registado" (PT) vs "registrado" (BR)

---

## Estrutura das Chaves

Exemplo de acesso no código:

```blade
<x-filament::button>
    {{ __('launchpad::buttons.novo_space') }}
</x-filament::button>

<input placeholder="{{ __('launchpad::placeholders.todos_podem_ver') }}" />

<p>{{ __('launchpad::helpers.permissao_vazia') }}</p>
```

---

Mapa criado em 2026-07-06 para o plugin `filament-launchpad` versão Filament v5.
