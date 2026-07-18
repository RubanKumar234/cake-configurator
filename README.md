# Custom Cake Order Configurator

Laravel backend + a thin Blade/Alpine.js frontend implementing a cake order configurator with
cascading rules, server-authoritative pricing, and a rule engine that lives in one place.

## Requirements
- PHP 7.4+ (also compatible with PHP 8.x — no version-specific syntax is used)
- Composer
- MySQL (5.7+ / 8.x) running locally, or any MySQL-compatible server (MariaDB works fine too)

## Setup (fresh machine)

```bash
# 1. Create a normal Laravel skeleton
composer create-project laravel/laravel cake-configurator
cd cake-configurator

# 2. Copy this repo's files IN, overwriting/adding on top of the fresh skeleton:
#    app/Domain/Cake/*        -> app/Domain/Cake/
#    app/Http/Controllers/CakeOrderController.php -> app/Http/Controllers/
#    app/Models/CakeOrder.php -> app/Models/
#    database/migrations/2026_01_01_000000_create_cake_orders_table.php -> database/migrations/
#    resources/views/cake-order.blade.php -> resources/views/
#    public/js/cakeorder.js   -> public/js/
#    public/css/style.css     -> public/css/
#    routes/api.php           -> merge into routes/api.php (create the file if it doesn't exist
#                                 — some Laravel versions ship without one by default; if yours
#                                 does, run `php artisan install:api` first, then merge). Make
#                                 sure the `use App\Http\Controllers\CakeOrderController;` import
#                                 line is present at the top.
#    routes/web.php           -> merge the one Route::view(...) line into routes/web.php
#    tests/Unit/CakeConfiguratorEngineTest.php   -> tests/Unit/
#    tests/Feature/CakeOrderApiTest.php          -> tests/Feature/
#    DESIGN.md                -> project root

# 3. Create the database
mysql -u root -p -e "CREATE DATABASE cake_configurator;"

# 4. Configure .env
#   DB_CONNECTION=mysql
#   DB_HOST=127.0.0.1
#   DB_PORT=3306
#   DB_DATABASE=cake_configurator
#   DB_USERNAME=root
#   DB_PASSWORD=your_mysql_password

# 5. Run migrations
php artisan migrate

# 6. Run the tests
php artisan test
# Expect 10 passing: CakeConfiguratorEngineTest (5), Unit ExampleTest (1, Laravel default),
# CakeOrderApiTest (3), Feature ExampleTest (1, Laravel default).
# Note: tests run against SQLite in-memory by default (see phpunit.xml's DB_CONNECTION /
# DB_DATABASE env overrides), independent of the MySQL connection above — this is standard
# practice for fast, isolated test runs and needs no extra setup.

# 7. Serve it
php artisan serve
# Visit http://127.0.0.1:8000/cake-order
```

## Database notes
The app defaults to MySQL for local development (see `.env` above). Nothing in this project is
MySQL-specific — no raw SQL, no MySQL-only column types — so SQLite works identically if you'd
rather avoid running a MySQL server:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/cake-configurator/database/database.sqlite
```
(and `touch database/database.sqlite` first, since Laravel doesn't create the file for you).

## What to check once it's running
1. Open `/cake-order`, pick Birthday / 1kg / Chocolate / Eggless / Buttercream / Message on
   ("Happy Birthday!") / 10 candles. The summary panel should show Subtotal ₹1100, GST ₹198,
   Total ₹1298 — matches the spec's worked example exactly.
2. Switch Occasion from Wedding (with Fondant + a color theme set) back to Birthday — the tier
   count, Finish, and Color theme fields should visibly reset instead of leaving stale values.
3. Try `curl -X POST http://127.0.0.1:8000/api/cake-orders -H 'Content-Type: application/json' -d '{"occasion":"birthday","tiers":[1],"flavor":"chocolate","dietary":"eggless","finish":"buttercream","has_message":true,"message":"hi","candles":10,"price":1,"valid":true}'`
   — the response total is still 1298, ignoring the `price: 1` sent in the request.

## Project structure

```
app/
  Domain/Cake/
    CakeRules.php                 - all constraints & prices, as data
    CakeConfiguratorEngine.php     - validate() + price() + downstream reset helper
    Money.php                      - integer-paise arithmetic, no floats
    Exceptions/InvalidCakeConfigException.php
  Http/Controllers/CakeOrderController.php   - /quote (preview) and /cake-orders (submit)
  Models/CakeOrder.php
database/migrations/...create_cake_orders_table.php
resources/views/cake-order.blade.php         - Blade markup (two-column: inputs + live summary)
public/js/cakeorder.js                       - frontend Alpine component (all form/quote logic)
public/css/style.css                         - styling, including the two-column layout
routes/api.php, routes/web.php
tests/Unit/CakeConfiguratorEngineTest.php
tests/Feature/CakeOrderApiTest.php
DESIGN.md
```

## Rounding rule
All money is computed in integer paise; GST is rounded half-up to the nearest paisa
(`intdiv(subtotalPaise * 18 + 50, 100)`). See `Money.php` and `DESIGN.md` for details.
