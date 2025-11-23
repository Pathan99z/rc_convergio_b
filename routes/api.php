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
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\FeatureStatusController;
use App\Http\Controllers\Api\FacebookOAuthController;
use App\Http\Controllers\Api\GoogleOAuthController;
use App\Http\Controllers\Api\TeamsOAuthController;
use App\Http\Controllers\Api\OutlookOAuthController;
use App\Http\Controllers\Api\DocumentsController;
use App\Http\Controllers\Api\IntegrationController;
use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\Api\SocialMediaController;
// use App\Http\Controllers\Api\SocialMediaOAuthController;
use App\Http\Controllers\Api\ContactJourneyFlowController;
use App\Http\Controllers\Api\SocialListeningController;


// CMS Controllers (EXISTING)
use App\Http\Controllers\Api\Cms\PageController;
use App\Http\Controllers\Api\Cms\TemplateController;
use App\Http\Controllers\Api\Cms\PersonalizationController;
use App\Http\Controllers\Api\Cms\ABTestController;
use App\Http\Controllers\Api\Cms\DomainController;
use App\Http\Controllers\Api\Cms\LanguageController;
use App\Http\Controllers\Api\Cms\MembershipController;

// Missing Controllers (NEED TO CREATE)
use App\Http\Controllers\Api\SeoController;
use App\Http\Controllers\Api\SocialMediaController;
use App\Http\Controllers\Api\SocialMediaOAuthController;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::get('verify', [AuthController::class, 'verifyEmail'])->name('auth.verify')->middleware('signed');
    Route::post('forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('reset', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::post('resend-verification', [AuthController::class, 'resendVerificationEmail'])->middleware('throttle:3,1');
});

// Public tracking endpoint for external websites
Route::post('tracking/events', [\App\Http\Controllers\Api\TrackingController::class, 'logEvent']);

// Public download endpoints for exports and reports (no auth required for temporary download URLs)
Route::get('tracking/export/{jobId}/download', [\App\Http\Controllers\Api\AsyncExportController::class, 'downloadExport']);
Route::get('tracking/reports/{jobId}/download', [\App\Http\Controllers\Api\AsyncReportController::class, 'downloadReport']);

// Public AI Support Agent endpoints (no auth required - uses tenant_id for isolation)
Route::post('ai/support-agent/message', [\App\Http\Controllers\Api\AI\SupportAgentController::class, 'message']);
Route::post('ai/support-agent/article', [\App\Http\Controllers\Api\AI\SupportAgentController::class, 'processArticleRequest']);

// Convergio Copilot endpoints (auth required - internal platform assistant)
Route::middleware(['auth:sanctum'])->prefix('copilot')->group(function () {
    Route::post('ask', [\App\Http\Controllers\Api\AI\ConvergioCopilotController::class, 'ask']);
    Route::get('features', [\App\Http\Controllers\Api\AI\ConvergioCopilotController::class, 'features']);
    Route::get('history', [\App\Http\Controllers\Api\AI\ConvergioCopilotController::class, 'history']);
    Route::get('stats', [\App\Http\Controllers\Api\AI\ConvergioCopilotController::class, 'stats']);
});

// Public Survey endpoints (no auth required - uses tenant_id for isolation)
Route::get('service/surveys/public', [\App\Http\Controllers\Api\Service\SurveyController::class, 'publicSurveys']);
Route::post('service/surveys/{id}/submit', [\App\Http\Controllers\Api\Service\SurveyController::class, 'submitResponse']);

// Public Email Webhook endpoints (no auth required - uses email address for tenant identification)
Route::post('service/email/webhook', [\App\Http\Controllers\Api\Service\EmailWebhookController::class, 'handleIncomingEmail'])->name('api.service.email.webhook');
Route::post('service/email/webhook/gmail', [\App\Http\Controllers\Api\Service\EmailWebhookController::class, 'handleGmailPush']);
Route::get('service/email/webhook/test', [\App\Http\Controllers\Api\Service\EmailWebhookController::class, 'test']);
Route::get('service/email/webhook/verify', [\App\Http\Controllers\Api\Service\EmailWebhookController::class, 'verify']);

