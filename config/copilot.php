<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Convergio Copilot Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the platform knowledge base for the Convergio Copilot.
    | It defines all the features, actions, and guidance that the AI assistant
    | can provide to users navigating the RC Convergio platform.
    |
    */

    'features' => [
        'contacts' => [
            'name' => 'Contacts',
            'description' => 'Manage your customer contacts and leads',
            'keywords' => ['contact', 'contacts', 'customer', 'customers', 'lead', 'leads', 'person', 'people'],
            'navigation' => 'Go to Contacts section in the main navigation menu',
            'api_endpoints' => [
                'list' => '/api/contacts',
                'create' => '/api/contacts',
                'search' => '/api/contacts/search'
            ],
            'steps' => [
                'create' => [
                    'Click on "Contacts" in the main navigation menu',
                    'Click the "Add Contact" button (usually a + icon)',
                    'Fill in the contact details (name, email, phone, etc.)',
                    'Add any additional information in the custom fields',
                    'Click "Save" to create the contact'
                ],
                'update' => [
                    'Go to Contacts section',
                    'Find the contact you want to update',
                    'Click on the contact name to open details',
                    'Click "Edit" button',
                    'Make your changes',
                    'Click "Save" to update'
                ],
                'view' => [
                    'Go to Contacts section',
                    'Use the search bar to find specific contacts',
                    'Click on any contact name to view full details',
                    'Use filters to narrow down the list'
                ],
                'search' => [
                    'Go to Contacts section',
                    'Use the search bar at the top',
                    'Type the name, email, or company',
                    'Press Enter or click the search icon',
                    'Review the filtered results'
                ],
                'default' => [
                    'Navigate to the Contacts section',
                    'Use the available actions to manage contacts'
                ]
            ],
            'related_features' => ['companies', 'deals', 'activities', 'tasks']
        ],

        'companies' => [
            'name' => 'Companies',
            'description' => 'Manage company information and organization details',
            'keywords' => ['company', 'companies', 'organization', 'organizations', 'business', 'firm', 'corporation'],
            'navigation' => 'Go to Companies section in the main navigation menu',
            'api_endpoints' => [
                'list' => '/api/companies',
                'create' => '/api/companies',
                'enrich' => '/api/companies/enrich'
            ],
            'steps' => [
                'create' => [
                    'Click on "Companies" in the main navigation menu',
                    'Click the "Add Company" button',
                    'Enter company name and basic information',
                    'Add industry, size, and other details',
                    'Click "Save" to create the company'
                ],
                'update' => [
                    'Go to Companies section',
                    'Find the company you want to update',
                    'Click on the company name',
                    'Click "Edit" button',
                    'Make your changes and save'
                ],
                'enrich' => [
                    'Go to Companies section',
                    'Select the company you want to enrich',
                    'Click "Enrich Data" button',
                    'Review the enriched information',
                    'Save the updated data'
                ],
                'default' => [
                    'Navigate to the Companies section',
                    'Use the available actions to manage companies'
                ]
            ],
            'related_features' => ['contacts', 'deals', 'activities']
        ],

        'deals' => [
            'name' => 'Deals',
            'description' => 'Track sales opportunities and manage your pipeline',
            'keywords' => ['deal', 'deals', 'opportunity', 'opportunities', 'pipeline', 'sales', 'revenue'],
            'navigation' => 'Go to Deals section in the main navigation menu',
            'api_endpoints' => [
                'list' => '/api/deals',
                'create' => '/api/deals',
                'summary' => '/api/deals/summary'
            ],
            'steps' => [
                'create' => [
                    'Click on "Deals" in the main navigation menu',
                    'Click the "Add Deal" button',
                    'Enter deal name and value',
                    'Select the associated contact and company',
                    'Choose the deal stage',
                    'Set close date and click "Save"'
                ],
                'update' => [
                    'Go to Deals section',
                    'Find the deal you want to update',
                    'Click on the deal name',
                    'Make your changes',
                    'Click "Save" to update'
                ],
                'move' => [
                    'Go to Deals section',
                    'Find the deal in the pipeline',
                    'Drag and drop to the new stage',
                    'Or click the deal and change the stage manually'
                ],
                'view' => [
                    'Go to Deals section',
                    'View deals by stage in the pipeline',
                    'Click on any deal to see full details',
                    'Use filters to view specific deals'
                ],
                'default' => [
                    'Navigate to the Deals section',
                    'Use the pipeline view to manage your sales process'
                ]
            ],
            'related_features' => ['contacts', 'companies', 'activities', 'quotes']
        ],

        'campaigns' => [
            'name' => 'Email Campaigns',
            'description' => 'Create and send marketing email campaigns',
            'keywords' => ['campaign', 'campaigns', 'email', 'emails', 'marketing', 'newsletter', 'blast'],
            'navigation' => 'Go to Campaigns section in the main navigation menu',
            'api_endpoints' => [
                'list' => '/api/campaigns',
                'create' => '/api/campaigns',
                'send' => '/api/campaigns/{id}/send'
            ],
            'steps' => [
                'create' => [
                    'Click on "Campaigns" in the main navigation menu',
                    'Click "Create Campaign" button',
                    'Choose email template or create new one',
                    'Design your email content',
                    'Add recipients from your contact lists',
                    'Schedule or send immediately'
                ],
                'send' => [
                    'Go to Campaigns section',
                    'Find the campaign you want to send',
                    'Click on the campaign name',
                    'Review the content and recipients',
                    'Click "Send Now" or schedule for later'
                ],
                'view' => [
                    'Go to Campaigns section',
                    'View all your campaigns',
                    'Click on any campaign to see details',
                    'Check performance metrics and reports'
                ],
                'default' => [
                    'Navigate to the Campaigns section',
                    'Create and manage your email marketing campaigns'
                ]
            ],
            'related_features' => ['contacts', 'lists', 'templates']
        ],

        'tasks' => [
            'name' => 'Tasks',
            'description' => 'Manage your to-do items and follow-ups',
            'keywords' => ['task', 'tasks', 'todo', 'todos', 'reminder', 'reminders', 'follow', 'follow-up'],
            'navigation' => 'Go to Tasks section in the main navigation menu',
            'api_endpoints' => [
                'list' => '/api/tasks',
                'create' => '/api/tasks',
                'complete' => '/api/tasks/{id}/complete'
            ],
            'steps' => [
                'create' => [
                    'Click on "Tasks" in the main navigation menu',
                    'Click "Add Task" button',
                    'Enter task title and description',
                    'Set due date and priority',
                    'Assign to yourself or team member',
                    'Click "Save" to create the task'
                ],
                'complete' => [
                    'Go to Tasks section',
                    'Find the task you want to complete',
                    'Click the checkbox or "Complete" button',
                    'Add completion notes if needed'
                ],
                'view' => [
                    'Go to Tasks section',
                    'View tasks by status (pending, completed)',
                    'Use filters to find specific tasks',
                    'Click on any task to see details'
                ],
                'default' => [
                    'Navigate to the Tasks section',
                    'Manage your daily tasks and follow-ups'
                ]
            ],
            'related_features' => ['contacts', 'deals', 'activities']
        ],

        'quotes' => [
            'name' => 'Quotes',
            'description' => 'Create and manage sales quotes and proposals',
            'keywords' => ['quote', 'quotes', 'proposal', 'proposals', 'estimate', 'estimates', 'pricing'],
            'navigation' => 'Go to Quotes section in the main navigation menu',
            'api_endpoints' => [
                'list' => '/api/quotes',
                'create' => '/api/quotes',
                'send' => '/api/quotes/{id}/send'
            ],
            'steps' => [
                'create' => [
                    'Click on "Quotes" in the main navigation menu',
                    'Click "Create Quote" button',
                    'Select the customer/contact',
                    'Add products and services',
                    'Set pricing and terms',
                    'Generate and review the quote'
                ],
                'send' => [
                    'Go to Quotes section',
                    'Find the quote you want to send',
                    'Click on the quote',
                    'Review all details',
                    'Click "Send Quote" to email it to the customer'
                ],
                'view' => [
                    'Go to Quotes section',
                    'View all your quotes',
                    'Filter by status (draft, sent, accepted)',
                    'Click on any quote to see full details'
                ],
                'default' => [
                    'Navigate to the Quotes section',
                    'Create and manage your sales quotes'
                ]
            ],
            'related_features' => ['contacts', 'companies', 'deals', 'products']
        ],

        'activities' => [
            'name' => 'Activities',
            'description' => 'Track all interactions and communications',
            'keywords' => ['activity', 'activities', 'interaction', 'interactions', 'communication', 'log', 'history'],
            'navigation' => 'Go to Activities section in the main navigation menu',
            'api_endpoints' => [
                'list' => '/api/activities',
                'create' => '/api/activities'
            ],
            'steps' => [
                'view' => [
                    'Go to Activities section',
                    'View all recent activities',
                    'Filter by type (calls, emails, meetings)',
                    'Click on any activity to see details'
                ],
                'create' => [
                    'Go to Activities section',
                    'Click "Log Activity" button',
                    'Select activity type',
                    'Enter details and notes',
                    'Associate with contact/deal if needed',
                    'Save the activity'
                ],
                'default' => [
                    'Navigate to the Activities section',
                    'Track all your business interactions'
                ]
            ],
            'related_features' => ['contacts', 'deals', 'companies']
        ],

        'dashboard' => [
            'name' => 'Dashboard',
            'description' => 'Overview of your business performance and key metrics',
            'keywords' => ['dashboard', 'overview', 'summary', 'metrics', 'performance', 'analytics'],
            'navigation' => 'Go to Dashboard in the main navigation menu',
            'api_endpoints' => [
                'main' => '/api/dashboard',
                'deals_summary' => '/api/deals/summary',
                'campaigns_metrics' => '/api/campaigns/metrics'
            ],
            'steps' => [
                'view' => [
                    'Click on "Dashboard" in the main navigation',
                    'View key performance metrics',
                    'Check recent activities and updates',
                    'Review pipeline and revenue data'
                ],
                'customize' => [
                    'Go to Dashboard',
                    'Click "Customize" or settings icon',
                    'Add or remove widgets as needed',
                    'Arrange widgets by dragging',
                    'Save your dashboard layout'
                ],
                'default' => [
                    'Navigate to the Dashboard',
                    'Get an overview of your business performance'
                ]
            ],
            'related_features' => ['deals', 'campaigns', 'contacts', 'activities']
        ],

        'reports' => [
            'name' => 'Reports & Analytics',
            'description' => 'Generate detailed reports and analyze your business data',
            'keywords' => ['report', 'reports', 'analytics', 'analysis', 'data', 'insights', 'metrics'],
            'navigation' => 'Go to Reports section in the main navigation menu',
            'api_endpoints' => [
                'export' => '/api/deals/export',
                'activities_export' => '/api/activities/export'
            ],
            'steps' => [
                'create' => [
                    'Go to Reports section',
                    'Click "Create Report" button',
                    'Select data source (deals, contacts, campaigns)',
                    'Choose report type and filters',
                    'Generate and view the report'
                ],
                'export' => [
                    'Go to Reports section',
                    'Find the report you want to export',
                    'Click "Export" button',
                    'Choose format (CSV, PDF, Excel)',
                    'Download the exported file'
                ],
                'view' => [
                    'Go to Reports section',
                    'View all available reports',
                    'Click on any report to see details',
                    'Use filters to customize the view'
                ],
                'default' => [
                    'Navigate to the Reports section',
                    'Generate insights from your business data'
                ]
            ],
            'related_features' => ['deals', 'contacts', 'campaigns', 'activities']
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | General Platform Guidance
    |--------------------------------------------------------------------------
    |
    | Default responses and guidance for general platform navigation.
    |
    */

    'general_guidance' => [
        'welcome_message' => 'Welcome to RC Convergio! I\'m your AI assistant and I\'m here to help you navigate and use all the features of your CRM platform.',
        'unknown_feature' => 'I can help you with various features in RC Convergio. Try asking about contacts, deals, campaigns, tasks, or any other feature you need help with.',
        'navigation_help' => 'Use the main navigation menu on the left to access different sections of RC Convergio. Each section has specific tools and features to help you manage your business.',
        'feature_suggestions' => [
            'contacts' => 'Manage your customer contacts and leads',
            'deals' => 'Track your sales opportunities and pipeline',
            'campaigns' => 'Create and send marketing emails',
            'tasks' => 'Organize your daily tasks and follow-ups',
            'companies' => 'Manage company information',
            'quotes' => 'Create and send sales quotes',
            'activities' => 'Track all your business interactions',
            'dashboard' => 'View your business performance overview'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Templates
    |--------------------------------------------------------------------------
    |
    | Templates for generating consistent responses.
    |
    */

    'response_templates' => [
        'feature_help' => 'I\'ll help you with {feature_name}. Here\'s what you need to know:',
        'step_guidance' => 'Follow these steps to {action} {feature_name}:',
        'navigation_help' => 'To access {feature_name}, {navigation_instruction}',
        'related_features' => 'You might also be interested in: {related_features}',
        'help_articles' => 'Here are some helpful articles: {help_articles}'
    ]
];

