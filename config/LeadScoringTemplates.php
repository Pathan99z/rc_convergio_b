<?php

return [
    'marketing_engagement' => [
        'name' => 'Marketing Engagement',
        'description' => 'Standard marketing engagement scoring for lead qualification',
        'category' => 'marketing',
        'icon' => 'email',
        'color' => '#3B82F6',
        'rules' => [
            [
                'name' => 'Email Open',
                'description' => 'Contact opens an email',
                'event' => 'email_open',
                'points' => 5,
                'priority' => 1,
                'is_active' => true
            ],
            [
                'name' => 'Email Click',
                'description' => 'Contact clicks a link in an email',
                'event' => 'email_click',
                'points' => 10,
                'priority' => 2,
                'is_active' => true
            ],
            [
                'name' => 'Form Submission',
                'description' => 'Contact submits a form',
                'event' => 'form_submission',
                'points' => 15,
                'priority' => 3,
                'is_active' => true
            ],
            [
                'name' => 'Page Visit',
                'description' => 'Contact visits a page',
                'event' => 'page_visit',
                'points' => 3,
                'priority' => 1,
                'is_active' => true
            ],
            [
                'name' => 'File Download',
                'description' => 'Contact downloads a file',
                'event' => 'file_download',
                'points' => 20,
                'priority' => 4,
                'is_active' => true
            ],
            [
                'name' => 'Email Unsubscribe',
                'description' => 'Contact unsubscribes from emails',
                'event' => 'email_unsubscribe',
                'points' => -10,
                'priority' => 1,
                'is_active' => true
            ]
        ]
    ],
    
    'sales_activity' => [
        'name' => 'Sales Activity',
        'description' => 'Sales-focused lead scoring for sales team',
        'category' => 'sales',
        'icon' => 'briefcase',
        'color' => '#10B981',
        'rules' => [
            [
                'name' => 'Deal Created',
                'description' => 'A deal is created for the contact',
                'event' => 'deal_created',
                'points' => 25,
                'priority' => 5,
                'is_active' => true
            ],
            [
                'name' => 'Deal Updated',
                'description' => 'A deal is updated',
                'event' => 'deal_updated',
                'points' => 10,
                'priority' => 3,
                'is_active' => true
            ],
            [
                'name' => 'Meeting Scheduled',
                'description' => 'A meeting is scheduled with the contact',
                'event' => 'meeting_scheduled',
                'points' => 30,
                'priority' => 6,
                'is_active' => true
            ],
            [
                'name' => 'Contact Created',
                'description' => 'Contact is created in the system',
                'event' => 'contact_created',
                'points' => 10,
                'priority' => 1,
                'is_active' => true
            ],
            [
                'name' => 'Deal Closed Won',
                'description' => 'A deal is closed as won',
                'event' => 'deal_closed_won',
                'points' => 50,
                'priority' => 10,
                'is_active' => true
            ],
            [
                'name' => 'Deal Closed Lost',
                'description' => 'A deal is closed as lost',
                'event' => 'deal_closed_lost',
                'points' => -5,
                'priority' => 1,
                'is_active' => true
            ]
        ]
    ],
    
    'website_behavior' => [
        'name' => 'Website Behavior',
        'description' => 'Website engagement and behavior scoring',
        'category' => 'website',
        'icon' => 'globe',
        'color' => '#F59E0B',
        'rules' => [
            [
                'name' => 'Homepage Visit',
                'description' => 'Contact visits the homepage',
                'event' => 'page_visit',
                'points' => 2,
                'priority' => 1,
                'is_active' => true,
                'conditions' => [
                    ['field' => 'page', 'operator' => 'equals', 'value' => 'homepage']
                ]
            ],
            [
                'name' => 'Pricing Page Visit',
                'description' => 'Contact visits the pricing page',
                'event' => 'page_visit',
                'points' => 8,
                'priority' => 3,
                'is_active' => true,
                'conditions' => [
                    ['field' => 'page', 'operator' => 'contains', 'value' => 'pricing']
                ]
            ],
            [
                'name' => 'Contact Page Visit',
                'description' => 'Contact visits the contact page',
                'event' => 'page_visit',
                'points' => 12,
                'priority' => 4,
                'is_active' => true,
                'conditions' => [
                    ['field' => 'page', 'operator' => 'contains', 'value' => 'contact']
                ]
            ],
            [
                'name' => 'Product Page Visit',
                'description' => 'Contact visits a product page',
                'event' => 'page_visit',
                'points' => 6,
                'priority' => 2,
                'is_active' => true,
                'conditions' => [
                    ['field' => 'page', 'operator' => 'contains', 'value' => 'product']
                ]
            ],
            [
                'name' => 'Resource Download',
                'description' => 'Contact downloads a resource',
                'event' => 'file_download',
                'points' => 15,
                'priority' => 4,
                'is_active' => true
            ],
            [
                'name' => 'Multiple Page Visits',
                'description' => 'Contact visits multiple pages in one session',
                'event' => 'page_visit',
                'points' => 5,
                'priority' => 2,
                'is_active' => true,
                'conditions' => [
                    ['field' => 'visit_count', 'operator' => 'greater_than', 'value' => 3]
                ]
            ]
        ]
    ],
    
    'email_engagement' => [
        'name' => 'Email Engagement',
        'description' => 'Email marketing engagement scoring',
        'category' => 'email',
        'icon' => 'mail',
        'color' => '#8B5CF6',
        'rules' => [
            [
                'name' => 'Email Open',
                'description' => 'Contact opens an email',
                'event' => 'email_open',
                'points' => 3,
                'priority' => 1,
                'is_active' => true
            ],
            [
                'name' => 'Email Click',
                'description' => 'Contact clicks a link in an email',
                'event' => 'email_click',
                'points' => 8,
                'priority' => 2,
                'is_active' => true
            ],
            [
                'name' => 'Email Reply',
                'description' => 'Contact replies to an email',
                'event' => 'email_reply',
                'points' => 25,
                'priority' => 6,
                'is_active' => true
            ],
            [
                'name' => 'Email Forward',
                'description' => 'Contact forwards an email',
                'event' => 'email_forward',
                'points' => 15,
                'priority' => 4,
                'is_active' => true
            ],
            [
                'name' => 'Email Bounce',
                'description' => 'Email bounces (negative score)',
                'event' => 'email_bounce',
                'points' => -5,
                'priority' => 1,
                'is_active' => true
            ],
            [
                'name' => 'Email Unsubscribe',
                'description' => 'Contact unsubscribes (negative score)',
                'event' => 'email_unsubscribe',
                'points' => -15,
                'priority' => 1,
                'is_active' => true
            ]
        ]
    ],
    
    'event_attendance' => [
        'name' => 'Event Attendance',
        'description' => 'Event and webinar engagement scoring',
        'category' => 'events',
        'icon' => 'calendar',
        'color' => '#EF4444',
        'rules' => [
            [
                'name' => 'Event Registration',
                'description' => 'Contact registers for an event',
                'event' => 'event_registration',
                'points' => 20,
                'priority' => 4,
                'is_active' => true
            ],
            [
                'name' => 'Event Attendance',
                'description' => 'Contact attends an event',
                'event' => 'event_attendance',
                'points' => 35,
                'priority' => 7,
                'is_active' => true
            ],
            [
                'name' => 'Webinar Registration',
                'description' => 'Contact registers for a webinar',
                'event' => 'webinar_registration',
                'points' => 15,
                'priority' => 3,
                'is_active' => true
            ],
            [
                'name' => 'Webinar Attendance',
                'description' => 'Contact attends a webinar',
                'event' => 'webinar_attendance',
                'points' => 25,
                'priority' => 5,
                'is_active' => true
            ],
            [
                'name' => 'Event No-Show',
                'description' => 'Contact doesn\'t show up for registered event',
                'event' => 'event_no_show',
                'points' => -5,
                'priority' => 1,
                'is_active' => true
            ]
        ]
    ]
];