Route::middleware(['auth:sanctum', 'license.check'])->group(function () {
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
    Route::get('deals/{id}/stage-history', [\App\Http\Controllers\Api\DealsController::class, 'stageHistory'])->whereNumber('id');

    // Quotes resource
    Route::get('quotes', [\App\Http\Controllers\Api\QuoteController::class, 'index']);
    Route::post('quotes', [\App\Http\Controllers\Api\QuoteController::class, 'store']);
    Route::post('quotes/preview-prices', [\App\Http\Controllers\Api\QuoteController::class, 'previewPrices']);
    Route::get('contacts/{contactId}/deals', [\App\Http\Controllers\Api\QuoteController::class, 'getCustomerDeals'])->whereNumber('contactId');
    Route::get('quotes/{quote}', [\App\Http\Controllers\Api\QuoteController::class, 'show'])->whereNumber('quote');
    Route::put('quotes/{quote}', [\App\Http\Controllers\Api\QuoteController::class, 'update'])->whereNumber('quote');
    Route::delete('quotes/{quote}', [\App\Http\Controllers\Api\QuoteController::class, 'destroy'])->whereNumber('quote');
    Route::post('quotes/{quote}/send', [\App\Http\Controllers\Api\QuoteController::class, 'send'])->whereNumber('quote');
    Route::post('quotes/{quote}/accept', [\App\Http\Controllers\Api\QuoteController::class, 'accept'])->whereNumber('quote');
    Route::post('quotes/{quote}/reject', [\App\Http\Controllers\Api\QuoteController::class, 'reject'])->whereNumber('quote');
    Route::get('quotes/{quote}/pdf', [\App\Http\Controllers\Api\QuoteController::class, 'pdf'])->whereNumber('quote');

    // Exchange Rates
    Route::get('exchange-rates/rate', [\App\Http\Controllers\Api\ExchangeRateController::class, 'getRate']);
    Route::post('exchange-rates/refresh', [\App\Http\Controllers\Api\ExchangeRateController::class, 'refresh']);

    // Products resource
    Route::get('products', [\App\Http\Controllers\Api\ProductController::class, 'index']);
    Route::post('products', [\App\Http\Controllers\Api\ProductController::class, 'store']);
    Route::get('products/{product}', [\App\Http\Controllers\Api\ProductController::class, 'show'])->whereNumber('product');
    Route::put('products/{product}', [\App\Http\Controllers\Api\ProductController::class, 'update'])->whereNumber('product');
    Route::delete('products/{product}', [\App\Http\Controllers\Api\ProductController::class, 'destroy'])->whereNumber('product');

    // Quote Templates resource
    Route::get('quote-templates', [\App\Http\Controllers\Api\QuoteTemplateController::class, 'index']);
    Route::post('quote-templates', [\App\Http\Controllers\Api\QuoteTemplateController::class, 'store']);
    Route::get('quote-templates/{quoteTemplate}', [\App\Http\Controllers\Api\QuoteTemplateController::class, 'show'])->whereNumber('quoteTemplate');
    Route::put('quote-templates/{quoteTemplate}', [\App\Http\Controllers\Api\QuoteTemplateController::class, 'update'])->whereNumber('quoteTemplate');
    Route::delete('quote-templates/{quoteTemplate}', [\App\Http\Controllers\Api\QuoteTemplateController::class, 'destroy'])->whereNumber('quoteTemplate');
    Route::get('quote-templates/{quoteTemplate}/preview', [\App\Http\Controllers\Api\QuoteTemplateController::class, 'preview'])->whereNumber('quoteTemplate');

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
    Route::get('tasks/deals', [\App\Http\Controllers\Api\TasksController::class, 'getDealsForContact']);
    Route::get('tasks/quotes', [\App\Http\Controllers\Api\TasksController::class, 'getQuotesForContact']);
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

    // Lead Scoring Templates (Industry-Standard)
    Route::get('lead-scoring/templates', [\App\Http\Controllers\Api\LeadScoringTemplateController::class, 'getTemplates']);
    Route::get('lead-scoring/templates/{templateKey}', [\App\Http\Controllers\Api\LeadScoringTemplateController::class, 'getTemplate']);
    Route::post('lead-scoring/templates/{templateKey}/activate', [\App\Http\Controllers\Api\LeadScoringTemplateController::class, 'activateTemplate']);
    Route::get('lead-scoring/templates/categories', [\App\Http\Controllers\Api\LeadScoringTemplateController::class, 'getCategories']);
    Route::get('lead-scoring/suggestions', [\App\Http\Controllers\Api\LeadScoringTemplateController::class, 'getSuggestions']);
    Route::post('lead-scoring/suggestions/create', [\App\Http\Controllers\Api\LeadScoringTemplateController::class, 'createFromSuggestions']);

    // Lead Scoring Auto-Detection
    Route::post('lead-scoring/auto-detect/email', [\App\Http\Controllers\Api\LeadScoringAutoDetectionController::class, 'detectEmailEvent']);
    Route::post('lead-scoring/auto-detect/website', [\App\Http\Controllers\Api\LeadScoringAutoDetectionController::class, 'detectWebsiteEvent']);
    Route::post('lead-scoring/auto-detect/form', [\App\Http\Controllers\Api\LeadScoringAutoDetectionController::class, 'detectFormEvent']);
    Route::post('lead-scoring/auto-detect/deal', [\App\Http\Controllers\Api\LeadScoringAutoDetectionController::class, 'detectDealEvent']);
    Route::post('lead-scoring/auto-detect/meeting', [\App\Http\Controllers\Api\LeadScoringAutoDetectionController::class, 'detectMeetingEvent']);
    Route::get('lead-scoring/auto-detect/suggestions', [\App\Http\Controllers\Api\LeadScoringAutoDetectionController::class, 'getAutoSuggestions']);
    Route::post('lead-scoring/auto-detect/test', [\App\Http\Controllers\Api\LeadScoringAutoDetectionController::class, 'testDetection']);

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

    // Facebook OAuth Integration
    Route::prefix('facebook')->group(function () {
        Route::get('oauth/redirect', [FacebookOAuthController::class, 'redirect']);
        Route::get('ad-accounts', [FacebookOAuthController::class, 'getAdAccounts']);
        Route::post('disconnect', [FacebookOAuthController::class, 'disconnect']);
        Route::post('refresh', [FacebookOAuthController::class, 'refresh']);
    });

    // Google OAuth Integration
    Route::prefix('google')->group(function () {
        Route::get('oauth/redirect', [GoogleOAuthController::class, 'redirect']);
        Route::get('ad-accounts', [GoogleOAuthController::class, 'getAdAccounts']);
        Route::post('disconnect', [GoogleOAuthController::class, 'disconnect']);
        Route::post('refresh', [GoogleOAuthController::class, 'refresh']);
    });

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
    Route::get('events/analytics', [\App\Http\Controllers\Api\EventsController::class, 'getEventsAnalytics']);
    
    // Event sharing and professional features
    Route::get('events/{id}/share-link', [\App\Http\Controllers\Api\EventsController::class, 'getShareLink'])->whereNumber('id');
    Route::get('events/{id}/qr-code', [\App\Http\Controllers\Api\EventsController::class, 'getQrCode'])->whereNumber('id');
    Route::get('events/{id}/calendar', [\App\Http\Controllers\Api\EventsController::class, 'getCalendarEvent'])->whereNumber('id');
    Route::get('events/{id}/calendar/ical', [\App\Http\Controllers\Api\EventsController::class, 'downloadIcal'])->whereNumber('id');
    Route::post('events/{id}/send-invitations', [\App\Http\Controllers\Api\EventsController::class, 'sendInvitations'])->whereNumber('id');
    Route::get('events/{id}/analytics', [\App\Http\Controllers\Api\EventsController::class, 'getAnalytics'])->whereNumber('id');

    // Visitor Intent Tracking
    Route::get('tracking/intent', [\App\Http\Controllers\Api\TrackingController::class, 'getIntentSignals']);
    Route::get('tracking/analytics', [\App\Http\Controllers\Api\TrackingController::class, 'getIntentAnalytics']);
    Route::get('tracking/actions', [\App\Http\Controllers\Api\TrackingController::class, 'getAvailableActions']);
    Route::get('tracking/intent-levels', [\App\Http\Controllers\Api\TrackingController::class, 'getIntentLevels']);
    Route::get('tracking/visitor-intent-analytics', [\App\Http\Controllers\Api\TrackingController::class, 'getVisitorIntentAnalytics']);
    Route::get('tracking/visitor-stats', [\App\Http\Controllers\Api\TrackingController::class, 'getVisitorStats']);
    
    // Tracking Script Generator
    Route::get('tracking/script', [\App\Http\Controllers\Api\TrackingController::class, 'getTrackingScript']);
    
    // Scoring Configuration Management
    Route::get('tracking/scoring/config', [\App\Http\Controllers\Api\TrackingController::class, 'getScoringConfig']);
    Route::put('tracking/scoring/config', [\App\Http\Controllers\Api\TrackingController::class, 'updateScoringConfig']);
    Route::get('tracking/scoring/stats', [\App\Http\Controllers\Api\TrackingController::class, 'getScoringStats']);
    Route::post('tracking/scoring/test', [\App\Http\Controllers\Api\TrackingController::class, 'testScoring']);
    Route::get('tracking/url-stats', [\App\Http\Controllers\Api\TrackingController::class, 'getUrlStats']);

    // Campaign Intent Integration (NEW - doesn't interfere with existing campaign functionality)
    Route::post('campaigns/intent-webhook', [\App\Http\Controllers\Api\CampaignIntentWebhookController::class, 'handleIntentEvents']);
    Route::get('campaigns/intent-stats', [\App\Http\Controllers\Api\CampaignIntentWebhookController::class, 'getCampaignIntentStats']);

    // Async Exports & Reports (NEW - doesn't interfere with existing functionality)
    Route::post('tracking/export', [\App\Http\Controllers\Api\AsyncExportController::class, 'createExport']);
    Route::get('tracking/export/{jobId}/status', [\App\Http\Controllers\Api\AsyncExportController::class, 'getExportStatus']);
    Route::get('tracking/exports', [\App\Http\Controllers\Api\AsyncExportController::class, 'listExports']);
    
    Route::post('tracking/reports', [\App\Http\Controllers\Api\AsyncReportController::class, 'createReport']);
    Route::get('tracking/reports/{jobId}/status', [\App\Http\Controllers\Api\AsyncReportController::class, 'getReportStatus']);
    Route::get('tracking/reports', [\App\Http\Controllers\Api\AsyncReportController::class, 'listReports']);
    
    // Intent Event Management (CRUD)
    Route::get('tracking/events/{id}', [\App\Http\Controllers\Api\TrackingEventController::class, 'show'])->whereNumber('id');
    Route::put('tracking/events/{id}', [\App\Http\Controllers\Api\TrackingEventController::class, 'update'])->whereNumber('id');
    Route::delete('tracking/events/{id}', [\App\Http\Controllers\Api\TrackingEventController::class, 'destroy'])->whereNumber('id');
    
    // Module Integration APIs
    Route::get('tracking/contacts/{id}/intent', [\App\Http\Controllers\Api\TrackingController::class, 'getContactIntent'])->whereNumber('id');
    Route::get('tracking/companies/{id}/intent', [\App\Http\Controllers\Api\TrackingController::class, 'getCompanyIntent'])->whereNumber('id');
    Route::get('tracking/campaigns/{id}/intent', [\App\Http\Controllers\Api\TrackingController::class, 'getCampaignIntent'])->whereNumber('id');
    Route::get('tracking/events/{id}/intent', [\App\Http\Controllers\Api\TrackingController::class, 'getEventIntent'])->whereNumber('id');

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
    Route::get('journeys/customer', [\App\Http\Controllers\Api\JourneysController::class, 'customer']);
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
    Route::get('meetings/{id}', [\App\Http\Controllers\Api\MeetingsController::class, 'show'])->whereNumber('id');
    Route::put('meetings/{id}', [\App\Http\Controllers\Api\MeetingsController::class, 'update'])->whereNumber('id');
    Route::delete('meetings/{id}', [\App\Http\Controllers\Api\MeetingsController::class, 'destroy'])->whereNumber('id');
    Route::post('meetings/{id}/status', [\App\Http\Controllers\Api\MeetingsController::class, 'updateStatus'])->whereNumber('id');
    Route::get('meetings/analytics', [\App\Http\Controllers\Api\MeetingsController::class, 'getAnalytics']);
    Route::get('meetings/upcoming', [\App\Http\Controllers\Api\MeetingsController::class, 'getUpcoming']);
    Route::post('meetings/sync/google', [\App\Http\Controllers\Api\MeetingsController::class, 'syncGoogle']);
    Route::post('meetings/sync/outlook', [\App\Http\Controllers\Api\MeetingsController::class, 'syncOutlook']);
    Route::get('meetings/statuses', [\App\Http\Controllers\Api\MeetingsController::class, 'getStatuses']);
    Route::get('meetings/providers', [\App\Http\Controllers\Api\MeetingsController::class, 'getProviders']);

        // Google OAuth for Meetings (moved outside auth middleware)

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
    
    // Zoom webhook (no auth required)
    Route::post('webhooks/zoom/events', [\App\Http\Controllers\Api\ZoomWebhookController::class, 'handleWebhook']);

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
    Route::get('users/for-assignment', [UsersController::class, 'forAssignment']); // ✅ NEW: Team-aware assignment endpoint
    Route::get('users/{id}', [UsersController::class, 'show'])->whereNumber('id');
    Route::post('users', [UsersController::class, 'store']);
    Route::put('users/{id}', [UsersController::class, 'update'])->whereNumber('id');
    Route::delete('users/{id}', [UsersController::class, 'destroy'])->whereNumber('id');

    // Teams resource (Admin only)
    Route::get('teams', [TeamController::class, 'index']);
    Route::post('teams', [TeamController::class, 'store']);
    Route::get('teams/{id}', [TeamController::class, 'show'])->whereNumber('id');
    Route::put('teams/{id}', [TeamController::class, 'update'])->whereNumber('id');
    Route::delete('teams/{id}', [TeamController::class, 'destroy'])->whereNumber('id');
    Route::get('teams/{id}/members', [TeamController::class, 'members'])->whereNumber('id');
    Route::post('teams/{id}/members', [TeamController::class, 'addMember'])->whereNumber('id');
    Route::delete('teams/{id}/members/{userId}', [TeamController::class, 'removeMember'])->whereNumber(['id', 'userId']);
    Route::put('teams/{id}/members/{userId}/role', [TeamController::class, 'updateMemberRole'])->whereNumber(['id', 'userId']);

    // Roles resource (for role dropdowns)
    Route::get('roles', [RoleController::class, 'index']);

    // Feature status and restrictions
    Route::get('features/status', [FeatureStatusController::class, 'index']);
    Route::get('features/check/{feature}', [FeatureStatusController::class, 'checkFeature']);

    // Audit logs
    Route::get('audit-logs', [\App\Http\Controllers\Api\AuditLogController::class, 'index']);

    // Sequences (Automated Outreach)
    Route::prefix('sequences')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\SequencesController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\SequencesController::class, 'store']);
        Route::get('{sequence}', [\App\Http\Controllers\Api\SequencesController::class, 'show'])->whereNumber('sequence');
        Route::put('{sequence}', [\App\Http\Controllers\Api\SequencesController::class, 'update'])->whereNumber('sequence');
        Route::delete('{sequence}', [\App\Http\Controllers\Api\SequencesController::class, 'destroy'])->whereNumber('sequence');
        Route::post('{sequence}/steps', [\App\Http\Controllers\Api\SequenceStepsController::class, 'store'])->whereNumber('sequence');
        Route::put('steps/{step}', [\App\Http\Controllers\Api\SequenceStepsController::class, 'update'])->whereNumber('step');
        Route::delete('steps/{step}', [\App\Http\Controllers\Api\SequenceStepsController::class, 'destroy'])->whereNumber('step');
        Route::post('{sequence}/enroll', [\App\Http\Controllers\Api\SequenceEnrollmentsController::class, 'enroll'])->whereNumber('sequence');
        Route::post('enrollments/{enrollment}/pause', [\App\Http\Controllers\Api\SequenceEnrollmentsController::class, 'pause'])->whereNumber('enrollment');
        Route::post('enrollments/{enrollment}/resume', [\App\Http\Controllers\Api\SequenceEnrollmentsController::class, 'resume'])->whereNumber('enrollment');
        Route::post('enrollments/{enrollment}/cancel', [\App\Http\Controllers\Api\SequenceEnrollmentsController::class, 'cancel'])->whereNumber('enrollment');
        Route::get('{sequence}/logs', [\App\Http\Controllers\Api\SequenceEnrollmentsController::class, 'logs'])->whereNumber('sequence');
        Route::get('enrollments/{enrollment}/logs', [\App\Http\Controllers\Api\SequenceEnrollmentsController::class, 'enrollmentLogs'])->whereNumber('enrollment');
    });

    // Template Preview & Editing (NEW - Real-time template system)
    Route::prefix('templates')->group(function () {
        Route::post('preview', [\App\Http\Controllers\Api\TemplatePreviewController::class, 'preview']);
        Route::put('update-content', [\App\Http\Controllers\Api\TemplatePreviewController::class, 'updateContent']);
        Route::post('create-custom', [\App\Http\Controllers\Api\TemplatePreviewController::class, 'createCustom']);
    });
});

