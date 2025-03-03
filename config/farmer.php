<?php

return [
    'display_farmer_title' => env('DISPLAY_FARMER_TITLE', false),
    'drops' => [
        'gold-eagle' => [
            'title' => '🥇 Gold Eagle Farmer',
            'enabled' => env('ENABLE_GOLD_EAGLE_FARMER', false)
        ],
        'hrum' => [
            'title' => '🥠 Hrum Farmer',
            'enabled' => env('ENABLE_HRUM_FARMER', false)
        ],
        'wonton' => [
            'title' => '👨‍🍳 Wonton Farmer',
            'enabled' => env('ENABLE_WONTON_FARMER', false)
        ],
        'funatic' => [
            'title' => '🤡 Funatic Farmer',
            'enabled' => env('ENABLE_FUNATIC_FARMER', false)
        ],
        'slotcoin' => [
            'title' => '🎰 Slotcoin Farmer',
            'enabled' => env('ENABLE_SLOTCOIN_FARMER', false)
        ],
        'dreamcoin' => [
            'title' => '🔋 DreamCoin Farmer',
            'enabled' => env('ENABLE_DREAMCOIN_FARMER', false)
        ],
    ]
];
