<?php

if (!function_exists('formatCurrency')) {
    /**
     * Format currency amount with correct symbol based on currency code.
     * 
     * @param float|string $amount The amount to format
     * @param string $currency The currency code (e.g., 'USD', 'EUR', 'ZAR')
     * @return string Formatted currency string
     */
    function formatCurrency($amount, $currency = 'USD'): string
    {
        $currency = strtoupper($currency);
        $amount = (float) $amount;
        $formatted = number_format($amount, 2, '.', ',');

        // Currency symbols mapping
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'ZAR' => 'R',
            'INR' => '₹',
            'JPY' => '¥',
            'CNY' => '¥',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'CHF' => 'CHF',
            'NZD' => 'NZ$',
            'SGD' => 'S$',
            'HKD' => 'HK$',
            'SEK' => 'kr',
            'NOK' => 'kr',
            'DKK' => 'kr',
            'PLN' => 'zł',
            'MXN' => '$',
            'BRL' => 'R$',
            'AED' => 'د.إ',
            'SAR' => '﷼',
            'THB' => '฿',
            'MYR' => 'RM',
            'PHP' => '₱',
            'IDR' => 'Rp',
            'VND' => '₫',
            'KRW' => '₩',
            'TRY' => '₺',
            'RUB' => '₽',
            'ILS' => '₪',
        ];

        $symbol = $symbols[$currency] ?? $currency . ' ';

        // For some currencies, symbol comes after the amount
        if (in_array($currency, ['EUR', 'GBP', 'CHF'])) {
            return $formatted . ' ' . $symbol;
        }

        // For most currencies, symbol comes before
        return $symbol . $formatted;
    }
}

