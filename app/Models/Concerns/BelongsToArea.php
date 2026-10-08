<?php

namespace App\Models\Concerns;

use App\Models\Area;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;

/**
 * Scopes a model to the signed-in staff member's Area and tags new records with it.
 * Administrators (users without an area_id) see every Area.
 */
trait BelongsToArea
{
    protected static function bootBelongsToArea(): void
    {
        static::addGlobalScope('area', function (Builder $query) {
            $areaId = static::currentStaffAreaId();

            if ($areaId !== null) {
                $query->where($query->qualifyColumn('area_id'), $areaId);
            }
        });

        static::creating(function ($model) {
            $model->area_id ??= static::currentStaffAreaId();
        });
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->area_id = $this->area_id;
    }

    protected static function currentStaffAreaId(): ?int
    {
        // Resolving the user here is safe (User is not area-scoped, so there is no recursion)
        // and guarantees the scope applies even if nothing else has loaded the user yet.
        return Auth::user()?->area_id;
    }
}
