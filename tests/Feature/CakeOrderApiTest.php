<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CakeOrderApiTest extends TestCase
{
    use RefreshDatabase;

    /** The server must ignore any price/valid flag the client sends and
     *  compute its own number instead. */
    public function test_server_ignores_tampered_client_price(): void
    {
        $payload = [
            'occasion'    => 'birthday',
            'tiers'       => [1],
            'flavor'      => 'chocolate',
            'dietary'     => 'eggless',
            'finish'      => 'buttercream',
            'has_message' => true,
            'message'     => 'Happy Birthday!',
            'candles'     => 10,
            // client attempts to lie about price/validity:
            'price'       => 1,
            'valid'       => true,
        ];

        $response = $this->postJson('/api/cake-orders', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('price.subtotal', 1100)
            ->assertJsonPath('price.gst', 198)
            ->assertJsonPath('price.total', 1298);
    }

    public function test_invalid_combination_is_rejected_with_422(): void
    {
        $payload = [
            'occasion' => 'baby_shower',
            'tiers'    => [1],
            'flavor'   => 'red_velvet', // not allowed for baby shower
            'dietary'  => 'none',
            'finish'   => 'buttercream',
            'candles'  => 0,
        ];

        $response = $this->postJson('/api/cake-orders', $payload);

        $response->assertStatus(422)->assertJsonPath('valid', false);
    }

    public function test_quote_endpoint_does_not_persist(): void
    {
        $payload = [
            'occasion' => 'birthday',
            'tiers'    => [1],
            'flavor'   => 'vanilla',
            'dietary'  => 'none',
            'finish'   => 'buttercream',
            'candles'  => 0,
        ];

        $response = $this->postJson('/api/cake-orders/quote', $payload);

        $response->assertStatus(200)->assertJsonPath('valid', true);
        $this->assertDatabaseCount('cake_orders', 0);
    }
}
