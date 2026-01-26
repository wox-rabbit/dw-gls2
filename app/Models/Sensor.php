<?php

namespace App\Models;

use App\Traits\HasEtc;
use Illuminate\Database\Eloquent\Model;

class Sensor extends Model
{

    use HasEtc;

    protected $fillable = ['sn', 'deviceType', 'registrationIP', 'etc'];

    public function historic_events()
    {
        return $this->hasMany(HistoricEvent::class, 'sensor', 'id');
    }

    public function log_events()
    {
        return $this->hasMany(HistoricEvent::class, 'sensor', 'id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}
