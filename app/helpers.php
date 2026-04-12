<?php

use App\Support\ActivityShim;

if (! function_exists('activity')) {
    function activity(?string $logName = null): ActivityShim
    {
        return new ActivityShim($logName);
    }
}