// Public routes (no auth required)
Route::prefix('public')->group(function () {
    Route::get('forms/{id}', [PublicFormController::class, 'show'])->whereNumber('id');
    Route::post('forms/{id}/submit', [PublicFormController::class, 'submit'])->whereNumber('id');
    
    // Public event registration
    Route::get('events/{id}', [\App\Http\Controllers\Api\PublicEventController::class, 'show'])->whereNumber('id');
    Route::post('events/{id}/register', [\App\Http\Controllers\Api\PublicEventController::class, 'register'])->whereNumber('id');
    Route::get('events/{id}/rsvp', [\App\Http\Controllers\Api\PublicEventController::class, 'rsvp'])->whereNumber('id');
    
    // Public ticket creation (for customers without authentication)
    Route::post('tickets', [\App\Http\Controllers\Api\Service\TicketController::class, 'publicStore']);
    
    // Generate tenant-specific public form URL
    Route::get('tickets/form-url/{tenantId}', [\App\Http\Controllers\Api\Service\TicketController::class, 'generateFormUrl'])->whereNumber('tenantId');
    
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

// Public routes (no auth required)
Route::prefix('public')->group(function () {
    Route::get('forms/{id}', [PublicFormController::class, 'show'])->whereNumber('id');
    Route::post('forms/{id}/submit', [PublicFormController::class, 'submit'])->whereNumber('id');
    
    // Public event registration
    Route::get('events/{id}', [\App\Http\Controllers\Api\PublicEventController::class, 'show'])->whereNumber('id');
    Route::post('events/{id}/register', [\App\Http\Controllers\Api\PublicEventController::class, 'register'])->whereNumber('id');
    Route::get('events/{id}/rsvp', [\App\Http\Controllers\Api\PublicEventController::class, 'rsvp'])->whereNumber('id');
    
    // Public ticket creation (for customers without authentication)
    Route::post('tickets', [\App\Http\Controllers\Api\Service\TicketController::class, 'publicStore']);
    
    // Generate tenant-specific public form URL
    Route::get('tickets/form-url/{tenantId}', [\App\Http\Controllers\Api\Service\TicketController::class, 'generateFormUrl'])->whereNumber('tenantId');
    
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
// CMS Management Routes
Route::prefix('cms')->middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    // Pages Management
    Route::get('pages', [PageController::class, 'index']);
    Route::post('pages', [PageController::class, 'store']);
    Route::get('pages/{id}', [PageController::class, 'show'])->whereNumber('id');
    Route::put('pages/{id}', [PageController::class, 'update'])->whereNumber('id');
    Route::delete('pages/{id}', [PageController::class, 'destroy'])->whereNumber('id');
    Route::post('pages/{id}/publish', [PageController::class, 'publish'])->whereNumber('id');
    Route::post('pages/{id}/unpublish', [PageController::class, 'unpublish'])->whereNumber('id');
    Route::get('pages/{id}/preview', [PageController::class, 'preview'])->whereNumber('id');
    Route::post('pages/{id}/duplicate', [PageController::class, 'duplicate'])->whereNumber('id');

    // Templates Management
    Route::get('templates', [TemplateController::class, 'index']);
    Route::post('templates', [TemplateController::class, 'store']);
    Route::get('templates/{id}', [TemplateController::class, 'show'])->whereNumber('id');
    Route::put('templates/{id}', [TemplateController::class, 'update'])->whereNumber('id');
    Route::delete('templates/{id}', [TemplateController::class, 'destroy'])->whereNumber('id');
    Route::get('templates/types', [TemplateController::class, 'types']);

    // Personalization
    Route::get('personalization', [PersonalizationController::class, 'index']);
    Route::post('personalization', [PersonalizationController::class, 'store']);
    Route::get('personalization/{id}', [PersonalizationController::class, 'show'])->whereNumber('id');
    Route::put('personalization/{id}', [PersonalizationController::class, 'update'])->whereNumber('id');
    Route::delete('personalization/{id}', [PersonalizationController::class, 'destroy'])->whereNumber('id');
    Route::post('personalization/{id}/toggle', [PersonalizationController::class, 'toggle'])->whereNumber('id');
    Route::post('personalization/{id}/test', [PersonalizationController::class, 'test'])->whereNumber('id');
    Route::get('personalization/{id}/analytics', [PersonalizationController::class, 'analytics'])->whereNumber('id');
    Route::post('personalization/evaluate', [PersonalizationController::class, 'evaluate']);
    Route::get('personalization/analytics', [PersonalizationController::class, 'globalAnalytics']);
    Route::post('personalization/bulk-toggle', [PersonalizationController::class, 'bulkToggle']);
    Route::post('personalization/track-impression', [PersonalizationController::class, 'trackImpression']);
    Route::post('personalization/track-conversion', [PersonalizationController::class, 'trackConversion']);
    Route::get('personalization/operators', [PersonalizationController::class, 'operators']);
    Route::get('personalization/fields', [PersonalizationController::class, 'fields']);

    // A/B Testing
    Route::get('abtesting', [ABTestController::class, 'index']);
    Route::post('abtesting', [ABTestController::class, 'store']);
    Route::get('abtesting/{id}', [ABTestController::class, 'show'])->whereNumber('id');
    Route::put('abtesting/{id}', [ABTestController::class, 'update'])->whereNumber('id');
    Route::post('abtesting/{id}/start', [ABTestController::class, 'start'])->whereNumber('id');
    Route::post('abtesting/{id}/stop', [ABTestController::class, 'stop'])->whereNumber('id');
    Route::get('abtesting/{id}/results', [ABTestController::class, 'results'])->whereNumber('id');
    Route::post('abtesting/visitor', [ABTestController::class, 'recordVisitor']);
    Route::post('abtesting/conversion', [ABTestController::class, 'recordConversion']);
    Route::get('abtesting/analytics', [ABTestController::class, 'analytics']);
    Route::get('abtesting/metrics', [ABTestController::class, 'metrics']);
    Route::get('abtesting/active', [ABTestController::class, 'getActiveForPage']);

    // // SEO Analysis
    // Route::post('seo/analyze', [SeoController::class, 'analyzePage']);
    // Route::get('seo/logs', [SeoController::class, 'getSeoLogs']);
    // Route::get('seo/logs/{page_id}', [SeoController::class, 'getSeoLogsByPage'])->whereNumber('page_id');

    // Domains Management
    Route::get('domains', [\App\Http\Controllers\Api\Cms\DomainController::class, 'index']);
    Route::post('domains', [\App\Http\Controllers\Api\Cms\DomainController::class, 'store']);
    Route::get('domains/{id}', [\App\Http\Controllers\Api\Cms\DomainController::class, 'show'])->whereNumber('id');
    Route::put('domains/{id}', [\App\Http\Controllers\Api\Cms\DomainController::class, 'update'])->whereNumber('id');
    Route::delete('domains/{id}', [\App\Http\Controllers\Api\Cms\DomainController::class, 'destroy'])->whereNumber('id');

    // Languages Management
    Route::get('languages', [\App\Http\Controllers\Api\Cms\LanguageController::class, 'index']);
    Route::post('languages', [\App\Http\Controllers\Api\Cms\LanguageController::class, 'store']);
    Route::get('languages/{id}', [\App\Http\Controllers\Api\Cms\LanguageController::class, 'show'])->whereNumber('id');
    Route::put('languages/{id}', [\App\Http\Controllers\Api\Cms\LanguageController::class, 'update'])->whereNumber('id');
    Route::delete('languages/{id}', [\App\Http\Controllers\Api\Cms\LanguageController::class, 'destroy'])->whereNumber('id');

    // Memberships / Access Control
    Route::get('memberships', [\App\Http\Controllers\Api\Cms\MembershipController::class, 'index']);
    Route::get('memberships/{user_id}', [\App\Http\Controllers\Api\Cms\MembershipController::class, 'show'])->whereNumber('user_id');
    Route::post('pages/{id}/access', [\App\Http\Controllers\Api\Cms\MembershipController::class, 'setPageAccess'])->whereNumber('id');
    Route::get('pages/{id}/access', [\App\Http\Controllers\Api\Cms\MembershipController::class, 'getPageAccess'])->whereNumber('id');
    Route::delete('pages/{page_id}/access/{access_id}', [\App\Http\Controllers\Api\Cms\MembershipController::class, 'removePageAccess'])
        ->whereNumber(['page_id', 'access_id']);
});

// Contact Journey Flow endpoints
Route::get('contact-journey-flow', [ContactJourneyFlowController::class, 'getContactJourneyFlow']);
Route::get('contact-journey-flow/summary', [ContactJourneyFlowController::class, 'getJourneyFlowSummary']);
Route::get('contact-journey-flow/{contactId}', [ContactJourneyFlowController::class, 'getContactJourneyFlowById'])->whereNumber('contactId');
Route::get('contact-journey-flow/email/{email}', [ContactJourneyFlowController::class, 'getContactJourneyFlowByEmail']);

// SEO - Enhanced Analytics & Google Search Console Integration
Route::prefix('seo')->middleware(['auth:sanctum'])->group(function () {
    Route::get('pages', [SeoController::class, 'getPages']);
    Route::get('recommendations', [SeoController::class, 'getRecommendations']);
    Route::get('connection-status', [SeoController::class, 'checkConnection']);
    Route::get('metrics', [SeoController::class, 'getDashboardData']);
    Route::post('connect', [SeoController::class, 'initiateConnection']);
    Route::post('scan', [SeoController::class, 'startSiteScan']);
    Route::post('recommendations/{id}/resolve', [SeoController::class, 'resolveRecommendation'])->whereNumber('id');
});

// Social Media Management - Posts
Route::prefix('social-media')->middleware(['auth:sanctum'])->group(function () {
    Route::get('listening', [SocialMediaController::class, 'listening']);
});

// Missing API endpoints
Route::get('me', [UsersController::class, 'me']);
Route::get('status', [DashboardController::class, 'status']);

// Social Media Management - Posts
// Route::prefix('social')->group(function () {
//     // Post Management (Schedule and Publish)
//     Route::post('schedule-post', [SocialMediaController::class, 'store']); // Schedule a post
//     Route::post('publish-post', [SocialMediaController::class, 'store']); // Publish immediately (with publish_now flag)
//     Route::get('posts', [SocialMediaController::class, 'index']); // List all posts
//     Route::get('posts/{id}', [SocialMediaController::class, 'show'])->whereNumber('id'); // Get single post
//     Route::put('posts/{id}', [SocialMediaController::class, 'update'])->whereNumber('id'); // Update post
//     Route::delete('posts/{id}', [SocialMediaController::class, 'destroy'])->whereNumber('id'); // Delete post
//     Route::post('posts/{id}/publish', [SocialMediaController::class, 'publish'])->whereNumber('id'); // Publish a scheduled post
//     Route::get('posts/{id}/metrics', [SocialMediaController::class, 'metrics'])->whereNumber('id'); // Get post metrics
   
//     // OAuth & Account Management
//     Route::post('connect/{platform}', [SocialMediaOAuthController::class, 'connect'])->whereIn('platform', ['facebook', 'instagram', 'twitter', 'linkedin']); // Initiate OAuth
//     Route::post('callback/{platform}', [SocialMediaOAuthController::class, 'callback'])->whereIn('platform', ['facebook', 'instagram', 'twitter', 'linkedin']); // OAuth callback
//     Route::delete('disconnect/{platform}', [SocialMediaOAuthController::class, 'disconnect'])->whereIn('platform', ['facebook', 'instagram', 'twitter', 'linkedin']); // Disconnect account
//     Route::get('accounts', [SocialMediaOAuthController::class, 'getConnectedAccounts']); // Get all connected accounts
//     Route::post('refresh-token/{platform}', [SocialMediaOAuthController::class, 'refreshToken'])->whereIn('platform', ['facebook', 'instagram', 'twitter', 'linkedin']); // Refresh token
// });
// Facebook OAuth Callback (no auth required)
Route::get('oauth/facebook/callback', [FacebookOAuthController::class, 'callback']);

// Google OAuth Callback (no auth required)
Route::get('oauth/google/callback', [GoogleOAuthController::class, 'callback']);

// Meeting OAuth Callback (no auth required)
Route::get('meetings/oauth/google/callback', [\App\Http\Controllers\Api\MeetingOAuthController::class, 'callback']);

// Meeting OAuth Routes (no auth required for redirect)
Route::get('meetings/oauth/google', [\App\Http\Controllers\Api\MeetingOAuthController::class, 'redirect']);
Route::post('meetings/oauth/google/test', [\App\Http\Controllers\Api\MeetingOAuthController::class, 'testMeet']);
Route::post('meetings/oauth/google/force-real', [\App\Http\Controllers\Api\MeetingOAuthController::class, 'forceRealMeet']);

// Teams OAuth routes
Route::get('meetings/oauth/teams', [TeamsOAuthController::class, 'redirect']);
Route::get('meetings/oauth/teams/callback', [TeamsOAuthController::class, 'callback']);

// Outlook OAuth routes
Route::get('meetings/oauth/outlook', [OutlookOAuthController::class, 'redirect']);
Route::get('meetings/oauth/outlook/callback', [OutlookOAuthController::class, 'callback']);

// Public Quote Routes (No Authentication Required)
Route::prefix('public/quotes')->group(function () {
    Route::get('{uuid}', [\App\Http\Controllers\PublicQuoteController::class, 'show']);
    Route::post('{uuid}/accept', [\App\Http\Controllers\PublicQuoteController::class, 'accept']);
    Route::post('{uuid}/reject', [\App\Http\Controllers\PublicQuoteController::class, 'reject']);
});

// Assignment Rules Management (Admin only)
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::prefix('assignment-rules')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\AssignmentRulesController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\AssignmentRulesController::class, 'store']);
        Route::get('/operators', [\App\Http\Controllers\Api\AssignmentRulesController::class, 'operators']);
        Route::get('/fields', [\App\Http\Controllers\Api\AssignmentRulesController::class, 'fields']);
        Route::get('/stats', [\App\Http\Controllers\Api\AssignmentRulesController::class, 'stats']);
        Route::get('/{id}', [\App\Http\Controllers\Api\AssignmentRulesController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [\App\Http\Controllers\Api\AssignmentRulesController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [\App\Http\Controllers\Api\AssignmentRulesController::class, 'destroy'])->whereNumber('id');
        Route::patch('/{id}/toggle', [\App\Http\Controllers\Api\AssignmentRulesController::class, 'toggle'])->whereNumber('id');
    });

    Route::prefix('assignment-defaults')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\AssignmentDefaultsController::class, 'show']);
        Route::put('/', [\App\Http\Controllers\Api\AssignmentDefaultsController::class, 'update']);
        Route::get('/users', [\App\Http\Controllers\Api\AssignmentDefaultsController::class, 'users']);
        Route::get('/stats', [\App\Http\Controllers\Api\AssignmentDefaultsController::class, 'stats']);
        Route::post('/reset-counters', [\App\Http\Controllers\Api\AssignmentDefaultsController::class, 'resetCounters']);
        Route::patch('/toggle-automatic', [\App\Http\Controllers\Api\AssignmentDefaultsController::class, 'toggleAutomaticAssignment']);
    });

    // Assignment Audits (Admin only)
    Route::prefix('assignment-audits')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\AssignmentAuditsController::class, 'index']);
        Route::get('/export', [\App\Http\Controllers\Api\AssignmentAuditsController::class, 'export']);
    });

    // Assignment Logs (Admin only)
    Route::prefix('logs')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\AssignmentLogsController::class, 'index']);
        Route::get('/export', [\App\Http\Controllers\Api\AssignmentLogsController::class, 'export']);
    });

    // Documents Management
    Route::prefix('documents')->group(function () {
        Route::get('/', [DocumentsController::class, 'index']);
        Route::post('/', [DocumentsController::class, 'store']);
        Route::get('/analytics', [DocumentsController::class, 'analytics']);
        Route::get('/{id}/download', [DocumentsController::class, 'download'])->name('documents.download');
        Route::get('/{id}/preview', [DocumentsController::class, 'preview']);
        Route::post('/{id}/link', [DocumentsController::class, 'link']);
        Route::get('/{id}', [DocumentsController::class, 'show']);
        Route::put('/{id}', [DocumentsController::class, 'update']);
        Route::delete('/{id}', [DocumentsController::class, 'destroy']);
    });

    // Commerce Platform
    Route::prefix('commerce')->group(function () {
        // Orders
        Route::get('/orders', [\App\Http\Controllers\Api\Commerce\CommerceOrderController::class, 'index']);
        Route::post('/orders', [\App\Http\Controllers\Api\Commerce\CommerceOrderController::class, 'store']);
        Route::get('/orders/stats', [\App\Http\Controllers\Api\Commerce\CommerceOrderController::class, 'stats']);
        Route::get('/orders/{id}', [\App\Http\Controllers\Api\Commerce\CommerceOrderController::class, 'show'])->whereNumber('id');
        Route::put('/orders/{id}', [\App\Http\Controllers\Api\Commerce\CommerceOrderController::class, 'update'])->whereNumber('id');
        Route::delete('/orders/{id}', [\App\Http\Controllers\Api\Commerce\CommerceOrderController::class, 'destroy'])->whereNumber('id');

        // Payment Links
        Route::get('/payment-links', [\App\Http\Controllers\Api\Commerce\CommercePaymentLinkController::class, 'index']);
        Route::post('/payment-links', [\App\Http\Controllers\Api\Commerce\CommercePaymentLinkController::class, 'store']);
        Route::get('/payment-links/stats', [\App\Http\Controllers\Api\Commerce\CommercePaymentLinkController::class, 'stats']);
        Route::get('/payment-links/{id}', [\App\Http\Controllers\Api\Commerce\CommercePaymentLinkController::class, 'show'])->whereNumber('id');
        Route::put('/payment-links/{id}', [\App\Http\Controllers\Api\Commerce\CommercePaymentLinkController::class, 'update'])->whereNumber('id');
        Route::delete('/payment-links/{id}', [\App\Http\Controllers\Api\Commerce\CommercePaymentLinkController::class, 'destroy'])->whereNumber('id');
          Route::post('/payment-links/{id}/activate', [\App\Http\Controllers\Api\Commerce\CommercePaymentLinkController::class, 'activate'])->whereNumber('id');
          Route::post('/payment-links/{id}/deactivate', [\App\Http\Controllers\Api\Commerce\CommercePaymentLinkController::class, 'deactivate'])->whereNumber('id');
          Route::post('/payment-links/{id}/complete', [\App\Http\Controllers\Api\Commerce\CommercePaymentLinkController::class, 'complete'])->whereNumber('id');
          Route::post('/payment-links/{id}/send-email', [\App\Http\Controllers\Api\Commerce\CommercePaymentLinkController::class, 'sendEmail'])->whereNumber('id');
          Route::post('/payment-links/{id}/send-bulk-emails', [\App\Http\Controllers\Api\Commerce\CommercePaymentLinkController::class, 'sendBulkEmails'])->whereNumber('id');
          Route::post('/payment-links/create-and-send', [\App\Http\Controllers\Api\Commerce\CommercePaymentLinkController::class, 'createAndSend']);

        // Settings
        Route::get('/settings', [\App\Http\Controllers\Api\Commerce\CommerceSettingController::class, 'show']);
        Route::post('/settings', [\App\Http\Controllers\Api\Commerce\CommerceSettingController::class, 'update']);
        Route::post('/settings/test-connection', [\App\Http\Controllers\Api\Commerce\CommerceSettingController::class, 'testConnection']);
        Route::post('/settings/send-test-email', [\App\Http\Controllers\Api\Commerce\CommerceSettingController::class, 'sendTestEmail']);

        // Analytics
        Route::get('/analytics', [\App\Http\Controllers\Api\Commerce\CommerceAnalyticsController::class, 'index']);
        Route::get('/analytics/overview', [\App\Http\Controllers\Api\Commerce\CommerceAnalyticsController::class, 'overview']);
        Route::get('/analytics/revenue', [\App\Http\Controllers\Api\Commerce\CommerceAnalyticsController::class, 'revenue']);
        Route::get('/analytics/payment-links', [\App\Http\Controllers\Api\Commerce\CommerceAnalyticsController::class, 'paymentLinks']);
        Route::get('/analytics/orders', [\App\Http\Controllers\Api\Commerce\CommerceAnalyticsController::class, 'orders']);
        Route::get('/analytics/transactions', [\App\Http\Controllers\Api\Commerce\CommerceAnalyticsController::class, 'transactions']);
        Route::get('/analytics/trends', [\App\Http\Controllers\Api\Commerce\CommerceAnalyticsController::class, 'trends']);
        Route::get('/analytics/export', [\App\Http\Controllers\Api\Commerce\CommerceAnalyticsController::class, 'export']);
        Route::post('/settings/reset', [\App\Http\Controllers\Api\Commerce\CommerceSettingController::class, 'reset']);
        Route::get('/settings/stripe-config', [\App\Http\Controllers\Api\Commerce\CommerceSettingController::class, 'getStripeConfig']);

        // Webhooks (public, no auth required but signature verification)
        // Subscription Plans
        Route::get('/subscription-plans', [\App\Http\Controllers\Api\Commerce\SubscriptionPlanController::class, 'index']);
        Route::post('/subscription-plans', [\App\Http\Controllers\Api\Commerce\SubscriptionPlanController::class, 'store']);
        Route::get('/subscription-plans/{id}', [\App\Http\Controllers\Api\Commerce\SubscriptionPlanController::class, 'show'])->whereNumber('id');
        Route::put('/subscription-plans/{id}', [\App\Http\Controllers\Api\Commerce\SubscriptionPlanController::class, 'update'])->whereNumber('id');
        Route::delete('/subscription-plans/{id}', [\App\Http\Controllers\Api\Commerce\SubscriptionPlanController::class, 'destroy'])->whereNumber('id');

        // Subscriptions
        Route::get('/subscriptions', [\App\Http\Controllers\Api\Commerce\SubscriptionController::class, 'index']);
        Route::get('/subscriptions/{id}', [\App\Http\Controllers\Api\Commerce\SubscriptionController::class, 'show'])->whereNumber('id');
        Route::get('/subscriptions/{id}/activity', [\App\Http\Controllers\Api\Commerce\SubscriptionController::class, 'activity'])->whereNumber('id');
        Route::post('/subscriptions/{id}/cancel', [\App\Http\Controllers\Api\Commerce\SubscriptionController::class, 'cancel'])->whereNumber('id');
        Route::post('/subscriptions/{id}/change-plan', [\App\Http\Controllers\Api\Commerce\SubscriptionController::class, 'changePlan'])->whereNumber('id');
        Route::post('/subscriptions/{id}/portal', [\App\Http\Controllers\Api\Commerce\SubscriptionController::class, 'portal'])->whereNumber('id');

        // Demo Analytics (for demonstration purposes)
        Route::get('/analytics/subscriptions', [\App\Http\Controllers\Api\Commerce\DemoAnalyticsController::class, 'subscriptionAnalytics']);
        Route::get('/analytics/revenue', [\App\Http\Controllers\Api\Commerce\DemoAnalyticsController::class, 'revenueAnalytics']);

        // Tenant Branding Management
        Route::get('/branding', [\App\Http\Controllers\Api\Commerce\TenantBrandingController::class, 'show']);
        Route::put('/branding', [\App\Http\Controllers\Api\Commerce\TenantBrandingController::class, 'update']);
        Route::post('/branding', [\App\Http\Controllers\Api\Commerce\TenantBrandingController::class, 'update']); // For multipart form data
        Route::post('/branding/reset', [\App\Http\Controllers\Api\Commerce\TenantBrandingController::class, 'reset']);

        // Invoice PDF Generation
        Route::get('/invoices/{id}', [\App\Http\Controllers\Api\Commerce\InvoiceController::class, 'show'])->whereNumber('id');
        Route::get('/invoices/{id}/pdf', [\App\Http\Controllers\Api\Commerce\InvoiceController::class, 'generatePdf'])->whereNumber('id')->name('api.commerce.invoices.pdf');
        Route::get('/invoices/{id}/preview', [\App\Http\Controllers\Api\Commerce\InvoiceController::class, 'preview'])->whereNumber('id')->name('api.commerce.invoices.preview');
        Route::get('/invoices/{id}/download', [\App\Http\Controllers\Api\Commerce\InvoiceController::class, 'downloadPdf'])->whereNumber('id')->name('api.commerce.invoices.download');
        Route::post('/invoices/{id}/send-email', [\App\Http\Controllers\Api\Commerce\InvoiceController::class, 'sendEmail'])->whereNumber('id')->name('api.commerce.invoices.email');

        Route::post('/webhooks/stripe', [\App\Http\Controllers\Api\Commerce\CommerceWebhookController::class, 'stripe']);
    });

    // Service Platform - Ticketing Module
    Route::prefix('service')->group(function () {
        Route::apiResource('tickets', \App\Http\Controllers\Api\Service\TicketController::class);
        Route::post('tickets/{ticket}/messages', [\App\Http\Controllers\Api\Service\TicketMessageController::class, 'store']);
        Route::get('tickets/{ticket}/messages', [\App\Http\Controllers\Api\Service\TicketMessageController::class, 'index']);
        Route::get('tickets/{ticket}/messages/{message}', [\App\Http\Controllers\Api\Service\TicketMessageController::class, 'show']);
        Route::put('tickets/{ticket}/messages/{message}', [\App\Http\Controllers\Api\Service\TicketMessageController::class, 'update']);
        Route::delete('tickets/{ticket}/messages/{message}', [\App\Http\Controllers\Api\Service\TicketMessageController::class, 'destroy']);
        Route::post('tickets/{ticket}/messages/{message}/mark-read', [\App\Http\Controllers\Api\Service\TicketMessageController::class, 'markAsRead']);
        Route::get('tickets/{ticket}/messages/stats', [\App\Http\Controllers\Api\Service\TicketMessageController::class, 'stats']);
        
        // Additional ticket operations
        Route::post('tickets/{ticket}/assign', [\App\Http\Controllers\Api\Service\TicketController::class, 'assign']);
        Route::post('tickets/{ticket}/close', [\App\Http\Controllers\Api\Service\TicketController::class, 'close']);
        Route::patch('tickets/{ticket}/status', [\App\Http\Controllers\Api\Service\TicketController::class, 'updateStatus']);
        Route::get('tickets/stats/overview', [\App\Http\Controllers\Api\Service\TicketController::class, 'stats']);
        Route::post('tickets/bulk', [\App\Http\Controllers\Api\Service\TicketController::class, 'bulk']);
        
        // Knowledge Base integration
        Route::get('tickets/{ticket}/article-suggestions', [\App\Http\Controllers\Api\Service\TicketController::class, 'getArticleSuggestionsForTicket']);
        
        // NEW: Ticket-Survey Integration Endpoints
        Route::get('tickets/{ticket}/survey', [\App\Http\Controllers\Api\Service\TicketController::class, 'getTicketSurvey']);
        Route::get('tickets/{ticket}/survey-responses', [\App\Http\Controllers\Api\Service\TicketController::class, 'getTicketSurveyResponses']);
        Route::get('tickets/{ticket}/survey-status', [\App\Http\Controllers\Api\Service\TicketController::class, 'getTicketSurveyStatus']);
        
        // Get current user's tenant ID for form URL generation
        Route::get('tickets/my-tenant-id', [\App\Http\Controllers\Api\Service\TicketController::class, 'getCurrentUserTenantId']);
    });

    // Service Platform - Surveys
    Route::prefix('service')->group(function () {
        Route::get('surveys', [\App\Http\Controllers\Api\Service\SurveyController::class, 'index']);
        Route::post('surveys', [\App\Http\Controllers\Api\Service\SurveyController::class, 'store']);
        
        // NEW: Survey Management Endpoints (must be before {survey} routes to avoid conflicts)
        Route::get('surveys/active', [\App\Http\Controllers\Api\Service\SurveyController::class, 'activeSurveys']);
        Route::get('surveys/post-ticket', [\App\Http\Controllers\Api\Service\SurveyController::class, 'postTicketSurveys']);
        
        Route::get('surveys/{survey}', [\App\Http\Controllers\Api\Service\SurveyController::class, 'show']);
        Route::put('surveys/{survey}', [\App\Http\Controllers\Api\Service\SurveyController::class, 'update']);
        Route::delete('surveys/{survey}', [\App\Http\Controllers\Api\Service\SurveyController::class, 'destroy']);
        Route::get('surveys/{survey}/analytics', [\App\Http\Controllers\Api\Service\SurveyController::class, 'analytics']);
        Route::get('surveys/{survey}/responses', [\App\Http\Controllers\Api\Service\SurveyController::class, 'responses']);
        Route::post('surveys/{survey}/send-reminder', [\App\Http\Controllers\Api\Service\SurveyController::class, 'sendReminder']);
    });

    // NEW: Enhanced Analytics Endpoints
    Route::prefix('service/analytics')->group(function () {
        Route::get('tickets-with-csat', [\App\Http\Controllers\Api\Service\AnalyticsController::class, 'ticketsWithCsat']);
        Route::get('csat-trends', [\App\Http\Controllers\Api\Service\AnalyticsController::class, 'csatTrends']);
        Route::get('agent-performance', [\App\Http\Controllers\Api\Service\AnalyticsController::class, 'agentPerformance']);
        Route::get('survey-summary', [\App\Http\Controllers\Api\Service\AnalyticsController::class, 'surveySummary']);
    });

    // Integration endpoints
    Route::prefix('integration')->group(function () {
        Route::get('snippet', [IntegrationController::class, 'getWidgetSnippet']);
        Route::get('livechat-snippet', [IntegrationController::class, 'getLiveChatSnippet']);
    });

    // Email Integration endpoints
    Route::prefix('service/email')->group(function () {
        Route::get('integrations', [\App\Http\Controllers\Api\Service\EmailIntegrationController::class, 'index']);
        Route::post('integrations', [\App\Http\Controllers\Api\Service\EmailIntegrationController::class, 'store']);
        Route::get('integrations/{emailIntegration}', [\App\Http\Controllers\Api\Service\EmailIntegrationController::class, 'show']);
        Route::put('integrations/{emailIntegration}', [\App\Http\Controllers\Api\Service\EmailIntegrationController::class, 'update']);
        Route::delete('integrations/{emailIntegration}', [\App\Http\Controllers\Api\Service\EmailIntegrationController::class, 'destroy']);
        Route::post('integrations/{emailIntegration}/test', [\App\Http\Controllers\Api\Service\EmailIntegrationController::class, 'test']);
        Route::post('integrations/{emailIntegration}/connect', [\App\Http\Controllers\Api\Service\EmailIntegrationController::class, 'connect']);
        Route::get('integrations/{emailIntegration}/callback', [\App\Http\Controllers\Api\Service\EmailIntegrationController::class, 'callback'])->name('api.service.email.integrations.callback');
        Route::get('integrations/{emailIntegration}/webhook-url', [\App\Http\Controllers\Api\Service\EmailIntegrationController::class, 'webhookUrl']);
    });

    // Live Chat endpoints (authenticated)
    Route::prefix('service/livechat')->group(function () {
        Route::get('conversations', [\App\Http\Controllers\Api\Service\LiveChatController::class, 'getActiveConversations']);
        Route::get('conversations/{conversationId}', [\App\Http\Controllers\Api\Service\LiveChatController::class, 'getConversation']);
        Route::post('conversations/{conversationId}/messages', [\App\Http\Controllers\Api\Service\LiveChatController::class, 'sendMessage']);
        Route::post('conversations/{conversationId}/assign', [\App\Http\Controllers\Api\Service\LiveChatController::class, 'assignConversation']);
        Route::post('conversations/{conversationId}/close', [\App\Http\Controllers\Api\Service\LiveChatController::class, 'closeConversation']);
        Route::post('conversations/{conversationId}/mark-read', [\App\Http\Controllers\Api\Service\LiveChatController::class, 'markAsRead']);
        Route::get('stats', [\App\Http\Controllers\Api\Service\LiveChatController::class, 'getStats']);
        Route::get('agents', [\App\Http\Controllers\Api\Service\LiveChatController::class, 'getAvailableAgents']);
    });

    // Help Center Admin endpoints
    Route::prefix('admin/help')->middleware(['auth:sanctum'])->group(function () {
        // Categories with manual ID handling to avoid route model binding issues with tenant scoping
        Route::get('categories', [\App\Http\Controllers\Api\Help\CategoryController::class, 'index']);
        Route::post('categories', [\App\Http\Controllers\Api\Help\CategoryController::class, 'store']);
        Route::get('categories/{id}', [\App\Http\Controllers\Api\Help\CategoryController::class, 'show']);
        Route::put('categories/{id}', [\App\Http\Controllers\Api\Help\CategoryController::class, 'update']);
        Route::delete('categories/{id}', [\App\Http\Controllers\Api\Help\CategoryController::class, 'destroy']);
        
        // Articles with manual ID handling to avoid route model binding issues with tenant scoping
        Route::get('articles', [\App\Http\Controllers\Api\Help\ArticleController::class, 'index']);
        Route::post('articles', [\App\Http\Controllers\Api\Help\ArticleController::class, 'store']);
        Route::get('articles/{id}', [\App\Http\Controllers\Api\Help\ArticleController::class, 'show']);
        Route::put('articles/{id}', [\App\Http\Controllers\Api\Help\ArticleController::class, 'update']);
        Route::delete('articles/{id}', [\App\Http\Controllers\Api\Help\ArticleController::class, 'destroy']);
        
        // Article attachments
        Route::post('articles/{id}/attachments', [\App\Http\Controllers\Api\Help\ArticleController::class, 'uploadAttachment']);
        Route::get('articles/{id}/attachments', [\App\Http\Controllers\Api\Help\ArticleController::class, 'getAttachments']);
        Route::delete('articles/{id}/attachments/{attachmentId}', [\App\Http\Controllers\Api\Help\ArticleController::class, 'deleteAttachment']);
        
        // Article notifications
        Route::post('articles/{id}/notify', [\App\Http\Controllers\Api\Help\ArticleController::class, 'sendNotification']);
        
        // Article versioning
        Route::get('articles/{id}/versions', [\App\Http\Controllers\Api\Help\ArticleController::class, 'getVersionHistory']);
        Route::post('articles/{id}/restore-version', [\App\Http\Controllers\Api\Help\ArticleController::class, 'restoreToVersion']);
        Route::get('articles/{id}/compare-versions', [\App\Http\Controllers\Api\Help\ArticleController::class, 'compareVersions']);
        
        // Advanced search endpoint (for authenticated users)
        Route::get('search/advanced', [\App\Http\Controllers\Api\Help\ArticleController::class, 'advancedSearch']);
        
        Route::get('stats/overview', [\App\Http\Controllers\Api\Help\StatsController::class, 'overview']);
    });
});

