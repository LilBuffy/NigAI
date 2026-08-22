<?php

return [

    'max_history_messages' => 20,

    'providers' => [

        'gemini' => [
            'label'   => 'Google Gemini (SLOW AS FUCK)',
            'enabled' => true,
            'format'  => 'gemini', // Gemini has its own request/response shape.
            'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={key}',

            // Add as many keys as you want; they're tried in order and
            // api.php automatically skips to the next one on a rate-limit,
            // quota, or auth error.
            'api_keys' => [
                getenv('GEMINI_API_KEY_1') ?: 'FUCKYOU',
            ],

            // 'gemini-flash-latest' always points at Google's current
            // recommended Flash model, so it keeps working even after
            // Google renames/retires specific versions.
            'models' => [
                'gemini-flash-latest' => 'Gemini Flash (latest)',
                'gemini-3.7-flash'    => 'Gemini 3.7 Flash',
                'gemini-2.5-flash'    => 'Gemini 2.5 Flash',
            ],
            'default_model' => 'gemini-flash-latest',

            'generation_config' => [
                'temperature'     => 0.9,
                'maxOutputTokens' => 2048,
            ],
        ],

        'openrouter' => [
            'label'   => 'OpenRouter',
            'enabled' => true,
            'format'  => 'openai', // OpenAI-compatible chat/completions shape.
            'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',

            'api_keys' => [
                getenv('OPENROUTER_API_KEY_1') ?: 'FUCKYOU',
            ],

            'models' => [
                'openai/gpt-4o-mini'          => 'GPT-4o mini',
                'anthropic/claude-3.5-haiku'  => 'Claude 3.5 Haiku',
                'meta-llama/llama-3.3-70b-instruct' => 'Llama 3.3 70B',
            ],
            'default_model' => 'openai/gpt-4o-mini',

            // OpenRouter asks for these but they're optional; leave blank if unsure.
            'extra_headers' => [
                'HTTP-Referer' => '',
                'X-Title'      => 'term_chat',
            ],
        ],

        'groq' => [
            'label'   => 'Groq',
            'enabled' => true,
            'format'  => 'openai',
            'endpoint' => 'https://api.groq.com/openai/v1/chat/completions',

            'api_keys' => [
                getenv('GROQ_API_KEY_1') ?: 'FUCKYOU',
            ],

            'models' => [
                'llama-3.3-70b-versatile' => 'Llama 3.3 70B',
                'llama-3.1-8b-instant'    => 'Llama 3.1 8B (fast)',
            ],
            'default_model' => 'llama-3.3-70b-versatile',
        ],

        'mistral' => [
            'label'   => 'Mistral',
            'enabled' => true,
            'format'  => 'openai',
            'endpoint' => 'https://api.mistral.ai/v1/chat/completions',

            'api_keys' => [
                getenv('MISTRAL_API_KEY_1') ?: 'FUCKYOU',
            ],

            'models' => [
                'mistral-small-latest' => 'Mistral Small',
                'mistral-large-latest' => 'Mistral Large',
            ],
            'default_model' => 'mistral-small-latest',
        ],

        'cerebras' => [
            'label'   => 'Cerebras',
            'enabled' => true,
            'format'  => 'openai',
            'endpoint' => 'https://api.cerebras.ai/v1/chat/completions',

            'api_keys' => [
                getenv('CEREBRAS_API_KEY_1') ?: 'FUCKYOU',
            ],

            // Cerebras runs a small, frequently-changing model lineup —
            // check https://inference-docs.cerebras.ai/models/overview
            // before relying on these long-term.
            'models' => [
                'llama3.1-8b'   => 'Llama 3.1 8B (fastest)',
                'llama-3.3-70b' => 'Llama 3.3 70B',
                'gpt-oss-120b'  => 'GPT-OSS 120B',
            ],
            'default_model' => 'llama3.1-8b',
        ],

        'cohere' => [
            'label'   => 'Cohere',
            'enabled' => true,
            'format'  => 'openai',
            'endpoint' => 'https://api.cohere.ai/compatibility/v1/chat/completions',

            'api_keys' => [
                getenv('COHERE_API_KEY_1') ?: 'FUCKYOU',
            ],

            'models' => [
                'command-a-03-2025'      => 'Command A',
                'command-r-08-2024'      => 'Command R',
                'command-a-plus-05-2026' => 'Command A+',
            ],
            'default_model' => 'command-a-03-2025',
        ],

    ],
];
