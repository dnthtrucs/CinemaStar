<?php

namespace App\Observers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class CatalogAuditObserver
{
    private const FIELDS = ['title', 'name', 'status', 'is_active', 'room_id', 'cinema_id', 'movie_id', 'starts_at', 'ends_at', 'base_price', 'format', 'duration', 'release_date', 'end_date', 'code', 'type', 'value', 'min_order', 'usage_limit', 'price', 'rows', 'seats_per_row', 'is_accessible'];

    public function created(Model $model): void
    {
        $this->record($model, 'created', [], $model->only(self::FIELDS));
    }

    public function updated(Model $model): void
    {
        $fields = array_intersect(array_keys($model->getChanges()), self::FIELDS);
        if (! $fields) {
            return;
        }
        $before = [];
        foreach ($fields as $field) {
            $before[$field] = $model->getRawOriginal($field);
        }
        $this->record($model, 'updated', $before, $model->only($fields));
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', $model->only(self::FIELDS), []);
    }

    private function record(Model $model, string $action, array $before, array $after): void
    {
        if (! auth()->user()?->isAdmin()) {
            return;
        }
        ActivityLog::record('catalog.'.$action, class_basename($model).' #'.$model->id, $model, ['before' => $before, 'after' => $after]);
    }
}
