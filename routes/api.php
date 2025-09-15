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
use App\Http\Controllers\Api\EnrichmentController;
use App\Http\Controllers\Api\FormsController;
use App\Http\Controllers\Api\PublicFormController;
use App\Http\Controllers\Api\ListsController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\UsersController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\FeatureStatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::get('verify', [AuthController::class, 'verifyEmail'])->name('auth.verify')->middleware('signed');
    Route::post('forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('reset', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::post('resend-verification', [AuthController::class, 'resendVerificationEmail'])->middleware('throttle:3,1');
});

Route::middleware(['auth:sanctum'])->group(function () {
    // Aggregated dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);

    // Per-widget endpoints
    Route::get('deals/summary', [DealsController::class, 'summary']);
    Route::get('tasks/today', [TasksController::class, 'today']);
    Route::get('contacts/recent', [ContactsController::class, 'recent']);
    Route::get('campaigns/metrics', [CampaignsController::class, 'metrics']);

    // Email verification required for sensitive operations
    Route::middleware(['verified'])->group(function () {
        // Campaign operations (sending, etc.)
        Route::post('campaigns/{id}/send', [CampaignsController::class, 'send'])->whereNumber('id');
        
        // Bulk operations
        Route::post('contacts/import', [ApiContactsController::class, 'import']);
        Route::post('companies/import', [CompaniesController::class, 'import']);
        Route::post('companies/bulk-create', [CompaniesController::class, 'bulkCreate']);
        
        // Data export operations
        Route::get('deals/export', [\App\Http\Controllers\Api\DealsController::class, 'export']);
        Route::get('activities/export', [ActivitiesController::class, 'export']);
        
        // API integrations and advanced features
        Route::get('companies/enrich', [EnrichmentController::class, 'enrich']);
    });

    // Contacts resource (place search BEFORE {id} and constrain {id} numeric)
    Route::get('contacts/search', [ApiContactsController::class, 'search']);
    Route::get('contacts', [ApiContactsController::class, 'index']);
    Route::post('contacts', [ApiContactsController::class, 'store']);
    Route::get('contacts/{id}', [ApiContactsController::class, 'show'])->whereNumber('id');
    Route::put('contacts/{id}', [ApiContactsController::class, 'update'])->whereNumber('id');
    Route::delete('contacts/{id}', [ApiContactsController::class, 'destroy'])->whereNumber('id');
    Route::post('contacts/import', [ApiContactsController::class, 'import']);
    // Contact detail page APIs
    Route::get('contacts/{id}/company', [ApiContactsController::class, 'getCompany'])->whereNumber('id');
    Route::get('contacts/{id}/deals', [ApiContactsController::class, 'getDeals'])->whereNumber('id');
    Route::get('contacts/{id}/activities', [ApiContactsController::class, 'getActivities'])->whereNumber('id');

    // Companies resource
    Route::get('companies', [CompaniesController::class, 'index']);
    Route::post('companies', [CompaniesController::class, 'store']);
    Route::post('companies/check-duplicates', [CompaniesController::class, 'checkDuplicates']);
    Route::post('companies/bulk-create', [CompaniesController::class, 'bulkCreate']);
    Route::post('companies/import', [CompaniesController::class, 'import']);
    Route::get('companies/deleted', [CompaniesController::class, 'deleted']);
    Route::get('companies/enrich', [EnrichmentController::class, 'enrich']);
    Route::get('companies/{id}', [CompaniesController::class, 'show'])->whereNumber('id');
    Route::put('companies/{id}', [CompaniesController::class, 'update'])->whereNumber('id');
    Route::delete('companies/{id}', [CompaniesController::class, 'destroy'])->whereNumber('id');
    Route::post('companies/{id}/restore', [CompaniesController::class, 'restore'])->whereNumber('id');
    Route::get('companies/{id}/contacts', [CompaniesController::class, 'getCompanyContacts'])->whereNumber('id');
    Route::post('companies/{id}/contacts', [CompaniesController::class, 'attachContacts'])->whereNumber('id');
    Route::delete('companies/{id}/contacts/{contact_id}', [CompaniesController::class, 'detachContact'])->whereNumber(['id', 'contact_id']);
    Route::get('companies/{id}/activity-log', [CompaniesController::class, 'activityLog'])->whereNumber('id');
    // Company detail page APIs
    Route::get('companies/{id}/deals', [CompaniesController::class, 'getDeals'])->whereNumber('id');
    Route::post('companies/{id}/contacts/bulk', [CompaniesController::class, 'bulkAttachContacts'])->whereNumber('id');
    Route::delete('companies/{id}/contacts/bulk', [CompaniesController::class, 'bulkDetachContacts'])->whereNumber('id');
    Route::get('companies/{companyId}/contacts/{contactId}/exists', [CompaniesController::class, 'checkContactLinked'])->whereNumber(['companyId', 'contactId']);

    // Metadata endpoints
    Route::get('metadata/industries', [MetadataController::class, 'industries']);
    Route::get('metadata/company-types', [MetadataController::class, 'companyTypes']);
    Route::get('metadata/owners', [MetadataController::class, 'owners']);

    // Deals resource
    Route::get('deals', [\App\Http\Controllers\Api\DealsController::class, 'index']);
    Route::post('deals', [\App\Http\Controllers\Api\DealsController::class, 'store']);
    Route::get('deals/summary', [\App\Http\Controllers\Api\DealsController::class, 'summary']);
    Route::get('deals/export', [\App\Http\Controllers\Api\DealsController::class, 'export']);
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
    Route::get('pipelines/{id}/stages', [PipelinesController::class, 'stages'])->whereNumber('id');
    Route::get('pipelines/{id}/kanban', [PipelinesController::class, 'kanban'])->whereNumber('id');

    // Stages resource
    Route::get('stages', [StagesController::class, 'index']);
    Route::post('stages', [StagesController::class, 'store']);
    Route::get('stages/{id}', [StagesController::class, 'show'])->whereNumber('id');
    Route::put('stages/{id}', [StagesController::class, 'update'])->whereNumber('id');
    Route::delete('stages/{id}', [StagesController::class, 'destroy'])->whereNumber('id');

    // Activities resource
    Route::get('activities', [ActivitiesController::class, 'index']);
    Route::post('activities', [ActivitiesController::class, 'store']);
    Route::get('activities/timeline', [ActivitiesController::class, 'timeline']);
    Route::get('activities/upcoming', [ActivitiesController::class, 'upcoming']);
    Route::get('activities/search', [ActivitiesController::class, 'search']);
    Route::get('activities/stats', [ActivitiesController::class, 'stats']);
    Route::get('activities/metrics', [ActivitiesController::class, 'metrics']);
    Route::get('activities/export', [ActivitiesController::class, 'export']);
    Route::patch('activities/bulk-update', [ActivitiesController::class, 'bulkUpdate']);
    Route::post('activities/bulk-complete', [ActivitiesController::class, 'bulkComplete']);
    Route::delete('activities/bulk-delete', [ActivitiesController::class, 'bulkDelete']);
    Route::get('activities/{entityType}/{entityId}', [ActivitiesController::class, 'entityActivities'])->whereNumber('entityId');
    Route::get('activities/{id}', [ActivitiesController::class, 'show'])->whereNumber('id');
    Route::put('activities/{id}', [ActivitiesController::class, 'update'])->whereNumber('id');
    Route::delete('activities/{id}', [ActivitiesController::class, 'destroy'])->whereNumber('id');
    Route::patch('activities/{id}/complete', [ActivitiesController::class, 'complete'])->whereNumber('id');

    // Tasks resource
    Route::get('tasks', [\App\Http\Controllers\Api\TasksController::class, 'index']);
    Route::post('tasks', [\App\Http\Controllers\Api\TasksController::class, 'store']);
    Route::get('tasks/assignee/{assigneeId}', [\App\Http\Controllers\Api\TasksController::class, 'assigneeTasks'])->whereNumber('assigneeId');
    Route::get('tasks/owner/{ownerId}', [\App\Http\Controllers\Api\TasksController::class, 'ownerTasks'])->whereNumber('ownerId');
    Route::get('tasks/overdue', [\App\Http\Controllers\Api\TasksController::class, 'overdue']);
    Route::get('tasks/upcoming', [\App\Http\Controllers\Api\TasksController::class, 'upcoming']);
    Route::patch('tasks/bulk-update', [\App\Http\Controllers\Api\TasksController::class, 'bulkUpdate']);
    Route::post('tasks/bulk-complete', [\App\Http\Controllers\Api\TasksController::class, 'bulkComplete']);
    Route::get('tasks/{id}', [\App\Http\Controllers\Api\TasksController::class, 'show'])->whereNumber('id');
    Route::put('tasks/{id}', [\App\Http\Controllers\Api\TasksController::class, 'update'])->whereNumber('id');
    Route::delete('tasks/{id}', [\App\Http\Controllers\Api\TasksController::class, 'destroy'])->whereNumber('id');
    Route::post('tasks/{id}/complete', [\App\Http\Controllers\Api\TasksController::class, 'complete'])->whereNumber('id');

    // Campaigns resource
    Route::get('campaigns', [\App\Http\Controllers\Api\CampaignsController::class, 'index']);
    Route::post('campaigns', [\App\Http\Controllers\Api\CampaignsController::class, 'store']);
    Route::get('campaigns/templates', [\App\Http\Controllers\Api\CampaignsController::class, 'templates']);
    Route::get('campaigns/{id}', [\App\Http\Controllers\Api\CampaignsController::class, 'show'])->whereNumber('id');
    Route::patch('campaigns/{id}', [\App\Http\Controllers\Api\CampaignsController::class, 'update'])->whereNumber('id');
    Route::put('campaigns/{id}', [\App\Http\Controllers\Api\CampaignsController::class, 'update'])->whereNumber('id');
    Route::delete('campaigns/{id}', [\App\Http\Controllers\Api\CampaignsController::class, 'destroy'])->whereNumber('id');
    Route::post('campaigns/{id}/send', [\App\Http\Controllers\Api\CampaignsController::class, 'send'])->whereNumber('id');
    Route::post('campaigns/{id}/pause', [\App\Http\Controllers\Api\CampaignsController::class, 'pause'])->whereNumber('id');
    Route::post('campaigns/{id}/resume', [\App\Http\Controllers\Api\CampaignsController::class, 'resume'])->whereNumber('id');
    Route::post('campaigns/{id}/duplicate', [\App\Http\Controllers\Api\CampaignsController::class, 'duplicate'])->whereNumber('id');
    Route::get('campaigns/{id}/recipients', [\App\Http\Controllers\Api\CampaignsController::class, 'recipients'])->whereNumber('id');
    Route::post('campaigns/{id}/recipients', [\App\Http\Controllers\Api\CampaignsController::class, 'addRecipients'])->whereNumber('id');
    Route::delete('campaigns/{id}/recipients', [\App\Http\Controllers\Api\CampaignsController::class, 'removeRecipients'])->whereNumber('id');
    Route::get('campaigns/{id}/metrics', [\App\Http\Controllers\Api\CampaignsController::class, 'metrics'])->whereNumber('id');

    // Campaign Automations
    Route::get('campaigns/{id}/automations', [\App\Http\Controllers\Api\CampaignAutomationController::class, 'index'])->whereNumber('id');
    Route::post('campaigns/{id}/automations', [\App\Http\Controllers\Api\CampaignAutomationController::class, 'store'])->whereNumber('id');
    Route::delete('campaigns/automations/{automationId}', [\App\Http\Controllers\Api\CampaignAutomationController::class, 'destroy'])->whereNumber('automationId');
    Route::get('campaigns/automations/options', [\App\Http\Controllers\Api\CampaignAutomationController::class, 'options']);

    // Ad Campaigns
    Route::post('campaigns/{id}/ads', [\App\Http\Controllers\Api\CampaignsController::class, 'createAd'])->whereNumber('id');
    Route::get('campaigns/{id}/ads-metrics', [\App\Http\Controllers\Api\CampaignsController::class, 'getAdMetrics'])->whereNumber('id');

    // Ad Accounts
    Route::get('ad-accounts', [\App\Http\Controllers\Api\AdAccountsController::class, 'index']);
    Route::post('ad-accounts', [\App\Http\Controllers\Api\AdAccountsController::class, 'store']);
    Route::put('ad-accounts/{id}', [\App\Http\Controllers\Api\AdAccountsController::class, 'update'])->whereNumber('id');
    Route::delete('ad-accounts/{id}', [\App\Http\Controllers\Api\AdAccountsController::class, 'destroy'])->whereNumber('id');
    Route::get('ad-accounts/providers', [\App\Http\Controllers\Api\AdAccountsController::class, 'providers']);

    // Events
    Route::get('events', [\App\Http\Controllers\Api\EventsController::class, 'index']);
    Route::post('events', [\App\Http\Controllers\Api\EventsController::class, 'store']);
    Route::get('events/{id}', [\App\Http\Controllers\Api\EventsController::class, 'show'])->whereNumber('id');
    Route::put('events/{id}', [\App\Http\Controllers\Api\EventsController::class, 'update'])->whereNumber('id');
    Route::delete('events/{id}', [\App\Http\Controllers\Api\EventsController::class, 'destroy'])->whereNumber('id');
    Route::post('events/{id}/attendees', [\App\Http\Controllers\Api\EventsController::class, 'addAttendee'])->whereNumber('id');
    Route::get('events/{id}/attendees', [\App\Http\Controllers\Api\EventsController::class, 'getAttendees'])->whereNumber('id');
    Route::post('events/{eventId}/attendees/{attendeeId}/attended', [\App\Http\Controllers\Api\EventsController::class, 'markAttended'])->whereNumber(['eventId', 'attendeeId']);
    Route::get('events/types', [\App\Http\Controllers\Api\EventsController::class, 'getEventTypes']);
    Route::get('events/rsvp-statuses', [\App\Http\Controllers\Api\EventsController::class, 'getRsvpStatuses']);

    // Visitor Intent Tracking
    Route::post('tracking/events', [\App\Http\Controllers\Api\TrackingController::class, 'logEvent']);
    Route::get('tracking/intent', [\App\Http\Controllers\Api\TrackingController::class, 'getIntentSignals']);
    Route::get('tracking/analytics', [\App\Http\Controllers\Api\TrackingController::class, 'getIntentAnalytics']);
    Route::get('tracking/actions', [\App\Http\Controllers\Api\TrackingController::class, 'getAvailableActions']);
    Route::get('tracking/intent-levels', [\App\Http\Controllers\Api\TrackingController::class, 'getIntentLevels']);

    // Lead Scoring Rules
    Route::get('lead-scoring/rules', [\App\Http\Controllers\Api\LeadScoringController::class, 'getRules']);
    Route::post('lead-scoring/rules', [\App\Http\Controllers\Api\LeadScoringController::class, 'createRule']);
    Route::put('lead-scoring/rules/{id}', [\App\Http\Controllers\Api\LeadScoringController::class, 'updateRule'])->whereNumber('id');
    Route::delete('lead-scoring/rules/{id}', [\App\Http\Controllers\Api\LeadScoringController::class, 'deleteRule'])->whereNumber('id');
    Route::post('lead-scoring/recalculate/{contactId}', [\App\Http\Controllers\Api\LeadScoringController::class, 'recalculateContactScore'])->whereNumber('contactId');
    Route::get('lead-scoring/stats', [\App\Http\Controllers\Api\LeadScoringController::class, 'getStats']);
    Route::get('lead-scoring/top-contacts', [\App\Http\Controllers\Api\LeadScoringController::class, 'getTopScoringContacts']);
    Route::get('lead-scoring/event-types', [\App\Http\Controllers\Api\LeadScoringController::class, 'getEventTypes']);
    Route::get('lead-scoring/operators', [\App\Http\Controllers\Api\LeadScoringController::class, 'getOperators']);

    // Customer Journey Workflows
    Route::get('journeys', [\App\Http\Controllers\Api\JourneysController::class, 'index']);
    Route::post('journeys', [\App\Http\Controllers\Api\JourneysController::class, 'store']);
    Route::get('journeys/{id}', [\App\Http\Controllers\Api\JourneysController::class, 'show'])->whereNumber('id');
    Route::put('journeys/{id}', [\App\Http\Controllers\Api\JourneysController::class, 'update'])->whereNumber('id');
    Route::delete('journeys/{id}', [\App\Http\Controllers\Api\JourneysController::class, 'destroy'])->whereNumber('id');
    Route::post('journeys/{journeyId}/run/{contactId}', [\App\Http\Controllers\Api\JourneysController::class, 'runForContact'])->whereNumber(['journeyId', 'contactId']);
    Route::get('journeys/{id}/executions', [\App\Http\Controllers\Api\JourneysController::class, 'getExecutions'])->whereNumber('id');
    Route::get('journeys/statuses', [\App\Http\Controllers\Api\JourneysController::class, 'getStatuses']);
    Route::get('journeys/step-types', [\App\Http\Controllers\Api\JourneysController::class, 'getStepTypes']);
    Route::get('journeys/step-schema', [\App\Http\Controllers\Api\JourneysController::class, 'getStepTypeSchema']);

    // Sales Forecast
    Route::get('forecast', [\App\Http\Controllers\Api\ForecastController::class, 'index']);
    Route::get('forecast/multi-timeframe', [\App\Http\Controllers\Api\ForecastController::class, 'multiTimeframe']);
    Route::get('forecast/trends', [\App\Http\Controllers\Api\ForecastController::class, 'trends']);
    Route::get('forecast/by-pipeline', [\App\Http\Controllers\Api\ForecastController::class, 'byPipeline']);
    Route::get('forecast/accuracy', [\App\Http\Controllers\Api\ForecastController::class, 'accuracy']);
    Route::get('forecast/timeframes', [\App\Http\Controllers\Api\ForecastController::class, 'timeframes']);

    // Meetings
    Route::get('meetings', [\App\Http\Controllers\Api\MeetingsController::class, 'index']);
    Route::post('meetings', [\App\Http\Controllers\Api\MeetingsController::class, 'store']);
    Route::post('meetings/sync/google', [\App\Http\Controllers\Api\MeetingsController::class, 'syncGoogle']);
    Route::post('meetings/sync/outlook', [\App\Http\Controllers\Api\MeetingsController::class, 'syncOutlook']);
    Route::get('meetings/statuses', [\App\Http\Controllers\Api\MeetingsController::class, 'getStatuses']);
    Route::get('meetings/providers', [\App\Http\Controllers\Api\MeetingsController::class, 'getProviders']);

    // Analytics Dashboard
    Route::get('analytics/dashboard', [\App\Http\Controllers\Api\AnalyticsController::class, 'dashboard']);
    Route::get('analytics/modules', [\App\Http\Controllers\Api\AnalyticsController::class, 'modules']);
    Route::get('analytics/periods', [\App\Http\Controllers\Api\AnalyticsController::class, 'periods']);
    Route::get('analytics/{module}', [\App\Http\Controllers\Api\AnalyticsController::class, 'module']);

    // Campaign webhook (no auth required)
    Route::post('campaigns/events', [CampaignWebhookController::class, 'handleEvents']);

    // Forms resource
    Route::get('forms', [FormsController::class, 'index']);
    Route::post('forms', [FormsController::class, 'store']);
    Route::get('forms/check-duplicate', [FormsController::class, 'checkDuplicate']);
    Route::get('forms/{form}', [FormsController::class, 'show'])->whereNumber('form');
    Route::put('forms/{form}', [FormsController::class, 'update'])->whereNumber('form');
    Route::delete('forms/{form}', [FormsController::class, 'destroy'])->whereNumber('form');
    Route::get('forms/{form}/submissions', [FormsController::class, 'submissions'])->whereNumber('form');
    Route::get('forms/{form}/submissions/{submission}', [FormsController::class, 'showSubmission'])->whereNumber(['form', 'submission']);
    
    // Form settings and field mapping
    Route::get('forms/{form}/settings', [FormsController::class, 'getSettings'])->whereNumber('form');
    Route::put('forms/{form}/settings', [FormsController::class, 'updateSettings'])->whereNumber('form');
    Route::get('forms/{form}/mapping', [FormsController::class, 'getFieldMapping'])->whereNumber('form');
    Route::put('forms/{form}/mapping', [FormsController::class, 'updateFieldMapping'])->whereNumber('form');
    
    // Form submission reprocessing
    Route::post('forms/{form}/submissions/{submissionId}/reprocess', [FormsController::class, 'reprocessSubmission'])->whereNumber(['form', 'submissionId']);

    // Lists resource
    Route::get('lists', [ListsController::class, 'index']);
    Route::post('lists', [ListsController::class, 'store']);
    Route::get('lists/check-duplicate', [ListsController::class, 'checkDuplicate']);
    Route::get('lists/{list}', [ListsController::class, 'show'])->whereNumber('list');
    Route::put('lists/{list}', [ListsController::class, 'update'])->whereNumber('list');
    Route::delete('lists/{list}', [ListsController::class, 'destroy'])->whereNumber('list');
    Route::get('lists/{list}/members', [ListsController::class, 'members'])->whereNumber('list');
    Route::post('lists/{list}/members', [ListsController::class, 'addMembers'])->whereNumber('list');
    Route::delete('lists/{list}/members/{contact_id}', [ListsController::class, 'removeMember'])->whereNumber(['list', 'contact_id']);

    // Global search
    Route::get('search', [SearchController::class, 'search']);

    // Users resource (Admin only)
    Route::get('users/me', [UsersController::class, 'me']);
    Route::get('users', [UsersController::class, 'index']);
    Route::get('users/{id}', [UsersController::class, 'show'])->whereNumber('id');
    Route::post('users', [UsersController::class, 'store']);
    Route::put('users/{id}', [UsersController::class, 'update'])->whereNumber('id');
    Route::delete('users/{id}', [UsersController::class, 'destroy'])->whereNumber('id');

    // Roles resource (for role dropdowns)
    Route::get('roles', [RoleController::class, 'index']);

    // Feature status and restrictions
    Route::get('features/status', [FeatureStatusController::class, 'index']);
    Route::get('features/check/{feature}', [FeatureStatusController::class, 'checkFeature']);

    // Audit logs
    Route::get('audit-logs', [\App\Http\Controllers\Api\AuditLogController::class, 'index']);
});

// Public routes (no auth required)
Route::prefix('public')->group(function () {
    Route::get('forms/{id}', [PublicFormController::class, 'show'])->whereNumber('id');
    Route::post('forms/{id}/submit', [PublicFormController::class, 'submit'])->whereNumber('id');
    // Campaign tracking
    Route::get('campaigns/track/open', [\App\Http\Controllers\Api\CampaignTrackingController::class, 'open'])->name('campaigns.track.open');
    Route::get('campaigns/track/click', [\App\Http\Controllers\Api\CampaignTrackingController::class, 'click'])->name('campaigns.track.click');
    // Campaign unsubscribe
    Route::get('campaigns/unsubscribe/{recipientId}', [\App\Http\Controllers\Api\UnsubscribeController::class, 'unsubscribe'])->name('campaigns.unsubscribe')->where('recipientId', '[0-9]+');
});


