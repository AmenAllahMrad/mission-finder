<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
/*
|--------------------------------------------------------------------------
| Source polling
|--------------------------------------------------------------------------
*/

Schedule::command('sources:poll-due')
    ->everyMinute()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| MissionFinder Digests
|--------------------------------------------------------------------------
|
| Daily  : tous les jours à 08:00 heure de Tunis
| Weekly : chaque lundi à 08:10 heure de Tunis
|
*/

Schedule::command('alerts:send-digests daily')
    ->dailyAt('08:00')
    ->timezone('Africa/Tunis')
    ->withoutOverlapping();

Schedule::command('alerts:send-digests weekly')
    ->weeklyOn(1, '08:10')
    ->timezone('Africa/Tunis')
    ->withoutOverlapping();