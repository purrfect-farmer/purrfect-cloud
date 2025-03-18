<?php

return [
    'access_require_membership' => env('ACCESS_REQUIRE_MEMBERSHIP', true),

    'chat_id' => env('TELEGRAM_CHAT_ID', ''),
    'announcement_thread_id' => env('TELEGRAM_CHAT_ANNOUNCEMENT_THREAD_ID', ''),
    'error_thread_id' => env('TELEGRAM_CHAT_ERROR_THREAD_ID', ''),

    'display_farmer_title' => env('DISPLAY_FARMER_TITLE', false),
    'disable_telegram_messages' => env('DISABLE_TELEGRAM_MESSAGES', false),

    'drops' => [
        'gold-eagle' => [
            'title' => '🥇 Gold Eagle Farmer',
            'enabled' => env('FARMER_GOLD_EAGLE_ENABLED', true),
            'thread_id' => env('FARMER_GOLD_EAGLE_THREAD_ID', ''),
            'telegram_link' => 'https://t.me/gold_eagle_coin_bot/main?startapp=r_ubdOBYN6KX'
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
