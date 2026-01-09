<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    /**
     * Email provider options for mail settings.
     *
     * @var array<string, string>
     */
    public static array $emailSettings = [
        'custom' => 'Custom',
        'smtp' => 'SMTP',
        'gmail' => 'Gmail',
        'outlook' => 'Outlook/Office 365',
        'yahoo' => 'Yahoo',
        'sendgrid' => 'SendGrid',
        'amazon' => 'Amazon SES',
        'mailgun' => 'Mailgun',
        'smtp.com' => 'SMTP.com',
        'zohomail' => 'Zoho Mail',
        'mandrill' => 'Mandrill',
        'mailtrap' => 'Mailtrap',
        'sparkpost' => 'SparkPost',
    ];

    /**
     * Get email provider host configuration.
     *
     * @param string $provider
     * @return array<string, string>|null
     */
    public static function getProviderHostConfig(string $provider): ?array
    {
        $configs = [
            'gmail' => [
                'host' => 'smtp.gmail.com',
                'port' => '587',
                'encryption' => 'TLS',
            ],
            'outlook' => [
                'host' => 'smtp.office365.com',
                'port' => '587',
                'encryption' => 'TLS',
            ],
            'yahoo' => [
                'host' => 'smtp.mail.yahoo.com',
                'port' => '587',
                'encryption' => 'TLS',
            ],
            'sendgrid' => [
                'host' => 'smtp.sendgrid.net',
                'port' => '587',
                'encryption' => 'TLS',
            ],
            'amazon' => [
                'host' => 'email-smtp.us-east-1.amazonaws.com',
                'port' => '587',
                'encryption' => 'TLS',
            ],
            'mailgun' => [
                'host' => 'smtp.mailgun.org',
                'port' => '587',
                'encryption' => 'TLS',
            ],
            'smtp.com' => [
                'host' => 'smtp.smtp.com',
                'port' => '587',
                'encryption' => 'TLS',
            ],
            'zohomail' => [
                'host' => 'smtp.zoho.com',
                'port' => '587',
                'encryption' => 'TLS',
            ],
            'mandrill' => [
                'host' => 'smtp.mandrillapp.com',
                'port' => '587',
                'encryption' => 'TLS',
            ],
            'mailtrap' => [
                'host' => 'smtp.mailtrap.io',
                'port' => '2525',
                'encryption' => 'TLS',
            ],
            'sparkpost' => [
                'host' => 'smtp.sparkpostmail.com',
                'port' => '587',
                'encryption' => 'TLS',
            ],
        ];

        return $configs[$provider] ?? null;
    }
}
