<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Fase 5.3: un giro al giorno basta — il preavviso è espresso in giorni
// (config('timone.deadline_reminder_days_before')), non in ore, quindi non
// serve una cadenza più fitta. Richiede il servizio `scheduler` in
// docker-compose.yml (php artisan schedule:work) per ticchettare davvero in
// produzione: senza, questo resta codice morto, mai eseguito.
Schedule::command('timone:send-deadline-reminders')->dailyAt('07:30');

// Annulla/ripeti (2026-10-28): cronologia oltre 24 ore e contenuti nel cestino.
Schedule::command('timone:history-prune')->hourly();
