<?php

return [
    'crawler' => [
        'user_agent' => 'SEO-Crawler/1.0',
        'timeout' => 30,
        'max_pages' => 100,
        'delay_between_requests' => 1, // seconds
    ],
    
    'keywords' => [
        'min_length' => 3,
        'max_length' => 50,
        'excluded_words' => [
            'this', 'that', 'with', 'from', 'they', 'were', 'been', 'have',
            'their', 'said', 'each', 'which', 'them', 'more', 'very', 'what',
            'know', 'just', 'first', 'into', 'over', 'think', 'also', 'your',
            'work', 'life', 'only', 'can', 'still', 'should', 'after', 'being',
            'now', 'made', 'before', 'here', 'through', 'when', 'where', 'much',
            'some', 'these', 'many', 'then', 'well', 'were', 'details', 'product',
            'will', 'would', 'could', 'should', 'about', 'into', 'through', 'during',
            'before', 'after', 'above', 'below', 'up', 'down', 'in', 'out', 'on',
            'off', 'over', 'under', 'again', 'further', 'then', 'once', 'here',
            'there', 'when', 'where', 'why', 'how', 'all', 'any', 'both', 'each',
            'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not',
            'only', 'own', 'same', 'so', 'than', 'too', 'very', 's', 't', 'can',
            'will', 'just', 'don', 'should', 'now'
        ]
    ],
    
    'scoring' => [
        'meta_description_weight' => 10,
        'h1_tag_weight' => 15,
        'title_tag_weight' => 5,
        'internal_links_weight' => 5,
        'image_alt_text_weight' => 5,
        'page_speed_weight' => 10,
        'word_count_weight' => 3,
        'heading_structure_weight' => 5
    ],

    'analysis' => [
        'max_issues_per_page' => 50,
        'min_word_count' => 100,
        'max_title_length' => 60,
        'max_meta_description_length' => 160,
        'min_meta_description_length' => 120,
        'max_h1_count' => 1,
        'min_internal_links' => 3,
        'max_load_time' => 3.0, // seconds
    ],

    'crawl' => [
        'max_depth' => 3,
        'respect_robots_txt' => true,
        'follow_redirects' => true,
        'max_redirects' => 5,
        'crawl_delay' => 1, // seconds between requests
        'timeout' => 30, // seconds
        'max_file_size' => 10485760, // 10MB
        'allowed_content_types' => [
            'text/html',
            'text/plain',
            'application/xhtml+xml'
        ]
    ],

    'reports' => [
        'retention_days' => 90,
        'max_reports_per_user' => 10,
        'auto_cleanup' => true
    ]
];
