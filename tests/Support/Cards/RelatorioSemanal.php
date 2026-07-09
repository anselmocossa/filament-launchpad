<?php

namespace Filament\Launchpad\Tests\Support\Cards;

use Filament\Launchpad\Launchpad\BaseCardPreset;

/**
 * Not suffixed with "Card" — proves key()/defaultTitle() keep the class
 * name unchanged (no suffix to strip), e.g. RelatorioSemanal => key
 * 'relatorio_semanal', mirroring VendasHoje's role for KpiSource.
 */
class RelatorioSemanal extends BaseCardPreset
{
    protected string $type = 'shortcut';

    protected string $targetType = 'url';

    protected ?string $targetValue = '/admin/relatorios/semanal';
}
