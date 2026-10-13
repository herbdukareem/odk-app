<?php

namespace App\Models\Concerns;

use App\Models\OdkWorkspace;
use App\Support\OdkWorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToOdkWorkspace
{
    protected static function bootBelongsToOdkWorkspace(): void
    {
        static::addGlobalScope('odkWorkspace', function (Builder $builder): void {
            $context = app(OdkWorkspaceContext::class);

            if ($context->id() !== null) {
                $builder->where($builder->getModel()->qualifyColumn('workspace_id'), $context->id());
            }
        });

        static::creating(function (Model $model): void {
            $workspaceId = app(OdkWorkspaceContext::class)->id();

            if ($workspaceId !== null && empty($model->workspace_id)) {
                $model->workspace_id = $workspaceId;
            }
        });
    }

    public function workspace()
    {
        return $this->belongsTo(OdkWorkspace::class, 'workspace_id');
    }

    public function scopeForWorkspace(Builder $query, int $workspaceId): Builder
    {
        return $query->withoutGlobalScope('odkWorkspace')->where($query->getModel()->qualifyColumn('workspace_id'), $workspaceId);
    }
}
