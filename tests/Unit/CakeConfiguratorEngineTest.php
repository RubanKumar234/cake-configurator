<?php

namespace Tests\Unit;

use App\Domain\Cake\CakeConfiguratorEngine;
use App\Domain\Cake\Exceptions\InvalidCakeConfigException;
use PHPUnit\Framework\TestCase;

class CakeConfiguratorEngineTest extends TestCase
{
    private CakeConfiguratorEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new CakeConfiguratorEngine();
    }

    /** The worked example from the spec must reproduce exactly. */
    public function test_worked_example_pricing(): void
    {
        $spec = [
            'occasion'    => 'birthday',
            'tiers'       => [1],
            'flavor'      => 'chocolate',
            'dietary'     => 'eggless',
            'finish'      => 'buttercream',
            'has_message' => true,
            'message'     => 'Happy Birthday!',
            'candles'     => 10,
        ];

        $this->assertTrue($this->engine->isValid($spec));

        $price = $this->engine->price($spec);

        $this->assertSame(1100, $price['subtotal']);
        $this->assertSame(198, $price['gst']);
        $this->assertSame(1298, $price['total']);
    }

    /** Constraint rejection: Fruit flavor requires size >= 2kg. */
    public function test_fruit_flavor_rejected_below_2kg(): void
    {
        $spec = [
            'occasion'    => 'birthday',
            'tiers'       => [1],
            'flavor'      => 'fruit',
            'dietary'     => 'none',
            'finish'      => 'buttercream',
            'has_message' => false,
            'candles'     => 0,
        ];

        $errors = $this->engine->validate($spec);

        $this->assertNotEmpty($errors);
        $this->assertFalse($this->engine->isValid($spec));
    }

    /** A second, independent rejection case: Red Velvet is banned for Baby Shower. */
    public function test_red_velvet_rejected_for_baby_shower(): void
    {
        $spec = [
            'occasion'    => 'baby_shower',
            'tiers'       => [1],
            'flavor'      => 'red_velvet',
            'dietary'     => 'none',
            'finish'      => 'buttercream',
            'has_message' => false,
            'candles'     => 0,
        ];

        $this->assertFalse($this->engine->isValid($spec));
    }

    /**
     * Downstream invalidation: switching Wedding -> Birthday must clear the
     * tier count and the Fondant/Color-theme selection rather than leaving
     * them stale. Also proves that even if a stale combination somehow
     * still reached the server, validate() would reject it independently.
     */
    public function test_occasion_change_resets_downstream_fields(): void
    {
        $wedding = [
            'occasion'    => 'wedding',
            'tiers'       => [2, 3, 5, 5],
            'flavor'      => 'vanilla',
            'dietary'     => 'none',
            'finish'      => 'fondant',
            'color_theme' => 'gold',
            'has_message' => false,
            'candles'     => 0,
        ];

        $this->assertTrue($this->engine->isValid($wedding));

        $afterSwitch = $this->engine->resetDownstreamForOccasionChange($wedding, 'birthday');

        $this->assertSame('birthday', $afterSwitch['occasion']);
        $this->assertCount(1, $afterSwitch['tiers']);
        $this->assertArrayNotHasKey('finish', $afterSwitch);
        $this->assertArrayNotHasKey('color_theme', $afterSwitch);

        // Backstop: if the reset had NOT happened and the stale combination
        // reached the server anyway, it must still be rejected outright.
        $stale = $wedding;
        $stale['occasion'] = 'birthday'; // tiers/finish left stale on purpose
        $this->assertFalse($this->engine->isValid($stale));
    }

    /** price() must refuse to compute a number for an invalid spec. */
    public function test_price_throws_on_invalid_spec(): void
    {
        $this->expectException(InvalidCakeConfigException::class);

        $this->engine->price([
            'occasion'    => 'baby_shower',
            'tiers'       => [1],
            'flavor'      => 'red_velvet', // invalid for baby shower
            'dietary'     => 'none',
            'finish'      => 'buttercream',
            'has_message' => false,
            'candles'     => 0,
        ]);
    }
}
