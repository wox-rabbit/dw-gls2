<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * App\Models\LogEvent2
 *
 * NOTE:
 * - This table is partitioned by time_bucket (weekly).
 * - No foreign key constraints (MySQL limitation with partitioning).
 * - Canonical sensor identity is sensor_id (maps to sensors.id).
 *
 * @property int $sensor_id Sensor ID (maps to sensors.id)
 * @property string $type 3-character measurement type identifier
 * @property float|string $value Measured value (decimal stored as string by PDO)
 * @property Carbon $time_bucket Bucket timestamp (UTC, rounded to 5-minute bucket)
 * @property Carbon|null $time_true Exact timestamp (UTC), optional
 *
 * @method static Builder|LogEvent2 forSensor(int $sensorId)
 * @method static Builder|LogEvent2 forType(string $type)
 * @method static Builder|LogEvent2 betweenBuckets(Carbon|string $from, Carbon|string $to)
 */
class LogEvent2 extends Model
{
    protected $table = 'log_events2';

    /**
     * Composite primary key:
     * (sensor_id, time_bucket, type)
     *
     * Eloquent doesn't support composite PKs natively,
     * so we disable incrementing and manage keys manually.
     */
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    /**
     * Attributes that are mass assignable.
     */
    protected $fillable = [
        'sensor_id',
        'type',
        'value',
        'time_bucket',
        'time_true',
    ];

    /**
     * Attribute casting.
     */
    protected $casts = [
        'sensor_id'  => 'integer',
        'time_bucket'=> 'datetime',
        'time_true'  => 'datetime',
        // value is decimal -> keep as string to avoid float precision loss
    ];

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope to a single sensor.
     */
    public function scopeForSensor(Builder $query, int $sensorId): Builder
    {
        return $query->where('sensor_id', $sensorId);
    }

    /**
     * Scope to a single measurement type.
     */
    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to a time_bucket range.
     *
     * IMPORTANT:
     * Always include a time range so partition pruning kicks in.
     */
    public function scopeBetweenBuckets(
        Builder $query,
        Carbon|string $from,
        Carbon|string $to
    ): Builder {
        return $query
            ->where('time_bucket', '>=', $from)
            ->where('time_bucket', '<', $to);
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Sensor relationship (no FK constraint at DB level).
     */
    public function sensor()
    {
        return $this->belongsTo(Sensor::class, 'sensor_id');
    }
}