// Public Help Center endpoints (no auth required)
Route::prefix('help')->group(function () {
    // Public article and category endpoints
    Route::get('categories', [\App\Http\Controllers\Api\Help\CategoryController::class, 'publicIndex']);
    Route::get('articles', [\App\Http\Controllers\Api\Help\ArticleController::class, 'publicIndex']);
    Route::get('articles/{slug}', [\App\Http\Controllers\Api\Help\ArticleController::class, 'publicShow']);
    
    // Public feedback endpoint
    Route::post('articles/{id}/feedback', [\App\Http\Controllers\Api\Help\FeedbackController::class, 'store']);
    
    // Embed endpoints
    Route::get('embed/script', [\App\Http\Controllers\Api\Help\EmbedController::class, 'widgetScript']);
    Route::get('embed/help', [\App\Http\Controllers\Api\Help\EmbedController::class, 'publicHelpIndex']);
    
        // Utility endpoint for ticket integration
        Route::post('suggest', [\App\Http\Controllers\Api\Help\ArticleController::class, 'suggestForTicket']);
        
        // Advanced search endpoint (for frontend compatibility)
        Route::get('search/advanced', [\App\Http\Controllers\Api\Help\ArticleController::class, 'advancedSearch'])
            ->middleware('auth:sanctum');
});

