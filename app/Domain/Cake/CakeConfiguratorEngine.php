<?php

namespace App\Domain\Cake;

use App\Domain\Cake\Exceptions\InvalidCakeConfigException;

/**
 * The single authority for "is this cake config legal" and "what does it
 * cost." No HTTP, no DB, no Eloquent — plain PHP so it's trivially unit
 * testable and reusable from both the /quote preview endpoint and the
 * final /store submit endpoint.
 *
 * Expected $spec shape:
 * [
 *   'occasion'    => 'birthday'|'baby_shower'|'wedding',
 *   'tiers'       => float[]  // one entry per tier, in kg
 *   'flavor'      => 'vanilla'|'chocolate'|'red_velvet'|'fruit',
 *   'dietary'     => 'none'|'eggless'|'vegan',
 *   'finish'      => 'buttercream'|'whipped'|'fondant',
 *   'color_theme' => ?string, // required iff finish === 'fondant'
 *   'has_message' => bool,
 *   'message'     => ?string, // required iff has_message === true
 *   'candles'     => int,
 * ]
 */
class CakeConfiguratorEngine
{
    /**
     * Collects ALL violations rather than stopping at the first one — makes
     * the API more useful to the frontend and makes each rule independently
     * testable.
     *
     * @return string[] empty array = valid
     */
    public function validate(array $spec): array
    {
        $errors = [];

        $occasion = $spec['occasion'] ?? null;
        if (!in_array($occasion, CakeRules::OCCASIONS, true)) {
            $errors[] = 'Invalid occasion.';
            return $errors; // nothing else is checkable without a real occasion
        }

        $isWedding = $occasion === 'wedding';
        $tiers = array_values($spec['tiers'] ?? []);

        if ($isWedding) {
            if (count($tiers) < 1 || count($tiers) > CakeRules::MAX_TIERS) {
                $errors[] = 'Wedding cakes must have between 1 and ' . CakeRules::MAX_TIERS . ' tiers.';
            }
        } elseif (count($tiers) !== 1) {
            $errors[] = ucfirst(str_replace('_', ' ', $occasion)) . ' must have exactly one size.';
        }

        // Compare as floats on both sides: incoming sizes may arrive as
        // int(1) (raw JSON/PHP) or float(1.0) (after the controller's
        // floatval() cast), and strict in_array() would otherwise treat
        // those as different values even though they're numerically equal.
        $allowedSizes = array_map('floatval', CakeRules::SIZES_BY_OCCASION[$occasion]);
        foreach ($tiers as $i => $size) {
            if (!in_array((float) $size, $allowedSizes, true)) {
                $errors[] = 'Tier ' . ($i + 1) . " size {$size}kg is not offered for {$occasion}.";
            }
        }

        $minTierSize = $tiers !== [] ? min($tiers) : 0;

        $flavor = $spec['flavor'] ?? null;
        if (!in_array($flavor, CakeRules::FLAVORS, true)) {
            $errors[] = 'Invalid flavor.';
        } else {
            if ($flavor === 'red_velvet' && $occasion === 'baby_shower') {
                $errors[] = 'Red Velvet is not available for Baby Shower.';
            }
            if ($flavor === 'fruit' && $minTierSize < CakeRules::FRUIT_MIN_SIZE_KG) {
                $errors[] = 'Fruit flavor requires a size of at least ' . CakeRules::FRUIT_MIN_SIZE_KG . 'kg.';
            }
        }

        $dietary = $spec['dietary'] ?? null;
        if (!in_array($dietary, CakeRules::DIETARY, true)) {
            $errors[] = 'Invalid dietary option.';
        }
        $isVegan = $dietary === 'vegan';

        if ($isVegan && $flavor === 'red_velvet') {
            $errors[] = 'Vegan cakes cannot be Red Velvet.';
        }

        $finish = $spec['finish'] ?? null;
        if (!in_array($finish, CakeRules::FINISHES, true)) {
            $errors[] = 'Invalid finish.';
        } else {
            if ($finish === 'buttercream' && $isVegan) {
                $errors[] = 'Buttercream is not available for Vegan cakes.';
            }
            if ($finish === 'fondant' && !$isWedding && $minTierSize < CakeRules::FONDANT_MIN_SIZE_KG) {
                $errors[] = 'Fondant requires Wedding occasion or a size of at least '
                    . CakeRules::FONDANT_MIN_SIZE_KG . 'kg.';
            }
        }

        $colorTheme = $spec['color_theme'] ?? null;
        if ($finish === 'fondant') {
            if ($colorTheme === null || $colorTheme === '') {
                $errors[] = 'Color theme is required when finish is Fondant.';
            }
        } elseif ($colorTheme !== null && $colorTheme !== '') {
            $errors[] = 'Color theme is only valid when finish is Fondant.';
        }

        $hasMessage = (bool) ($spec['has_message'] ?? false);
        $message = $spec['message'] ?? null;
        if ($hasMessage) {
            if (!is_string($message) || $message === '') {
                $errors[] = "Message text is required when 'Message on cake' is selected.";
            } elseif (mb_strlen($message) > CakeRules::MAX_MESSAGE_LEN) {
                $errors[] = 'Message must be ' . CakeRules::MAX_MESSAGE_LEN . ' characters or fewer.';
            }
        } elseif ($message !== null && $message !== '') {
            $errors[] = "Message text is only valid when 'Message on cake' is selected.";
        }

        $candles = $spec['candles'] ?? 0;
        if (!is_int($candles) || $candles < 0 || $candles > CakeRules::MAX_CANDLES) {
            $errors[] = 'Candles must be a whole number between 0 and ' . CakeRules::MAX_CANDLES . '.';
        }

        return $errors;
    }

