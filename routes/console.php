<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('partitions:ensure-log-events2 --weeks=4')
    ->sundays()
    ->at('03:15')
    ->timezone('Europe/Amsterdam')
    ->withoutOverlapping();