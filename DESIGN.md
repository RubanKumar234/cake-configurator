# DESIGN.md — Custom Cake Order Configurator

## How the rules are modeled
All constraints and prices live as plain data in `App\Domain\Cake\CakeRules` (allowed sizes per
occasion, flavor/finish exclusions, per-item prices, limits). A single stateless class,
`App\Domain\Cake\CakeConfiguratorEngine`, reads that data to do two things: `validate($spec)`
returns every violation found (not just the first), and `price($spec)` computes the breakdown —
both against the *same* table, so there's one place that knows what's legal and one place that
knows what it costs. Adding a rule (e.g. a new occasion) means adding rows to `CakeRules`, not
editing conditionals scattered across the controller/Blade/JS.

## Cascade + downstream invalidation
Two layers, deliberately different jobs:
- **Client (UX layer):** Alpine `onOccasionChange()` / `onDietaryChange()` / `onFinishChange()`
  handlers clear any field that the new upstream choice invalidates (tier count, flavor, finish,
  color theme) so the form never shows a stale, now-illegal selection.
- **Server (authority layer):** `resetDownstreamForOccasionChange()` on the engine does the same
  reset programmatically and is unit-tested directly. Independently, `validate()` is exercised
  against a *deliberately stale* combination (old finish/tiers left in place after an occasion
  change) to prove the engine rejects it on its own merits — the reset is a UX convenience, not
  the actual safety net.

## Where validation lives, and why
Entirely inside `CakeConfiguratorEngine`, not in a Laravel `FormRequest` and not in Blade/JS.
FormRequest-style rules are good at shape/type checks (is `candles` an int, is `occasion` a
string) but awkward at genuinely cross-field business logic (Fruit needs size ≥ 2kg AND that
depends on which occasion's tier). Keeping it in one plain-PHP class also makes it unit-testable
with zero framework bootstrap (`tests/Unit/CakeConfiguratorEngineTest`), separately from the
HTTP-level feature test that proves the *server*, not just the engine, is authoritative
(`tests/Feature/CakeOrderApiTest::test_server_ignores_tampered_client_price`).

## Money
No floats anywhere. Every rupee amount is converted to integer paise (`Money::toPaise`), summed
as integers, and GST is computed with an explicit round-half-up-to-the-paisa rule:
`intdiv(amountPaise * 18 + 50, 100)`. Every price in this domain resolves to a whole rupee, so
the paise→rupee conversion back is exact — but the internal representation stays integer the
whole way through so nothing ever drifts if paise-level pricing is added later.

## One tradeoff
The frontend re-implements a *simplified* mirror of the dependency rules purely to hide/show
options progressively (e.g. don't even list Red Velvet for Baby Shower). This duplicates a
sliver of logic in JS. The alternative — never filtering client-side and always waiting on
`/api/cake-orders/quote` to say what's wrong — is more strictly "one source of truth" but makes
for a worse form (every invalid dropdown value flashes an error instead of just not existing).
I chose the duplication because it's cosmetic only: the JS filtering never gates persistence or
price — every submit and every quote still round-trips through the same engine, so a bug in the
JS mirror can make the UI annoying but can never make the server accept or price something wrong.

## What I'd do with more time
- Property-based/fuzz tests generating random specs and asserting `price()` never throws for
  anything `validate()` accepts, and vice versa.
- Wedding per-tier flavor/finish (currently flavor/dietary/finish/add-ons are one choice for the
  whole order, matching the spec's pricing table, which only varies size per tier).
- Idempotency key on `/cake-orders` so a retried submit doesn't double-charge/double-create.
- Move `CakeRules` into a versioned config so historical orders keep the price rules that were
  live when they were placed, rather than always reflecting today's `CakeRules`.
