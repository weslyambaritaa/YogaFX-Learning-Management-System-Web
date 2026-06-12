<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

trait MaintainsSequentialSortOrder
{
    protected static function bootMaintainsSequentialSortOrder(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('sort_order') !== null) {
                return;
            }

            $model->setAttribute(
                'sort_order',
                ((int) $model->sortOrderScopeQuery()->max('sort_order')) + 1,
            );
        });

        static::deleted(function (Model $model): void {
            $sortOrder = $model->getAttribute('sort_order');

            if ($sortOrder === null) {
                return;
            }

            $model->sortOrderScopeQuery(useOriginalScope: true)
                ->where('sort_order', '>', $sortOrder)
                ->decrement('sort_order');
        });
    }

    protected function sortOrderScopeQuery(bool $useOriginalScope = false)
    {
        $query = static::query();

        foreach ($this->sortOrderScopeColumns() as $column) {
            $value = $useOriginalScope ? $this->getOriginal($column) : $this->getAttribute($column);

            if ($value === null) {
                $query->whereNull($column);
            } else {
                $query->where($column, $value);
            }
        }

        if ($this->exists) {
            $query->whereKeyNot($this->getKey());
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    protected function sortOrderScopeColumns(): array
    {
        return [];
    }
}
