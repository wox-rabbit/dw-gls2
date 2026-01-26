<?php

namespace App\Models;

use App\Traits\HasEtc;
use Illuminate\Database\Eloquent\Model;

class Simcard extends Model
{
    protected $fillable = [
        'iccid', 'provider', 'label', 'imei', 'ip'
    ];

    public $timestamps = false;
}
