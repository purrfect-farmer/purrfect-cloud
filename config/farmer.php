<?php

return [
    'enable_telegram_sessions' => env('ENABLE_TELEGRAM_SESSIONS', false),

    'chat_id' => env('TELEGRAM_CHAT_ID', ''),
    'announcement_thread_id' => env('TELEGRAM_CHAT_ANNOUNCEMENT_THREAD_ID', ''),
    'error_thread_id' => env('TELEGRAM_CHAT_ERROR_THREAD_ID', ''),

    'display_farmer_title' => env('DISPLAY_FARMER_TITLE', false),
    'disable_telegram_messages' => env('DISABLE_TELEGRAM_MESSAGES', false),

    'log_api_calls' => env('FARMER_LOG_API_CALLS', true),

    'enable_payments' => env('FARMER_ENABLE_PAYMENTS', true),
    'subscription_amount' => env('FARMER_SUBSCRIPTION_AMOUNT', 1550),

    'proxy' => [
        'enabled' => env('FARMER_PROXY_ENABLED', false),
        'api_key' => env('FARMER_PROXY_API_KEY', ''),
        'page' => env('FARMER_PROXY_PAGE', 1),
        'page_size' => env('FARMER_PROXY_PAGE_SIZE', 100),
    ],

    'drops' => [
        'gold-eagle' => [
            'title' => '🥇 Gold Eagle Farmer',
            'enabled' => env('FARMER_GOLD_EAGLE_ENABLED', true),
            'thread_id' => env('FARMER_GOLD_EAGLE_THREAD_ID', ''),
            'telegram_link' => 'https://t.me/gold_eagle_coin_bot/main?startapp=r_ubdOBYN6KX',
            'options' => [
                'automatic_claim' => env('FARMER_GOLD_EAGLE_AUTOMATIC_CLAIM', false)
            ]
        ],
        'digger' => [
            'title' => '🏴‍☠️ Digger Farmer',
            'enabled' => env('FARMER_DIGGER_ENABLED', true),
            'thread_id' => env('FARMER_DIGGER_THREAD_ID', ''),
            'telegram_link' => 'https://t.me/diggerton_bot/dig?startapp=bro1147265290'
        ],
        'matchquest' => [
            'title' => '🌾 MatchQuest Farmer',
            'enabled' => env('FARMER_MATCHQUEST_ENABLED', true),
            'thread_id' => env('FARMER_MATCHQUEST_THREAD_ID', ''),
            'telegram_link' => 'https://t.me/MatchQuestBot/start?startapp=775f1cc48a46ce5221f1d9476233dc33'
        ],
        'hrum' => [
            'title' => '🥠 Hrum Farmer',
            'enabled' => env('FARMER_HRUM_ENABLED', true),
            'thread_id' => env('FARMER_HRUM_THREAD_ID', ''),
            'telegram_link' => 'https://t.me/hrummebot/game?startapp=ref1147265290'
        ],
        'tsubasa' => [
            'title' => '⚽️ Tsubasa Farmer',
            'enabled' => env('FARMER_TSUBASA_ENABLED', true),
            'thread_id' => env('FARMER_TSUBASA_THREAD_ID', ''),
            'telegram_link' => 'https://t.me/TsubasaRivalsBot/start?startapp=inviter_id-1147265290'
        ],
        'wonton' => [
            'title' => '👨‍🍳 Wonton Farmer',
            'enabled' => env('FARMER_WONTON_ENABLED', true),
            'thread_id' => env('FARMER_WONTON_THREAD_ID', ''),
            'telegram_link' => 'https://t.me/WontonOrgBot/gameapp?startapp=referralCode=K45JQRG7'
        ],
        'funatic' => [
            'title' => '🤡 Funatic Farmer',
            'enabled' => env('FARMER_FUNATIC_ENABLED', true),
            'thread_id' => env('FARMER_FUNATIC_THREAD_ID', ''),
            'telegram_link' => 'https://t.me/LuckyFunaticBot/lucky_funatic?startapp=1147265290'
        ],
        'slotcoin' => [
            'title' => '🎰 Slotcoin Farmer',
            'enabled' => env('FARMER_SLOTCOIN_ENABLED', true),
            'thread_id' => env('FARMER_SLOTCOIN_THREAD_ID', ''),
            'telegram_link' => 'https://t.me/SlotCoinApp_bot/app?startapp=eyJyZWZfY29kZSI6ImEyZGQtNjBmNyIsInV0bV9pZCI6InJlZmZlcmFsX2xpbmtfc2hhcmUifQ=='
        ],
        'dreamcoin' => [
            'title' => '🔋 DreamCoin Farmer',
            'enabled' => env('FARMER_DREAMCOIN_ENABLED', true),
            'thread_id' => env('FARMER_DREAMCOIN_THREAD_ID', ''),
            'telegram_link' => 'https://t.me/DreamCoinOfficial_bot?start=1147265290'
        ],
    ]
];
