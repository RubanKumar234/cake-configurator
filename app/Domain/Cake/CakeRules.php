<?php

namespace App\Domain\Cake;

/**
 * Every domain rule and price lives here as DATA, not as scattered
 * conditionals. Adding a new occasion/size/flavor/etc means adding a row
 * to one of these tables — the engine reads this file, it never hardcodes
 * a rule inline. This is what satisfies "rules live in one place."
 */
class CakeRules
{
    public const OCCASIONS = ['birthday', 'baby_shower', 'wedding'];

    public const SIZES_BY_OCCASION = [
        'birthday'    => [0.5, 1, 2],
        'baby_shower' => [1, 2],
        'wedding'     => [2, 3, 5],
    ];

    public const MAX_TIERS = 4;

    // Keyed by size-as-string so 0.5 doesn't collide with float-key quirks.
    public const SIZE_BASE_PRICE = [
        '0.5' => 400,
        '1'   => 700,
        '2'   => 1200,
        '3'   => 1800,
        '5'   => 2800,
    ];

    public const FLAVORS = ['vanilla', 'chocolate', 'red_velvet', 'fruit'];

    public const FLAVOR_PRICE = [
        'vanilla'    => 0,
        'chocolate'  => 100,
        'red_velvet' => 200,
        'fruit'      => 250,
    ];

    // Minimum tier size required to select Fruit flavor.
    public const FRUIT_MIN_SIZE_KG = 2;

    public const DIETARY = ['none', 'eggless', 'vegan'];

    public const DIETARY_PRICE = [
        'none'    => 0,
        'eggless' => 150,
        'vegan'   => 300,
    ];

    public const FINISHES = ['buttercream', 'whipped', 'fondant'];

    public const FINISH_PRICE = [
        'buttercream' => 0,
        'whipped'     => 100,
        'fondant'     => 400,
    ];

    // Minimum tier size required to select Fondant on a non-wedding cake.
    public const FONDANT_MIN_SIZE_KG = 2;

    public const MESSAGE_PRICE    = 50;
    public const MAX_MESSAGE_LEN  = 30;

    public const CANDLE_PRICE  = 10;
    public const MAX_CANDLES   = 50;

    public const GST_RATE_PERCENT = 18;
}
