<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\Dashboard\DealsController;
use App\Http\Controllers\Api\Dashboard\TasksController;
use App\Http\Controllers\Api\Dashboard\ContactsController;
use App\Http\Controllers\Api\Dashboard\CampaignsController;
use App\Http\Controllers\Api\ContactsController as ApiContactsController;
use App\Http\Controllers\Api\CompaniesController;
use App\Http\Controllers\Api\MetadataController;
use App\Http\Controllers\Api\PipelinesController;
use App\Http\Controllers\Api\StagesController;
use App\Http\Controllers\Api\ActivitiesController;
use App\Http\Controllers\Api\CampaignWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::get('verify', [AuthController::class, 'verifyEmail'])->name('auth.verify')->middleware('signed');
    Route::post('forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('reset', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
});

Route::middleware(['auth:sanctum'])->group(function () {
    // Aggregated dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);

    // Per-widget endpoints
    Route::get('deals/summary', [DealsController::class, 'summary']);
    Route::get('tasks/today', [TasksController::class, 'today']);
    Route::get('contacts/recent', [ContactsController::class, 'recent']);
    Route::get('campaigns/metrics', [CampaignsController::class, 'metrics']);

    // Contacts resource (place search BEFORE {id} and constrain {id} numeric)
    Route::get('contacts/search', [ApiContactsController::class, 'search']);
    Route::get('contacts', [ApiContactsController::class, 'index']);
    Route::post('contacts', [ApiContactsController::class, 'store']);
    Route::get('contacts/{id}', [ApiContactsController::class, 'show'])->whereNumber('id');
    Route::put('contacts/{id}', [ApiContactsController::class, 'update'])->whereNumber('id');
    Route::delete('contacts/{id}', [ApiContactsController::class, 'destroy'])->whereNumber('id');
    Route::post('contacts/import', [ApiContactsController::class, 'import']);

    // Companies resource
    Route::get('companies', [CompaniesController::class, 'index']);
    Route::post('companies', [CompaniesController::class, 'store']);
    Route::post('companies/check-duplicates', [CompaniesController::class, 'checkDuplicates']);
    Route::post('companies/bulk-create', [CompaniesController::class, 'bulkCreate']);
    Route::post('companies/import', [CompaniesController::class, 'import']);
    Route::get('companies/deleted', [CompaniesController::class, 'deleted']);
    Route::get('companies/{id}', [CompaniesController::class, 'show'])->whereNumber('id');
    Route::put('companies/{id}', [CompaniesController::class, 'update'])->whereNumber('id');
    Route::delete('companies/{id}', [CompaniesController::class, 'destroy'])->whereNumber('id');
    Route::post('companies/{id}/restore', [CompaniesController::class, 'restore'])->whereNumber('id');
    Route::get('companies/{id}/contacts', [CompaniesController::class, 'getCompanyContacts'])->whereNumber('id');
    Route::post('companies/{id}/contacts', [CompaniesController::class, 'attachContacts'])->whereNumber('id');
    Route::delete('companies/{id}/contacts/{contact_id}', [CompaniesController::class, 'detachContact'])->whereNumber(['id', 'contact_id']);
    Route::get('companies/{id}/activity-log', [CompaniesController::class, 'activityLog'])->whereNumber('id');

    // Metadata endpoints
    Route::get('metadata/industries', [MetadataController::class, 'industries']);
    Route::get('metadata/company-types', [MetadataController::class, 'companyTypes']);
    Route::get('metadata/owners', [MetadataController::class, 'owners']);

    // Deals resource
    Route::get('deals', [\App\Http\Controllers\Api\DealsController::class, 'index']);
    Route::post('deals', [\App\Http\Controllers\Api\DealsController::class, 'store']);
    Route::get('deals/summary', [\App\Http\Controllers\Api\DealsController::class, 'summary']);
    Route::get('deals/{id}', [\App\Http\Controllers\Api\DealsController::class, 'show'])->whereNumber('id');
    Route::put('deals/{id}', [\App\Http\Controllers\Api\DealsController::class, 'update'])->whereNumber('id');
    Route::delete('deals/{id}', [\App\Http\Controllers\Api\DealsController::class, 'destroy'])->whereNumber('id');
    Route::post('deals/{id}/move', [\App\Http\Controllers\Api\DealsController::class, 'move'])->whereNumber('id');

    // Pipelines resource
    Route::get('pipelines', [PipelinesController::class, 'index']);
    Route::post('pipelines', [PipelinesController::class, 'store']);
    Route::get('pipelines/{id}', [PipelinesController::class, 'show'])->whereNumber('id');
    Route::put('pipelines/{id}', [PipelinesController::class, 'update'])->whereNumber('id');
    Route::delete('pipelines/{id}', [PipelinesController::class, 'destroy'])->whereNumber('id');

    // Stages resource
    Route::get('stages', [StagesController::class, 'index']);
    Route::post('stages', [StagesController::class, 'store']);
    Route::get('stages/{id}', [StagesController::class, 'show'])->whereNumber('id');
    Route::put('stages/{id}', [StagesController::class, 'update'])->whereNumber('id');
    Route::delete('stages/{id}', [StagesController::class, 'destroy'])->whereNumber('id');

    // Activities resource
    Route::get('activities', [ActivitiesController::class, 'index']);
    Route::post('activities', [ActivitiesController::class, 'store']);
    Route::get('activities/{id}', [ActivitiesController::class, 'show'])->whereNumber('id');
    Route::put('activities/{id}', [ActivitiesController::class, 'update'])->whereNumber('id');
    Route::delete('activities/{id}', [ActivitiesController::class, 'destroy'])->whereNumber('id');

    // Tasks resource
    Route::get('tasks', [\App\Http\Controllers\Api\TasksController::class, 'index']);
    Route::post('tasks', [\App\Http\Controllers\Api\TasksController::class, 'store']);
    Route::get('tasks/{id}', [\App\Http\Controllers\Api\TasksController::class, 'show'])->whereNumber('id');
    Route::put('tasks/{id}', [\App\Http\Controllers\Api\TasksController::class, 'update'])->whereNumber('id');
    Route::delete('tasks/{id}', [\App\Http\Controllers\Api\TasksController::class, 'destroy'])->whereNumber('id');
    Route::post('tasks/{id}/complete', [\App\Http\Controllers\Api\TasksController::class, 'complete'])->whereNumber('id');

    // Campaigns resource
    Route::get('campaigns', [\App\Http\Controllers\Api\CampaignsController::class, 'index']);
    Route::post('campaigns', [\App\Http\Controllers\Api\CampaignsController::class, 'store']);
    Route::get('campaigns/{id}', [\App\Http\Controllers\Api\CampaignsController::class, 'show'])->whereNumber('id');
    Route::put('campaigns/{id}', [\App\Http\Controllers\Api\CampaignsController::class, 'update'])->whereNumber('id');
    Route::delete('campaigns/{id}', [\App\Http\Controllers\Api\CampaignsController::class, 'destroy'])->whereNumber('id');
    Route::post('campaigns/{id}/send', [\App\Http\Controllers\Api\CampaignsController::class, 'send'])->whereNumber('id');
    Route::get('campaigns/{id}/metrics', [\App\Http\Controllers\Api\CampaignsController::class, 'metrics'])->whereNumber('id');

    // Campaign webhook (no auth required)
    Route::post('campaigns/events', [CampaignWebhookController::class, 'handleEvents']);
});


