<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule; // Importante añadir esta línea

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// PROGRAMACIÓN DE TU COMANDO
// Se ejecutará todos los días a las 12:01 AM
Schedule::command('lease:verify-payments')->dailyAt('00:01');