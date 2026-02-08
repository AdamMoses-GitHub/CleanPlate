<?php
/**
 * Mail Configuration
 * 
 * Email service settings for notifications and communications.
 * Prepared for future use.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Mail Driver
    |--------------------------------------------------------------------------
    | 
    | Supported: smtp, sendgrid, mailgun
    */
    'default' => getenv('MAIL_MAILER') ?: 'smtp',
    
    /*
    |--------------------------------------------------------------------------
    | Mail Drivers
    |--------------------------------------------------------------------------
    */
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => getenv('MAIL_HOST') ?: 'smtp.mailtrap.io',
            'port' => (int)(getenv('MAIL_PORT') ?: 2525),
            'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
            'username' => getenv('MAIL_USERNAME') ?: null,
            'password' => getenv('MAIL_PASSWORD') ?: null,
            'timeout' => null,
        ],
        
        'sendgrid' => [
            'transport' => 'sendgrid',
            'api_key' => getenv('SENDGRID_API_KEY') ?: null,
        ],
        
        'mailgun' => [
            'transport' => 'mailgun',
            'domain' => getenv('MAILGUN_DOMAIN') ?: null,
            'secret' => getenv('MAILGUN_SECRET') ?: null,
            'endpoint' => getenv('MAILGUN_ENDPOINT') ?: 'api.mailgun.net',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | From Email Address
    |--------------------------------------------------------------------------
    */
    'from' => [
        'address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@cleanplate.app',
        'name' => getenv('MAIL_FROM_NAME') ?: 'CleanPlate',
    ],
];
