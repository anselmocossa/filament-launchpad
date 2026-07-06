<?php

namespace Filament\Launchpad\Launchpad;

use Illuminate\Support\Str;

/**
 * A space is the top-level entry in the launchpad sub-nav (SAP Fiori "Space").
 * It holds 1..N pages; a space with a single page renders as a plain button,
 * while a space with several pages shows a dropdown listing them.
 */
class LaunchpadSpace
{
    protected string $label;

    protected string $id;

    protected ?string $icon = null;

    /**
     * @var array<LaunchpadPage>
     */
    protected array $pages = [];

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
     * @param  array<LaunchpadPage>  $pages
     */
    public function pages(array $pages): static
    {
        $this->pages = $pages;

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

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

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * @return array<LaunchpadPage>
     */
    public function getPages(): array
    {
        return $this->pages;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'icon' => $this->icon,
            'pages' => array_map(fn (LaunchpadPage $page): array => $page->toArray(), $this->pages),
        ];
    }
}
