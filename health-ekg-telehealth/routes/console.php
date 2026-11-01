<?php

use App\Console\Commands\CheckDataGapsCommand;
use App\Console\Commands\CollectServerHealthCommand;
use Illuminate\Support\Facades\Schedule;

// Pemeriksaan jeda data: setiap 5 menit.
Schedule::command(CheckDataGapsCommand::class)->everyFiveMinutes();

// Pengumpul metrik server: setiap menit (menjadi heartbeat scheduler juga).
Schedule::command(CollectServerHealthCommand::class)->everyMinute();
