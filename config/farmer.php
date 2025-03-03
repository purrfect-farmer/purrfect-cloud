<?php

return [
    'display_farmer_title' => env('DISPLAY_FARMER_TITLE', true),
    'drops' => [
        'gold-eagle' => [
            'title' => '🥇 Gold Eagle Farmer',
            'enabled' => env('ENABLE_GOLD_EAGLE_FARMER', true)
        ],
        'hrum' => [
            'title' => '🥠 Hrum Farmer',
            'enabled' => env('ENABLE_HRUM_FARMER', true)
        ],
        'wonton' => [
            'title' => '👨‍🍳 Wonton Farmer',
            'enabled' => env('ENABLE_WONTON_FARMER', true)
        ],
        'tsubasa' => [
            'title' => '⚽️ Tsubasa Farmer',
            'enabled' => env('ENABLE_TSUBASA_FARMER', true)
        ],
        'funatic' => [
            'title' => '🤡 Funatic Farmer',
            'enabled' => env('ENABLE_FUNATIC_FARMER', true)
        ],
        'slotcoin' => [
            'title' => '🎰 Slotcoin Farmer',
            'enabled' => env('ENABLE_SLOTCOIN_FARMER', true)
        ],
        'dreamcoin' => [
            'title' => '🔋 DreamCoin Farmer',
            'enabled' => env('ENABLE_DREAMCOIN_FARMER', true)
        ],
    ]
];
