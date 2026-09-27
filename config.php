<?php

return [

    'app_name' => 'NigAI',

    'max_history_messages' => 24,
    'max_message_length' => 8000,

    'request_timeout_seconds' => 90,

    'fallback_across_providers' => true,

    'system_prompt' => "You are NigAI, a personal AI assistant created by John David Zamora, also known as Zacuia. Be direct, casual, friendly, practical, and slightly chaotic when appropriate. Match the user's language and energy naturally, including English, Taglish, or casual Filipino. Keep answers clear and concise, but explain complicated topics properly. Use Markdown and practical examples when useful. Avoid corporate language, unnecessary filler, fake enthusiasm, and pretending to know things you don't. Humor is welcome when appropriate. Be useful first, understandable second, and human always.",

    'providers' => [

        'gemini' => [
            'label' => 'Google Gemini',
            'enabled' => true,
            'format' => 'gemini',
            'stream' => true,
            'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={key}',
            'stream_endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:streamGenerateContent?alt=sse&key={key}',
            'api_keys' => [
                getenv('GEMINI_API_KEY_1') ?: 'FUCKYOU',
            ],
            'models' => [
                'gemini-flash-latest' => 'Gemini Flash (latest)',
                'gemini-2.5-flash' => 'Gemini 2.5 Flash',
                'gemini-2.5-pro' => 'Gemini 2.5 Pro',
            ],
            'default_model' => 'gemini-flash-latest',
            'generation_config' => [
                'temperature' => 0.9,
                'maxOutputTokens' => 4096,
            ],
        ],

        'openrouter' => [
            'label' => 'OpenRouter',
            'enabled' => true,
            'format' => 'openai',
            'stream' => true,
            'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
            'api_keys' => [
                getenv('OPENROUTER_API_KEY_1') ?: 'FUCKYOU',
            ],
            'models' => [
                'openai/gpt-4o-mini' => 'GPT-4o mini',
                'anthropic/claude-3.5-haiku' => 'Claude 3.5 Haiku',
                'meta-llama/llama-3.3-70b-instruct' => 'Llama 3.3 70B',
            ],
            'default_model' => 'openai/gpt-4o-mini',
            'extra_headers' => [
                'HTTP-Referer' => '',
                'X-Title' => 'NigAI',
            ],
        ],

        'groq' => [
            'label' => 'Groq',
            'enabled' => true,
            'format' => 'openai',
            'stream' => true,
            'endpoint' => 'https://api.groq.com/openai/v1/chat/completions',
            'api_keys' => [
                getenv('GROQ_API_KEY_1') ?: 'FUCKYOU',
            ],
            'models' => [
                'llama-3.3-70b-versatile' => 'Llama 3.3 70B',
                'llama-3.1-8b-instant' => 'Llama 3.1 8B (fast)',
            ],
            'default_model' => 'llama-3.3-70b-versatile',
        ],

        'mistral' => [
            'label' => 'Mistral',
            'enabled' => true,
            'format' => 'openai',
            'stream' => true,
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
            'label' => 'Cerebras',
            'enabled' => true,
            'format' => 'openai',
            'stream' => true,
            'endpoint' => 'https://api.cerebras.ai/v1/chat/completions',
            'api_keys' => [
                getenv('CEREBRAS_API_KEY_1') ?: 'FUCKYOU',
            ],
            'models' => [
                'llama3.1-8b' => 'Llama 3.1 8B (fastest)',
                'llama-3.3-70b' => 'Llama 3.3 70B',
                'gpt-oss-120b' => 'GPT-OSS 120B',
            ],
            'default_model' => 'llama3.1-8b',
        ],

        'cohere' => [
            'label' => 'Cohere',
            'enabled' => true,
            'format' => 'openai',
            'stream' => true,
            'endpoint' => 'https://api.cohere.ai/compatibility/v1/chat/completions',
            'api_keys' => [
                getenv('COHERE_API_KEY_1') ?: 'FUCKYOU',
            ],
            'models' => [
                'command-a-03-2025' => 'Command A',
                'command-r-08-2024' => 'Command R',
            ],
            'default_model' => 'command-a-03-2025',
        ],

    ],
];
