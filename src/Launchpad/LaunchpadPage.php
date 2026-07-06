<?php

namespace Filament\Launchpad\Launchpad;

use Illuminate\Support\Str;

/**
 * A page lives inside a LaunchpadSpace and holds 0..N sections (TileGroup).
 * This is where the tile cards actually live — a Space's sub-nav dropdown
 * lists its pages, and selecting one swaps which page's sections render.
 */
class LaunchpadPage
{
    protected string $label;

    protected string $id;

    /**
     * @var array<TileGroup>
     */
    protected array $sections = [];

    protected function __construct(string $label, ?string $id = null)
    {
        $this->label = $label;
        $this->id = $id ?? Str::slug($label);
    }

    public static function make(string $label, ?string $id = null): static
    {
        return new static($label, $id);
    }

    /**
     * @param  array<TileGroup>  $sections
     */
    public function sections(array $sections): static
    {
        $this->sections = $sections;

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return array<TileGroup>
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'sections' => array_map(fn (TileGroup $section): array => $section->toArray(), $this->sections),
        ];
    }
}
