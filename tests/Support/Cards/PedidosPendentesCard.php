<?php

namespace Filament\Launchpad\Tests\Support\Cards;

use Filament\Launchpad\Launchpad\BaseCardPreset;

/**
 * A fully-configured "kpi" preset, overriding every typed property —
 * exercises toArray()'s full shape (including kpi_source) with everything
 * explicitly set, mirroring VendasHoje's role for KpiSource tests.
 */
class PedidosPendentesCard extends BaseCardPreset
{
    protected string $title = 'Pedidos Pendentes';

    protected ?string $subtitle = 'Ponto de Venda';

    protected ?string $icon = 'heroicon-o-clock';

    protected string $type = 'kpi';

    protected ?string $kpiValue = '0';

    protected ?string $unit = 'enc.';

    protected ?string $trend = '+3 hoje';

    protected ?string $trendColor = 'success';

    protected ?string $badge = 'novo';

    protected string $targetType = 'resource';

    protected ?string $targetValue = 'OrderResource';

    protected ?string $kpiSource = 'pedidos_pendentes';
}
