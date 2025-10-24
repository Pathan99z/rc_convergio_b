<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | This file contains feature flags that can be toggled via environment
    | variables. These flags allow for safe deployment of new features
    | and easy rollback if needed.
    |
    */

    'team_access_enabled' => env('TEAM_ACCESS_ENABLED', false),
];