    public function isValid(array $spec): bool
    {
        return $this->validate($spec) === [];
    }

    /**
     * @return array{subtotal:int,gst:int,total:int} whole rupees
     *
     * @throws InvalidCakeConfigException if $spec is not valid — callers
     *         must validate (or catch this) before trusting the price.
     */
    public function price(array $spec): array
    {
        $errors = $this->validate($spec);
        if ($errors !== []) {
            throw new InvalidCakeConfigException($errors);
        }

        $subtotalPaise = 0;

        foreach ($spec['tiers'] as $size) {
            $subtotalPaise += Money::toPaise(CakeRules::SIZE_BASE_PRICE[(string) $size]);
        }

        $subtotalPaise += Money::toPaise(CakeRules::FLAVOR_PRICE[$spec['flavor']]);
        $subtotalPaise += Money::toPaise(CakeRules::DIETARY_PRICE[$spec['dietary']]);
        $subtotalPaise += Money::toPaise(CakeRules::FINISH_PRICE[$spec['finish']]);

        if (!empty($spec['has_message'])) {
            $subtotalPaise += Money::toPaise(CakeRules::MESSAGE_PRICE);
        }

        $candles = (int) ($spec['candles'] ?? 0);
        $subtotalPaise += Money::toPaise(CakeRules::CANDLE_PRICE) * $candles;

        $gstPaise = Money::percentRoundHalfUp($subtotalPaise, CakeRules::GST_RATE_PERCENT);
        $totalPaise = $subtotalPaise + $gstPaise;

        return [
            'subtotal' => Money::toRupees($subtotalPaise),
            'gst'      => Money::toRupees($gstPaise),
            'total'    => Money::toRupees($totalPaise),
        ];
    }

    /**
     * Given an existing spec and a newly chosen occasion, clears every
     * downstream field that the new occasion makes invalid, instead of
     * leaving stale selections in place. Used by the frontend's change
     * handler and exercised directly in tests; the server-side validate()
     * call is the actual backstop if a stale combination ever reaches the
     * API regardless (see CakeOrderApiTest / EngineTest for both angles).
     */
    public function resetDownstreamForOccasionChange(array $spec, string $newOccasion): array
    {
        $spec['occasion'] = $newOccasion;
        $spec['tiers'] = [CakeRules::SIZES_BY_OCCASION[$newOccasion][0]];

        if (($spec['flavor'] ?? null) === 'red_velvet' && $newOccasion === 'baby_shower') {
            unset($spec['flavor']);
        }
        if (($spec['flavor'] ?? null) === 'fruit') {
            // Size just reset to the smallest tier, so fruit's size gate
            // can no longer be assumed satisfied.
            unset($spec['flavor']);
        }
        if (($spec['finish'] ?? null) === 'fondant' && $newOccasion !== 'wedding') {
            unset($spec['finish'], $spec['color_theme']);
        }

        return $spec;
    }
}