// Public Live Chat endpoints (no auth required - uses tenant_id for isolation)
Route::prefix('livechat')->group(function () {
    Route::post('conversations', [\App\Http\Controllers\Api\Service\LiveChatController::class, 'startConversation']);
    Route::post('conversations/{conversationId}/messages', [\App\Http\Controllers\Api\Service\LiveChatController::class, 'sendMessage']);
    Route::get('conversations/{conversationId}', [\App\Http\Controllers\Api\Service\LiveChatController::class, 'getConversation']);

});

// Public Commerce Routes (No Auth Required)
Route::prefix('public/commerce')->group(function () {
    // Public payment link details for checkout (no auth required)
    Route::get('/payment-links/{id}', [\App\Http\Controllers\Api\Commerce\CommercePaymentLinkController::class, 'publicShow'])
        ->whereNumber('id');
    
    // Public subscription endpoints
    Route::get('/subscription-plans', [\App\Http\Controllers\Api\Commerce\PublicSubscriptionController::class, 'listPlans']);
    Route::get('/subscription-plans/{id}', [\App\Http\Controllers\Api\Commerce\PublicSubscriptionController::class, 'getPlanDetails'])->whereNumber('id');
    Route::post('/checkout/create-subscription-session', [\App\Http\Controllers\Api\Commerce\PublicSubscriptionController::class, 'createSubscriptionSession']);
});

// Allow public access to specific payment link endpoints for checkout
Route::get('/commerce/payment-links/{id}/checkout', [\App\Http\Controllers\Api\Commerce\CommercePaymentLinkController::class, 'publicShow'])
    ->whereNumber('id');

// Public payment completion endpoint (no auth required for checkout)
Route::post('/commerce/payment-links/{id}/complete', [\App\Http\Controllers\Api\Commerce\CommercePaymentLinkController::class, 'publicComplete'])
    ->whereNumber('id');


