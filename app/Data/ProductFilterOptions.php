<?php

namespace App\Data;

final readonly class ProductFilterOptions
{
    /**
     * @param  list<FilterOption>  $platforms
     * @param  list<FilterOption>  $regions
     * @param  list<FilterOption>  $types
     */
    public function __construct(
        public array $platforms,
        public array $regions,
        public array $types,
        public ?int $minPrice,
        public ?int $maxPrice,
        public string $currency,
    ) {}
}
