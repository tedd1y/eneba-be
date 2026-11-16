<?php

namespace App\Data;

final readonly class FilterOption
{
    public function __construct(
        public string $value,
        public string $label,
        public int $count,
        public ?string $icon = null,
        public bool $isFeatured = false,
    ) {}
}
