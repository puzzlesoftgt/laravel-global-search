<?php
namespace PuzzleSoft\GlobalSearch;

use JsonSerializable;

class SearchResult implements JsonSerializable
{
    public function __construct(
        public string|int $id,
        public string $title,
        public ?string $description = null,
        public ?string $icon = null,
        public ?string $url = null,
    ) {}

    public static function make(
        string|int $id,
        string $title,
        ?string $description = null,
        ?string $icon = null,
        ?string $url = null,
    ): static {
        return new static(
            $id,
            $title,
            $description,
            $icon,
            $url
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'icon' => $this->icon,
            'url' => $this->url,
        ];
    }
}
