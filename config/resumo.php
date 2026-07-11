<?php

return [
    'groq' => [
        'enabled' => filter_var(env('GROQ_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'api_key' => env('GROQ_API_KEY'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        'timeout' => (int) env('GROQ_TIMEOUT', 60),
        'max_tokens' => (int) env('GROQ_MAX_TOKENS', 1800),
        'butler_max_tokens' => (int) env('GROQ_BUTLER_MAX_TOKENS', 450),
    ],

    'ollama' => [
        'enabled' => filter_var(env('OLLAMA_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.2'),
        'fallback_model' => env('OLLAMA_FALLBACK_MODEL'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 75),
        'context' => (int) env('OLLAMA_CONTEXT', 4096),
        'num_predict' => (int) env('OLLAMA_NUM_PREDICT', 620),
    ],
];
