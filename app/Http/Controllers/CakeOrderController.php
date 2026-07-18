<?php

namespace App\Http\Controllers;

use App\Domain\Cake\CakeConfiguratorEngine;
use App\Models\CakeOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CakeOrderController extends Controller
{
    private CakeConfiguratorEngine $engine;

    public function __construct(CakeConfiguratorEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * Live preview used by the frontend on every field change. No
     * persistence. This is what lets the frontend show a running price
     * without ever computing money itself — the engine is the only place
     * pricing logic exists, client or server.
     */
    public function quote(Request $request): JsonResponse
    {
        $spec = $this->normalizeSpec($request);
        $errors = $this->engine->validate($spec);

        if ($errors !== []) {
            return response()->json(['valid' => false, 'errors' => $errors], 422);
        }

        return response()->json(['valid' => true, 'price' => $this->engine->price($spec)]);
    }

    /**
     * Final submit. Deliberately re-validates and re-prices from scratch —
     * any 'price' or 'valid' field the client sends is read here only to
     * demonstrate that it is ignored (see normalizeSpec: it's never even
     * extracted). A tampered POST gets the real, server-computed number
     * back, or a 422 if the underlying spec is illegal.
     */
    public function store(Request $request): JsonResponse
    {
        $spec = $this->normalizeSpec($request);
        $errors = $this->engine->validate($spec);

        if ($errors !== []) {
            return response()->json(['valid' => false, 'errors' => $errors], 422);
        }

        $price = $this->engine->price($spec);

        $order = CakeOrder::create([
            'spec'     => $spec,
            'subtotal' => $price['subtotal'],
            'gst'      => $price['gst'],
            'total'    => $price['total'],
        ]);

        return response()->json([
            'valid'    => true,
            'order_id' => $order->id,
            'price'    => $price,
        ], 201);
    }

    /**
     * Whitelists and casts the fields we accept from the client. Notably
     * absent: any 'price' or 'valid' the client might send — those are
     * simply never read into $spec, so they can't influence anything
     * downstream no matter what the request body contains.
     */
    private function normalizeSpec(Request $request): array
    {
        return [
            'occasion'    => (string) $request->input('occasion', ''),
            'tiers'       => array_map('floatval', (array) $request->input('tiers', [])),
            'flavor'      => (string) $request->input('flavor', ''),
            'dietary'     => (string) $request->input('dietary', ''),
            'finish'      => (string) $request->input('finish', ''),
            'color_theme' => $request->input('color_theme'),
            'has_message' => $request->boolean('has_message'),
            'message'     => $request->input('message'),
            'candles'     => (int) $request->input('candles', 0),
        ];
    }
}
