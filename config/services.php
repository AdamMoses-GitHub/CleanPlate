<?php
/**
 * External Services Configuration
 * 
 * API keys and settings for third-party services.
 * Prepared for future integrations.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | OpenAI API
    |--------------------------------------------------------------------------
    | 
    | For AI-powered features: tag generation, recipe analysis, etc.
    */
    'openai' => [
        'api_key' => getenv('OPENAI_API_KEY') ?: null,
        'model' => getenv('OPENAI_MODEL') ?: 'gpt-4',
        'max_tokens' => (int)(getenv('OPENAI_MAX_TOKENS') ?: 1000),
        'temperature' => (float)(getenv('OPENAI_TEMPERATURE') ?: 0.7),
        'timeout' => (int)(getenv('OPENAI_TIMEOUT') ?: 30),
        'base_url' => 'https://api.openai.com/v1',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Nutritionix API
    |--------------------------------------------------------------------------
    | 
    | For nutrition data and ingredient analysis.
    */
    'nutrition' => [
        'provider' => getenv('NUTRITION_PROVIDER') ?: 'nutritionix',
        'api_key' => getenv('NUTRITION_API_KEY') ?: null,
        'api_id' => getenv('NUTRITION_API_ID') ?: null,
        'base_url' => getenv('NUTRITION_API_URL') ?: 'https://api.nutritionix.com/v2',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Spoonacular API
    |--------------------------------------------------------------------------
    | 
    | Alternative recipe and nutrition API.
    */
    'spoonacular' => [
        'api_key' => getenv('SPOONACULAR_API_KEY') ?: null,
        'base_url' => 'https://api.spoonacular.com',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | AWS Services
    |--------------------------------------------------------------------------
    | 
    | For S3 storage and other AWS features.
    */
    'aws' => [
        'key' => getenv('AWS_ACCESS_KEY_ID') ?: null,
        'secret' => getenv('AWS_SECRET_ACCESS_KEY') ?: null,
        'region' => getenv('AWS_DEFAULT_REGION') ?: 'us-east-1',
        's3_bucket' => getenv('AWS_S3_BUCKET') ?: null,
    ],
];
