<?php

return [
    'enable_telegram_sessions' => env('ENABLE_TELEGRAM_SESSIONS', false),

    'farmer_bot_id' => env(
        'FARMER_BOT_ID',
        '7592929753'
    ),
    'farmer_bot_link' => env(
        'FARMER_BOT_LINK',
        'https://t.me/purrfect_little_bot/app?startapp=purrfect'
    ),
    'telegram_public_key' => env(
        'TELEGRAM_PUBLIC_KEY',
        'e7bf03a2fa4602af4580703d88dda5bb59f32ed8b02a56c187fe7d34caed242d'
    ),

    'chat_id' => env('TELEGRAM_CHAT_ID', ''),
    'announcement_thread_id' => env('TELEGRAM_CHAT_ANNOUNCEMENT_THREAD_ID', ''),
    'error_thread_id' => env('TELEGRAM_CHAT_ERROR_THREAD_ID', ''),

    'display_farmer_title' => env('DISPLAY_FARMER_TITLE', false),
    'disable_telegram_messages' => env('DISABLE_TELEGRAM_MESSAGES', false),

    'log_api_calls' => env('FARMER_LOG_API_CALLS', false),

    'enable_payments' => env('FARMER_ENABLE_PAYMENTS', false),
    'subscription_amount' => env('FARMER_SUBSCRIPTION_AMOUNT', 1550),

    'concurrency_enabled' => env('FARMER_CONCURRENCY_ENABLED', true),
    'concurrency_limit' => env('FARMER_CONCURRENCY_LIMIT', 20),
    'run_in_background' => env('FARMER_RUN_IN_BACKGROUND', true),
    'update_webapp_data' => env('FARMER_UPDATE_WEBAPP_DATA', true),

    'proxy' => [
        'enabled' => env('FARMER_PROXY_ENABLED', false),
        'api_key' => env('FARMER_PROXY_API_KEY', ''),
        'page' => env('FARMER_PROXY_PAGE', 1),
        'page_size' => env('FARMER_PROXY_PAGE_SIZE', 100),
    ],

    'drops' => [
        'unijump' => [
            'title' => '🦄 Unijump Farmer',
            'enabled' => env('FARMER_UNIJUMP_ENABLED', true),
            'thread_id' => env('FARMER_UNIJUMP_THREAD_ID', ''),
            'telegram_link' => 'https://t.me/unijump_bot/game?startapp=ref5859194569569580966',
        ],
        'gold-eagle' => [
            'title' => '🥇 Gold Eagle Farmer',
            'enabled' => env('FARMER_GOLD_EAGLE_ENABLED', true),
            'thread_id' => env('FARMER_GOLD_EAGLE_THREAD_ID', ''),
            'telegram_link' => 'https://t.me/gold_eagle_coin_bot/main?startapp=r_ubdOBYN6KX',
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
        'matchquest' => [
            'title' => '🌾 MatchQuest Farmer',
            'enabled' => env('FARMER_MATCHQUEST_ENABLED', true),
            'thread_id' => env('FARMER_MATCHQUEST_THREAD_ID', ''),
            'telegram_link' => 'https://t.me/MatchQuestBot/start?startapp=775f1cc48a46ce5221f1d9476233dc33'
        ],
        'space-adventure' => [
            'title' => '🚀 Space Adventure Farmer',
            'enabled' => env('FARMER_SPACE_ADVENTURE_ENABLED', true),
            'thread_id' => env('FARMER_SPACE_ADVENTURE_THREAD_ID', ''),
            'telegram_link' => 'https://t.me/spaceadv_game_bot/play?startapp=1147265290'
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
        'digger' => [
            'title' => '🏴‍☠️ Digger Farmer',
            'enabled' => env('FARMER_DIGGER_ENABLED', true),
            'thread_id' => env('FARMER_DIGGER_THREAD_ID', ''),
            'telegram_link' => 'https://t.me/diggerton_bot/dig?startapp=bro1147265290'
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
