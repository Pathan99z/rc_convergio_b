<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\UnsubscribeController;

Route::get('/', function () {
    return view('welcome');
});

// Email Campaign Tracking Routes (Public - No Auth Required)
Route::get('/track/open/{recipientId}', [TrackingController::class, 'open'])
    ->name('track.open')
    ->where('recipientId', '[0-9]+');

Route::get('/track/click/{recipientId}', [TrackingController::class, 'click'])
    ->name('track.click')
    ->where('recipientId', '[0-9]+');

// Simple RSVP Page Route
Route::get('/rsvp/{eventId}', [\App\Http\Controllers\Api\PublicEventController::class, 'showRsvpPage'])
    ->name('rsvp.page')
    ->where('eventId', '[0-9]+');

