<?php
namespace App\Http\Controllers\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait IndexHelpers
{
    protected function perPage(Request $r, int $default = 14): int
    {
        return max(1, min((int) $r->input('perPage', $default), 200));
    }

    /** Áp dụng search LIKE trên 1 mảng cột (đã prefix sẵn) */
    protected function applySearch(Builder $q, ?string $s, array $columns): void
    {
        $s = trim((string) $s);
        if ($s === '') return;
        $q->where(function ($w) use ($s, $columns) {
            foreach ($columns as $col) {
                $w->orWhere($col, 'like', "%{$s}%");
            }
        });
    }

    /** Áp dụng sort theo map whitelist: FE key -> DB column */
    protected function applySort(Builder $q, Request $r, array $sortMap, string $defaultBy, string $defaultDir = 'desc'): void
    {
        $by  = (string) $r->input('sortBy', $defaultBy);
        $dir = strtolower((string) $r->input('sortDir', $defaultDir)) === 'asc' ? 'asc' : 'desc';
        $col = $sortMap[$by] ?? $sortMap[$defaultBy] ?? $defaultBy;
        $q->orderBy($col, $dir);
    }
}
