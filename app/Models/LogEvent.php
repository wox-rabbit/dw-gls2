<?php

namespace App\Models;

use App\Traits\HasEtc;
use Illuminate\Database\Eloquent\Model;

class LogEvent extends Model
{
    public $timestamps = false;

    public $fillable = ['sensor', 'sn', 'type', 'time', 'value'];

}
