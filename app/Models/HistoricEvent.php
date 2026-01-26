<?php

namespace App\Models;

use App\Traits\HasEtc;
use Illuminate\Database\Eloquent\Model;

class HistoricEvent extends Model
{
    public $timestamps = false;

    public $fillable = ['sensor', 'sn', 'type', 'date', 'value'];
}
