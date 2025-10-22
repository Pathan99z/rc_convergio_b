<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CMS Module Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains all configuration options for the CMS/Content Platform
    | module. Adjust these settings according to your deployment environment.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | CMS Feature Toggles
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific CMS features. Useful for gradual rollout
    | or disabling features in certain environments.
    |
    */
    'features' => [
        'pages' => env('CMS_PAGES_ENABLED', true),
        'templates' => env('CMS_TEMPLATES_ENABLED', true),
        'personalization' => env('CMS_PERSONALIZATION_ENABLED', true),
        'ab_testing' => env('CMS_AB_TESTING_ENABLED', true),
        'seo_analysis' => env('CMS_SEO_ANALYSIS_ENABLED', true),
        'multi_domain' => env('CMS_MULTI_DOMAIN_ENABLED', true),
        'multi_language' => env('CMS_MULTI_LANGUAGE_ENABLED', true),
        'page_access_control' => env('CMS_PAGE_ACCESS_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Domain Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the default domain settings for CMS pages.
    |
    */
    'default_domain' => [
        'name' => env('CMS_DEFAULT_DOMAIN', 'localhost'),
        'url' => env('APP_URL', 'http://localhost'),
        'ssl_enabled' => env('CMS_DEFAULT_SSL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Language Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the default language settings for CMS pages.
    |
    */
    'default_language' => [
        'code' => env('CMS_DEFAULT_LANGUAGE', 'en'),
        'name' => env('CMS_DEFAULT_LANGUAGE_NAME', 'English'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Page Configuration
    |--------------------------------------------------------------------------
    |
    | Configure page-specific settings including caching, limits, and behavior.
    |
    */
    'pages' => [
        'cache_enabled' => env('CMS_PAGE_CACHE_ENABLED', true),
        'cache_ttl' => env('CMS_PAGE_CACHE_TTL', 3600), // seconds
        'per_page' => env('CMS_PAGES_PER_PAGE', 15),
        'max_per_page' => env('CMS_PAGES_MAX_PER_PAGE', 100),
        'auto_generate_slug' => env('CMS_AUTO_GENERATE_SLUG', true),
        'track_views' => env('CMS_TRACK_PAGE_VIEWS', true),
        'allowed_statuses' => ['draft', 'published', 'scheduled', 'archived'],
        'default_status' => env('CMS_DEFAULT_PAGE_STATUS', 'draft'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Configuration
    |--------------------------------------------------------------------------
    |
    | Configure template system settings.
    |
    */
    'templates' => [
        'types' => [
            'page' => 'Standard Page',
            'landing' => 'Landing Page',
            'blog' => 'Blog Post',
            'email' => 'Email Template',
            'popup' => 'Popup/Modal',
        ],
        'allow_system_templates' => env('CMS_ALLOW_SYSTEM_TEMPLATES', true),
        'max_templates' => env('CMS_MAX_TEMPLATES', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO Configuration
    |--------------------------------------------------------------------------
    |
    | Configure SEO analysis settings and thresholds.
    |
    */
    'seo' => [
        'enabled' => env('CMS_SEO_ENABLED', true),
        'auto_analyze_on_publish' => env('CMS_AUTO_SEO_ANALYZE', true),
        'min_title_length' => env('CMS_SEO_MIN_TITLE_LENGTH', 30),
        'max_title_length' => env('CMS_SEO_MAX_TITLE_LENGTH', 60),
        'min_description_length' => env('CMS_SEO_MIN_DESC_LENGTH', 120),
        'max_description_length' => env('CMS_SEO_MAX_DESC_LENGTH', 160),
        'min_content_length' => env('CMS_SEO_MIN_CONTENT_LENGTH', 300),
        'min_score_threshold' => env('CMS_SEO_MIN_SCORE', 70),
        'keyword_density_max' => env('CMS_SEO_KEYWORD_DENSITY_MAX', 3.0),
        'store_history' => env('CMS_SEO_STORE_HISTORY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | A/B Testing Configuration
    |--------------------------------------------------------------------------
    |
    | Configure A/B testing settings and statistical thresholds.
    |
    */
    'ab_testing' => [
        'enabled' => env('CMS_AB_TESTING_ENABLED', true),
        'min_sample_size' => env('CMS_AB_MIN_SAMPLE_SIZE', 100),
        'confidence_level' => env('CMS_AB_CONFIDENCE_LEVEL', 95),
        'max_concurrent_tests' => env('CMS_AB_MAX_CONCURRENT_TESTS', 10),
        'default_traffic_split' => env('CMS_AB_DEFAULT_TRAFFIC_SPLIT', 50),
        'adaptive_allocation' => env('CMS_AB_ADAPTIVE_ALLOCATION', true),
        'auto_declare_winner' => env('CMS_AB_AUTO_DECLARE_WINNER', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Personalization Configuration
    |--------------------------------------------------------------------------
    |
    | Configure content personalization settings.
    |
    */
    'personalization' => [
        'enabled' => env('CMS_PERSONALIZATION_ENABLED', true),
        'cache_rules' => env('CMS_PERSONALIZATION_CACHE_RULES', true),
        'cache_ttl' => env('CMS_PERSONALIZATION_CACHE_TTL', 300), // seconds
        'max_rules_per_page' => env('CMS_PERSONALIZATION_MAX_RULES', 50),
        'available_operators' => [
            'equals', 'not_equals', 'contains', 'not_contains',
            'starts_with', 'ends_with', 'in', 'not_in',
            'greater_than', 'less_than', 'between',
            'exists', 'not_exists', 'regex'
        ],
        'available_fields' => [
            'device', 'country', 'language', 'user_agent',
            'referrer', 'time_of_day', 'day_of_week',
            'user_type', 'user_segment', 'custom'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Page Access Control Configuration
    |--------------------------------------------------------------------------
    |
    | Configure page access and membership settings.
    |
    */
    'access_control' => [
        'enabled' => env('CMS_ACCESS_CONTROL_ENABLED', true),
        'default_access_type' => env('CMS_DEFAULT_ACCESS_TYPE', 'public'),
        'access_types' => ['public', 'members', 'custom'],
        'cache_access_rules' => env('CMS_CACHE_ACCESS_RULES', true),
        'cache_ttl' => env('CMS_ACCESS_CACHE_TTL', 600), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Domain Configuration
    |--------------------------------------------------------------------------
    |
    | Configure multi-domain hosting settings.
    |
    */
    'multi_domain' => [
        'enabled' => env('CMS_MULTI_DOMAIN_ENABLED', true),
        'auto_detect' => env('CMS_AUTO_DETECT_DOMAIN', true),
        'force_primary_domain' => env('CMS_FORCE_PRIMARY_DOMAIN', false),
        'ssl_required' => env('CMS_SSL_REQUIRED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Language Configuration
    |--------------------------------------------------------------------------
    |
    | Configure multi-language support settings.
    |
    */
    'multi_language' => [
        'enabled' => env('CMS_MULTI_LANGUAGE_ENABLED', true),
        'auto_detect' => env('CMS_AUTO_DETECT_LANGUAGE', true),
        'detect_from_header' => env('CMS_DETECT_LANGUAGE_FROM_HEADER', true),
        'detect_from_url' => env('CMS_DETECT_LANGUAGE_FROM_URL', true),
        'url_prefix_enabled' => env('CMS_LANGUAGE_URL_PREFIX', true),
        'fallback_to_default' => env('CMS_FALLBACK_TO_DEFAULT_LANGUAGE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Configure performance-related settings.
    |
    */
    'performance' => [
        'cache_enabled' => env('CMS_CACHE_ENABLED', true),
        'cache_driver' => env('CMS_CACHE_DRIVER', 'redis'),
        'cdn_enabled' => env('CMS_CDN_ENABLED', false),
        'cdn_url' => env('CMS_CDN_URL', null),
        'minify_html' => env('CMS_MINIFY_HTML', true),
        'minify_css' => env('CMS_MINIFY_CSS', true),
        'minify_js' => env('CMS_MINIFY_JS', true),
        'lazy_load_images' => env('CMS_LAZY_LOAD_IMAGES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configure security-related settings.
    |
    */
    'security' => [
        'rate_limit_enabled' => env('CMS_RATE_LIMIT_ENABLED', true),
        'rate_limit_max_attempts' => env('CMS_RATE_LIMIT_ATTEMPTS', 60),
        'rate_limit_decay_minutes' => env('CMS_RATE_LIMIT_DECAY', 1),
        'require_authentication' => env('CMS_REQUIRE_AUTH', true),
        'allowed_iframe_domains' => env('CMS_ALLOWED_IFRAME_DOMAINS', []),
        'sanitize_content' => env('CMS_SANITIZE_CONTENT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure CMS-specific logging settings.
    |
    */
    'logging' => [
        'enabled' => env('CMS_LOGGING_ENABLED', true),
        'log_channel' => env('CMS_LOG_CHANNEL', 'stack'),
        'log_queries' => env('CMS_LOG_QUERIES', false),
        'log_page_views' => env('CMS_LOG_PAGE_VIEWS', true),
        'log_ab_test_events' => env('CMS_LOG_AB_EVENTS', true),
        'log_personalization_evaluations' => env('CMS_LOG_PERSONALIZATION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure CMS API-specific settings.
    |
    */
    'api' => [
        'prefix' => env('CMS_API_PREFIX', 'api/cms'),
        'version' => env('CMS_API_VERSION', 'v1'),
        'include_meta' => env('CMS_API_INCLUDE_META', true),
        'pagination_enabled' => env('CMS_API_PAGINATION', true),
        'max_results' => env('CMS_API_MAX_RESULTS', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automated backup settings for CMS content.
    |
    */
    'backup' => [
        'enabled' => env('CMS_BACKUP_ENABLED', true),
        'schedule' => env('CMS_BACKUP_SCHEDULE', 'daily'),
        'retention_days' => env('CMS_BACKUP_RETENTION_DAYS', 30),
        'include_media' => env('CMS_BACKUP_INCLUDE_MEDIA', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Configuration
    |--------------------------------------------------------------------------
    |
    | Configure integrations with other modules.
    |
    */
    'integrations' => [
        'analytics_enabled' => env('CMS_ANALYTICS_ENABLED', true),
        'social_sharing_enabled' => env('CMS_SOCIAL_SHARING_ENABLED', true),
        'email_campaigns_enabled' => env('CMS_EMAIL_CAMPAIGNS_ENABLED', true),
        'crm_integration_enabled' => env('CMS_CRM_INTEGRATION_ENABLED', true),
    ],

];


