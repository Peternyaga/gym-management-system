<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('gym:maintenance')->everyMinute()->withoutOverlapping();
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping();
