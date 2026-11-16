<?php

namespace App\Data;

final readonly class ProductFilterOptions
{
    /**
     * @param  list<FilterOption>  $platforms
     * @param  list<FilterOption>  $regions
     * @param  list<FilterOption>  $countries
     * @param  list<FilterOption>  $types
     * @param  list<FilterOption>  $genres
     * @param  list<FilterOption>  $operatingSystems
     */
    public function __construct(
        public array $platforms,
        public array $regions,
        public array $countries,
        public array $types,
        public array $genres,
        public array $operatingSystems,
        public ?int $minPrice,
        public ?int $maxPrice,
        public string $currency,
    ) {}
}
