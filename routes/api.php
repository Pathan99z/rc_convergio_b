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
    Route::get('campaigns/metrics/trends', [CampaignsController::class, 'trends']);

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
    Route::post('campaigns/from-template/{templateId}', [\App\Http\Controllers\Api\CampaignsController::class, 'createFromTemplate'])->whereNumber('templateId');
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

    // Campaign Automations (Legacy - Backward Compatible)
    Route::get('campaigns/{id}/automations', [\App\Http\Controllers\Api\CampaignAutomationController::class, 'index'])->whereNumber('id');
    Route::post('campaigns/{id}/automations', [\App\Http\Controllers\Api\CampaignAutomationController::class, 'store'])->whereNumber('id');
    Route::put('campaigns/automations/{id}', [\App\Http\Controllers\Api\CampaignAutomationController::class, 'update'])->whereNumber('id');
    Route::patch('campaigns/automations/{id}/status', [\App\Http\Controllers\Api\CampaignAutomationController::class, 'updateStatus'])->whereNumber('id');
    Route::delete('campaigns/automations/{automationId}', [\App\Http\Controllers\Api\CampaignAutomationController::class, 'destroy'])->whereNumber('automationId');
    Route::get('campaigns/automations/{id}/logs', [\App\Http\Controllers\Api\CampaignAutomationController::class, 'logs'])->whereNumber('id');
    Route::get('campaigns/automations/options', [\App\Http\Controllers\Api\CampaignAutomationController::class, 'options']);
    Route::get('campaigns/automations', [\App\Http\Controllers\Api\CampaignAutomationController::class, 'index']);
    // Independent Email Automations (New Professional System)
    Route::get('automations', [\App\Http\Controllers\Api\AutomationController::class, 'index']);
    Route::post('automations', [\App\Http\Controllers\Api\AutomationController::class, 'store']);
    Route::get('automations/{id}', [\App\Http\Controllers\Api\AutomationController::class, 'show'])->whereNumber('id');
    Route::put('automations/{id}', [\App\Http\Controllers\Api\AutomationController::class, 'update'])->whereNumber('id');
    Route::delete('automations/{id}', [\App\Http\Controllers\Api\AutomationController::class, 'destroy'])->whereNumber('id');
    Route::get('automations/options', [\App\Http\Controllers\Api\AutomationController::class, 'options']);
    Route::get('automations/{id}/logs', [\App\Http\Controllers\Api\AutomationController::class, 'logs'])->whereNumber('id');

    // Ad Campaigns
    Route::post('campaigns/{id}/ads', [\App\Http\Controllers\Api\CampaignsController::class, 'createAd'])->whereNumber('id');
    Route::get('campaigns/{id}/ads-metrics', [\App\Http\Controllers\Api\CampaignsController::class, 'getAdMetrics'])->whereNumber('id');

    // Campaign Enhancement APIs
    Route::post('campaigns/{id}/test', [\App\Http\Controllers\Api\CampaignEnhancementController::class, 'test'])->whereNumber('id');
    Route::get('campaigns/{id}/preview', [\App\Http\Controllers\Api\CampaignEnhancementController::class, 'preview'])->whereNumber('id');
    Route::post('campaigns/{id}/validate', [\App\Http\Controllers\Api\CampaignEnhancementController::class, 'validateCampaign'])->whereNumber('id');
    Route::post('campaigns/{id}/schedule', [\App\Http\Controllers\Api\CampaignEnhancementController::class, 'schedule'])->whereNumber('id');
    Route::post('campaigns/{id}/unschedule', [\App\Http\Controllers\Api\CampaignEnhancementController::class, 'unschedule'])->whereNumber('id');
    Route::post('campaigns/{id}/archive', [\App\Http\Controllers\Api\CampaignEnhancementController::class, 'archive'])->whereNumber('id');
    Route::post('campaigns/{id}/restore', [\App\Http\Controllers\Api\CampaignEnhancementController::class, 'restore'])->whereNumber('id');

    // Campaign Templates
    Route::post('campaigns/templates', [\App\Http\Controllers\Api\CampaignEnhancementController::class, 'createTemplate']);
    Route::put('campaigns/templates/{id}', [\App\Http\Controllers\Api\CampaignEnhancementController::class, 'updateTemplate'])->whereNumber('id');
    Route::delete('campaigns/templates/{id}', [\App\Http\Controllers\Api\CampaignEnhancementController::class, 'deleteTemplate'])->whereNumber('id');

    // Bulk Campaign Operations
    Route::post('campaigns/bulk-send', [\App\Http\Controllers\Api\CampaignEnhancementController::class, 'bulkSend']);
    Route::post('campaigns/bulk-pause', [\App\Http\Controllers\Api\CampaignEnhancementController::class, 'bulkPause']);
    Route::post('campaigns/bulk-resume', [\App\Http\Controllers\Api\CampaignEnhancementController::class, 'bulkResume']);
    Route::post('campaigns/bulk-archive', [\App\Http\Controllers\Api\CampaignEnhancementController::class, 'bulkArchive']);

    // Campaign Import/Export
    Route::get('campaigns/export', [\App\Http\Controllers\Api\CampaignEnhancementController::class, 'export']);
    Route::post('campaigns/import', [\App\Http\Controllers\Api\CampaignEnhancementController::class, 'import']);

    // ==================== BULK OPERATIONS ====================

    // Forms Bulk Operations
    Route::post('forms/bulk-delete', [\App\Http\Controllers\Api\BulkOpsController::class, 'bulkDeleteForms']);
    Route::post('forms/bulk-activate', [\App\Http\Controllers\Api\BulkOpsController::class, 'bulkActivateForms']);
    Route::post('forms/bulk-deactivate', [\App\Http\Controllers\Api\BulkOpsController::class, 'bulkDeactivateForms']);

    // Lists Bulk Operations
    Route::post('lists/bulk-delete', [\App\Http\Controllers\Api\BulkOpsController::class, 'bulkDeleteLists']);
    Route::post('lists/bulk-activate', [\App\Http\Controllers\Api\BulkOpsController::class, 'bulkActivateLists']);
    Route::post('lists/bulk-deactivate', [\App\Http\Controllers\Api\BulkOpsController::class, 'bulkDeactivateLists']);
    Route::get('lists/export', [\App\Http\Controllers\Api\BulkOpsController::class, 'exportLists']);
    Route::post('lists/import', [\App\Http\Controllers\Api\BulkOpsController::class, 'importLists']);
    Route::get('lists/{id}/export', [\App\Http\Controllers\Api\BulkOpsController::class, 'exportSingleList'])->whereNumber('id');

    // Events Bulk Operations
    Route::post('events/bulk-delete', [\App\Http\Controllers\Api\BulkOpsController::class, 'bulkDeleteEvents']);
    Route::post('events/bulk-cancel', [\App\Http\Controllers\Api\BulkOpsController::class, 'bulkCancelEvents']);
    Route::post('events/bulk-activate', [\App\Http\Controllers\Api\BulkOpsController::class, 'bulkActivateEvents']);
    Route::get('events/export', [\App\Http\Controllers\Api\BulkOpsController::class, 'exportEvents']);
    Route::post('events/import', [\App\Http\Controllers\Api\BulkOpsController::class, 'importEvents']);
    Route::get('events/{id}/export', [\App\Http\Controllers\Api\BulkOpsController::class, 'exportSingleEvent'])->whereNumber('id');
    Route::post('events/{id}/cancel', [\App\Http\Controllers\Api\BulkOpsController::class, 'cancelEvent'])->whereNumber('id');
    Route::post('events/{id}/reschedule', [\App\Http\Controllers\Api\BulkOpsController::class, 'rescheduleEvent'])->whereNumber('id');

    // Meetings Bulk Operations
    Route::post('meetings/bulk-delete', [\App\Http\Controllers\Api\BulkOpsController::class, 'bulkDeleteMeetings']);
    Route::post('meetings/bulk-cancel', [\App\Http\Controllers\Api\BulkOpsController::class, 'bulkCancelMeetings']);
    Route::post('meetings/bulk-reschedule', [\App\Http\Controllers\Api\BulkOpsController::class, 'bulkRescheduleMeetings']);
    Route::get('meetings/export', [\App\Http\Controllers\Api\BulkOpsController::class, 'exportMeetings']);
    Route::post('meetings/import', [\App\Http\Controllers\Api\BulkOpsController::class, 'importMeetings']);
    Route::get('meetings/{id}/export', [\App\Http\Controllers\Api\BulkOpsController::class, 'exportSingleMeeting'])->whereNumber('id');
    Route::post('meetings/{id}/cancel', [\App\Http\Controllers\Api\BulkOpsController::class, 'cancelMeeting'])->whereNumber('id');
    Route::post('meetings/{id}/reschedule', [\App\Http\Controllers\Api\BulkOpsController::class, 'rescheduleMeeting'])->whereNumber('id');

    // ==================== MODULE ENHANCEMENTS ====================

    // Lead Scoring Enhancements
    Route::post('lead-scoring/bulk-recalculate', [\App\Http\Controllers\Api\LeadScoringEnhancementController::class, 'bulkRecalculate']);
    Route::post('lead-scoring/bulk-activate', [\App\Http\Controllers\Api\LeadScoringEnhancementController::class, 'bulkActivate']);
    Route::post('lead-scoring/bulk-deactivate', [\App\Http\Controllers\Api\LeadScoringEnhancementController::class, 'bulkDeactivate']);
    Route::get('lead-scoring/export', [\App\Http\Controllers\Api\LeadScoringEnhancementController::class, 'export']);
    Route::post('lead-scoring/import', [\App\Http\Controllers\Api\LeadScoringEnhancementController::class, 'import']);
    Route::get('lead-scoring/contacts/export', [\App\Http\Controllers\Api\LeadScoringEnhancementController::class, 'exportContacts']);

    // Journeys Enhancements
    Route::post('journeys/bulk-delete', [\App\Http\Controllers\Api\JourneysEnhancementController::class, 'bulkDelete']);
    Route::post('journeys/bulk-activate', [\App\Http\Controllers\Api\JourneysEnhancementController::class, 'bulkActivate']);
    Route::post('journeys/bulk-pause', [\App\Http\Controllers\Api\JourneysEnhancementController::class, 'bulkPause']);
    Route::get('journeys/export', [\App\Http\Controllers\Api\JourneysEnhancementController::class, 'export']);
    Route::post('journeys/import', [\App\Http\Controllers\Api\JourneysEnhancementController::class, 'import']);
    Route::get('journeys/{id}/export', [\App\Http\Controllers\Api\JourneysEnhancementController::class, 'exportSingle'])->whereNumber('id');
    Route::post('journeys/{id}/pause', [\App\Http\Controllers\Api\JourneysEnhancementController::class, 'pause'])->whereNumber('id');
    Route::post('journeys/{id}/resume', [\App\Http\Controllers\Api\JourneysEnhancementController::class, 'resume'])->whereNumber('id');

    // Ad Accounts Enhancements
    Route::post('ad-accounts/bulk-delete', [\App\Http\Controllers\Api\AdAccountsEnhancementController::class, 'bulkDelete']);
    Route::post('ad-accounts/bulk-activate', [\App\Http\Controllers\Api\AdAccountsEnhancementController::class, 'bulkActivate']);
    Route::post('ad-accounts/bulk-deactivate', [\App\Http\Controllers\Api\AdAccountsEnhancementController::class, 'bulkDeactivate']);
    Route::get('ad-accounts/export', [\App\Http\Controllers\Api\AdAccountsEnhancementController::class, 'export']);
    Route::post('ad-accounts/import', [\App\Http\Controllers\Api\AdAccountsEnhancementController::class, 'import']);
    Route::get('ad-accounts/{id}/export', [\App\Http\Controllers\Api\AdAccountsEnhancementController::class, 'exportSingle'])->whereNumber('id');

    // ==================== FORECAST, ANALYTICS & BUYER INTENT ENHANCEMENTS ====================

    // Forecast Enhancements
    Route::get('forecast/export', [\App\Http\Controllers\Api\ForecastEnhancementController::class, 'export']);
    Route::post('forecast/import', [\App\Http\Controllers\Api\ForecastEnhancementController::class, 'import']);
    Route::get('forecast/reports', [\App\Http\Controllers\Api\ForecastEnhancementController::class, 'reports']);
    Route::get('forecast/export/{format}', [\App\Http\Controllers\Api\ForecastEnhancementController::class, 'exportFormat'])->whereIn('format', ['csv', 'excel', 'json', 'pdf']);

    // Analytics Enhancements
    Route::get('analytics/export', [\App\Http\Controllers\Api\AnalyticsEnhancementController::class, 'export']);
    Route::get('analytics/reports', [\App\Http\Controllers\Api\AnalyticsEnhancementController::class, 'reports']);
    Route::get('analytics/export/{module}', [\App\Http\Controllers\Api\AnalyticsEnhancementController::class, 'exportModule']);
    Route::post('analytics/schedule-report', [\App\Http\Controllers\Api\AnalyticsEnhancementController::class, 'scheduleReport']);
    Route::get('analytics/scheduled-reports', [\App\Http\Controllers\Api\AnalyticsEnhancementController::class, 'scheduledReports']);
    Route::delete('analytics/scheduled-reports/{id}', [\App\Http\Controllers\Api\AnalyticsEnhancementController::class, 'deleteScheduledReport'])->whereNumber('id');

    // Buyer Intent Enhancements
    Route::get('tracking/export', [\App\Http\Controllers\Api\BuyerIntentEnhancementController::class, 'export']);
    Route::post('tracking/bulk-delete', [\App\Http\Controllers\Api\BuyerIntentEnhancementController::class, 'bulkDelete']);
    Route::get('tracking/reports', [\App\Http\Controllers\Api\BuyerIntentEnhancementController::class, 'reports']);
    Route::post('tracking/settings', [\App\Http\Controllers\Api\BuyerIntentEnhancementController::class, 'settings']);

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
    
    // Individual Analytics Module Routes
    Route::get('analytics/contacts', [\App\Http\Controllers\Api\AnalyticsController::class, 'contacts']);
    Route::get('analytics/companies', [\App\Http\Controllers\Api\AnalyticsController::class, 'companies']);
    Route::get('analytics/deals', [\App\Http\Controllers\Api\AnalyticsController::class, 'deals']);
    Route::get('analytics/campaigns', [\App\Http\Controllers\Api\AnalyticsController::class, 'campaigns']);
    Route::get('analytics/ads', [\App\Http\Controllers\Api\AnalyticsController::class, 'ads']);
    Route::get('analytics/events', [\App\Http\Controllers\Api\AnalyticsController::class, 'events']);
    Route::get('analytics/meetings', [\App\Http\Controllers\Api\AnalyticsController::class, 'meetings']);
    Route::get('analytics/tasks', [\App\Http\Controllers\Api\AnalyticsController::class, 'tasks']);
    Route::get('analytics/forecast', [\App\Http\Controllers\Api\AnalyticsController::class, 'forecast']);
    Route::get('analytics/lead-scoring', [\App\Http\Controllers\Api\AnalyticsController::class, 'leadScoring']);
    Route::get('analytics/journeys', [\App\Http\Controllers\Api\AnalyticsController::class, 'journeys']);
    Route::get('analytics/visitor-intent', [\App\Http\Controllers\Api\AnalyticsController::class, 'visitorIntent']);

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
    Route::get('campaigns/track/bounce', [\App\Http\Controllers\Api\CampaignTrackingController::class, 'bounce'])->name('campaigns.track.bounce');
    
    // Campaign reporting endpoints
    Route::get('campaigns/{campaign}/opens', [\App\Http\Controllers\Api\CampaignTrackingController::class, 'getOpensByCampaign'])->name('campaigns.opens')->where('campaign', '[0-9]+');
    Route::get('campaigns/{campaign}/clicks', [\App\Http\Controllers\Api\CampaignTrackingController::class, 'getClicksByCampaign'])->name('campaigns.clicks')->where('campaign', '[0-9]+');
    Route::get('campaigns/{campaign}/bounces', [\App\Http\Controllers\Api\CampaignTrackingController::class, 'getBouncesByCampaign'])->name('campaigns.bounces')->where('campaign', '[0-9]+');
    // Campaign unsubscribe
    Route::get('campaigns/unsubscribe/{recipientId}', [\App\Http\Controllers\Api\UnsubscribeController::class, 'unsubscribe'])->name('campaigns.unsubscribe')->where('recipientId', '[0-9]+');
});


