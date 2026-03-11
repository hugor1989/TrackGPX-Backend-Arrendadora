<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenPay Configuration
    |--------------------------------------------------------------------------
    */

    'merchant_id' => env('OPENPAY_MERCHANT_ID'),
    'private_key' => env('OPENPAY_PRIVATE_KEY'),
    'public_key' => env('OPENPAY_PUBLIC_KEY'),
    
    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    | Valores: 'production' o 'sandbox'
    */
    'environment' => env('OPENPAY_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    */
    'api_url' => [
        'sandbox' => 'https://sandbox-api.openpay.mx/v1',
        'production' => 'https://api.openpay.mx/v1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de reintentos
    |--------------------------------------------------------------------------
    */
    'max_retry_attempts' => env('OPENPAY_MAX_RETRIES', 3),
    'retry_delay_hours' => 24,

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    */
    'webhook_url' => env('OPENPAY_WEBHOOK_URL'),
    'webhook_user' => env('OPENPAY_WEBHOOK_USER'),
    'webhook_password' => env('OPENPAY_WEBHOOK_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Configuración de cargos
    |--------------------------------------------------------------------------
    */
    'currency' => 'MXN',
    'charge_description' => 'Servicio de rastreo GPS - TrackGPX',
    
    /*
    |--------------------------------------------------------------------------
    | Logs
    |--------------------------------------------------------------------------
    */
    'log_requests' => env('OPENPAY_LOG_REQUESTS', true),
    'log_channel' => env('OPENPAY_LOG_CHANNEL', 'daily'),

];