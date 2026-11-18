<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The paging numbers the admin lists send in meta. Not Laravel's own resource paging: the website and the app read
 * this shorter shape (a resource collection of the paginator itself would add links and more keys).
 */
final class PageMeta
{
    /**
     * @return array{current_page: int, last_page: int, per_page: int, total: int}
     */
    public static function of(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
