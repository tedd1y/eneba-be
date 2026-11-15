<?php

namespace App\Enums;

/**
 * Sort order for the product listing. A leading `-` means descending.
 */
enum ProductSort: string
{
    case TitleAsc = 'title';
    case TitleDesc = '-title';
    case PriceAsc = 'price';
    case PriceDesc = '-price';
    case DateAsc = 'created_at';
    case DateDesc = '-created_at';

    public function column(): string
    {
        return ltrim($this->value, '-');
    }

    public function direction(): string
    {
        return str_starts_with($this->value, '-') ? 'desc' : 'asc';
    }
}
