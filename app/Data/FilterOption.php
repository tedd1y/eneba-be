<?php

namespace App\Data;

final readonly class FilterOption
{
    public function __construct(
        public string $value,
        public string $label,
        public int $count,
    ) {}
}
