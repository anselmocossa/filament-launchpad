<?php

namespace Filament\Launchpad\Launchpad;

class TileGroup
{
    protected string $title;

    /**
     * @var array<Tile>
     */
    protected array $tiles = [];

    protected function __construct(string $title)
    {
        $this->title = $title;
    }

    public static function make(string $title): static
    {
        return new static($title);
    }

    /**
     * @param  array<Tile>  $tiles
     */
    public function tiles(array $tiles): static
    {
        $this->tiles = $tiles;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return array<Tile>
     */
    public function getTiles(): array
    {
        return $this->tiles;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'tiles' => array_map(fn (Tile $tile): array => $tile->toArray(), $this->tiles),
        ];
    }
}
