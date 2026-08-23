<?php

declare(strict_types=1);

namespace App\Modules\Search\Domain;

use DomainException;

final readonly class SearchQuery
{
    public string $term;

    public function __construct(string $term, public ?int $categoryId = null, public ?int $brandId = null, public int $page = 1, public int $perPage = 20)
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($term));
        $this->term = mb_strtolower($normalized ?? '', 'UTF-8');
        if (mb_strlen($this->term, 'UTF-8') > 100 || $this->page < 1 || $this->page > 100 || $this->perPage < 1 || $this->perPage > 50) {
            throw new DomainException('Search input is outside the approved bounds.');
        }
        if (($this->categoryId !== null && $this->categoryId <= 0) || ($this->brandId !== null && $this->brandId <= 0)) {
            throw new DomainException('Search filter identity is invalid.');
        }
    }

    /** @return array{term: string, category_id: int|null, brand_id: int|null, page: int, per_page: int} */
    public function normalized(): array
    {
        return ['term' => $this->term, 'category_id' => $this->categoryId, 'brand_id' => $this->brandId, 'page' => $this->page, 'per_page' => $this->perPage];
    }
}
