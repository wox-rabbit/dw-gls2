<?php

namespace App\Models;

use App\Traits\HasEtc;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string title
 * @property string config1
 * @property string config2
 * @property string config3
 * @property string config4
 * @property string config5
 * @property string etc
 * @property string date_created
 * @property string date_modified
 */
class Group extends Model
{

    protected $fillable = [
        'title', 'config1', 'config2', 'config3', 'config4', 'config5', 'etc',
    ];

    use HasEtc;

    public function sensors()
    {
        return $this->hasMany(Sensor::class);
    }
}
