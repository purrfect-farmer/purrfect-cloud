<?php

return [
    'access_require_membership' => env('ACCESS_REQUIRE_MEMBERSHIP', true),

    'chat_id' => env('TELEGRAM_CHAT_ID', ''),
    'farming_thread_id' => env('TELEGRAM_CHAT_FARMING_THREAD_ID', ''),
    'announcement_thread_id' => env('TELEGRAM_CHAT_ANNOUNCEMENT_THREAD_ID', ''),
    'error_thread_id' => env('TELEGRAM_CHAT_ERROR_THREAD_ID', ''),

    'display_farmer_title' => env('DISPLAY_FARMER_TITLE', false),
    'disable_telegram_messages' => env('DISABLE_TELEGRAM_MESSAGES', false),

    'drops' => [
        'gold-eagle' => [
            'title' => '🥇 Gold Eagle Farmer',
            'enabled' => env('FARMER_GOLD_EAGLE_ENABLED', true),
            'thread_id' => env('FARMER_GOLD_EAGLE_THREAD_ID', '')
        ],
        'hrum' => [
            'title' => '🥠 Hrum Farmer',
            'enabled' => env('FARMER_HRUM_ENABLED', true),
            'thread_id' => env('FARMER_HRUM_THREAD_ID', '')
        ],
        'tsubasa' => [
            'title' => '⚽️ Tsubasa Farmer',
            'enabled' => env('FARMER_TSUBASA_ENABLED', true),
            'thread_id' => env('FARMER_TSUBASA_THREAD_ID', '')
        ],
        'wonton' => [
            'title' => '👨‍🍳 Wonton Farmer',
            'enabled' => env('FARMER_WONTON_ENABLED', true),
            'thread_id' => env('FARMER_WONTON_THREAD_ID', '')
        ],
        'funatic' => [
            'title' => '🤡 Funatic Farmer',
            'enabled' => env('FARMER_FUNATIC_ENABLED', true),
            'thread_id' => env('FARMER_FUNATIC_THREAD_ID', '')
        ],
        'slotcoin' => [
            'title' => '🎰 Slotcoin Farmer',
            'enabled' => env('FARMER_SLOTCOIN_ENABLED', true),
            'thread_id' => env('FARMER_SLOTCOIN_THREAD_ID', '')
        ],
        'dreamcoin' => [
            'title' => '🔋 DreamCoin Farmer',
            'enabled' => env('FARMER_DREAMCOIN_ENABLED', true),
            'thread_id' => env('FARMER_DREAMCOIN_THREAD_ID', '')
        ],
    ]
];
