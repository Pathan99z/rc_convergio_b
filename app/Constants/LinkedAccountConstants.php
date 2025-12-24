<?php

namespace App\Constants;

class LinkedAccountConstants
{
    // Product IDs
    const PRODUCT_CONSOLE = 1;
    const PRODUCT_FUTURE_PRODUCT_1 = 2;
    const PRODUCT_FUTURE_PRODUCT_2 = 3;
    const PRODUCT_FUTURE_PRODUCT_3 = 4;
    
    // Integration Types
    const TYPE_SSO_ONLY = 1;        // Only SSO login
    const TYPE_PASSWORD_SYNC = 2;    // Password sync only
    const TYPE_BOTH = 3;             // Both SSO and password sync
    const TYPE_API_TOKEN = 4;        // API token based
    
    // Status Values
    const STATUS_ACTIVE = 1;
    const STATUS_PENDING = 2;
    const STATUS_FAILED = 3;
    const STATUS_DISABLED = 4;
    
    // Product Names (for reference)
    public static function getProductName(int $productId): string
    {
        return match($productId) {
            self::PRODUCT_CONSOLE => 'Console',
            self::PRODUCT_FUTURE_PRODUCT_1 => 'Future Product 1',
            self::PRODUCT_FUTURE_PRODUCT_2 => 'Future Product 2',
            self::PRODUCT_FUTURE_PRODUCT_3 => 'Future Product 3',
            default => 'Unknown Product',
        };
    }
}


