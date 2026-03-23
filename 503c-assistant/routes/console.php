<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('irb:retention-prune')
    ->daily()
    ->at('03:00')
    ->withoutOverlapping()
    ->runInBackground();
