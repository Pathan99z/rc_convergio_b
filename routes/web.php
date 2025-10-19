<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\UnsubscribeController;
use App\Http\Controllers\PublicSurveyController;

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
// Public Commerce Routes (No Auth Required)
Route::prefix('commerce')->group(function () {
    // Public checkout page for payment links
    Route::get('/checkout/{paymentLinkId}', [\App\Http\Controllers\PublicCommerceController::class, 'checkout'])
        ->name('commerce.checkout')
        ->where('paymentLinkId', '[0-9]+');
    
    // Process payment (for test mode)
    Route::post('/checkout/{paymentLinkId}/process', [\App\Http\Controllers\PublicCommerceController::class, 'processPayment'])
        ->name('commerce.checkout.process')
        ->where('paymentLinkId', '[0-9]+');
    
    // Demo subscription checkout page
    Route::get('/subscription-checkout/demo', [\App\Http\Controllers\PublicCommerceController::class, 'subscriptionCheckoutDemo'])
        ->name('commerce.subscription.checkout.demo');
    
    // Process demo subscription payment
    Route::post('/subscription-checkout/demo/process', [\App\Http\Controllers\PublicCommerceController::class, 'processSubscriptionDemo'])
        ->name('commerce.subscription.checkout.demo.process');
    
    // Demo billing portal
    Route::get('/subscription-portal/demo/{subscriptionId}', [\App\Http\Controllers\PublicCommerceController::class, 'demoBillingPortal'])
        ->name('commerce.subscription.portal.demo');
    
    // Demo subscription management actions
    Route::post('/subscription-portal/demo/{subscriptionId}/cancel', [\App\Http\Controllers\PublicCommerceController::class, 'demoCancelSubscription'])
        ->name('commerce.subscription.portal.demo.cancel');
    
    Route::post('/subscription-portal/demo/{subscriptionId}/change-plan', [\App\Http\Controllers\PublicCommerceController::class, 'demoChangePlan'])
        ->name('commerce.subscription.portal.demo.change-plan');
    
    // Public invoice routes (no authentication required)
    Route::get('/invoices/{invoiceId}/view', [\App\Http\Controllers\PublicCommerceController::class, 'viewInvoice'])
        ->name('commerce.invoices.view')
        ->where('invoiceId', '[0-9]+');
    
    Route::get('/invoices/{invoiceId}/download', [\App\Http\Controllers\PublicCommerceController::class, 'downloadInvoice'])
        ->name('commerce.invoices.download')
        ->where('invoiceId', '[0-9]+');
});

// Public Survey Routes (No Auth Required)
Route::get('/survey/{id}', [PublicSurveyController::class, 'show'])
    ->name('public.survey')
    ->where('id', '[0-9]+');

Route::post('/survey/{id}/submit', [PublicSurveyController::class, 'submit'])
    ->name('public.survey.submit')
    ->where('id', '[0-9]+');


