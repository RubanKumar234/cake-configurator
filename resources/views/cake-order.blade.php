<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Custom Cake Order</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">

</head>
<body>
<div x-data="cakeOrder()" class="parentdiv">
    <h1>Customize Your Cake</h1>

    <div class="configurator-layout">
        <div class="config-column">
            <label>Occasion
                <select x-model="occasion" @change="onOccasionChange()">
                    <option value="">Choose...</option>
                    <option value="birthday">Birthday</option>
                    <option value="baby_shower">Baby Shower</option>
                    <option value="wedding">Wedding</option>
                </select>
            </label>

            <template x-if="occasion === 'wedding'">
                <label>Number of tiers (1-4)
                    <select x-model.number="tierCount" @change="onTierCountChange()">
                        <template x-for="n in 4" :key="n">
                            <option :value="n" x-text="n"></option>
                        </template>
                    </select>
                </label>
            </template>

            <template x-for="(size, i) in tiers" :key="i">
                <label>
                    <span x-text="occasion === 'wedding' ? ('Tier ' + (i + 1) + ' size (kg)') : 'Size (kg)'"></span>
                    <select x-model.number="tiers[i]" @change="refreshQuote()">
                        <template x-for="opt in sizeOptions" :key="opt">
                            <option :value="opt" x-text="opt"></option>
                        </template>
                    </select>
                </label>
            </template>

            <template x-if="occasion">
                <label>Flavor
                    <select x-model="flavor" @change="refreshQuote()">
                        <option value="">Choose...</option>
                        <template x-for="f in availableFlavors" :key="f">
                            <option :value="f" x-text="flavorLabel(f)"></option>
                        </template>
                    </select>
                </label>
            </template>

            <template x-if="occasion">
                <label>Dietary
                    <select x-model="dietary" @change="onDietaryChange()">
                        <option value="none">None</option>
                        <option value="eggless">Eggless</option>
                        <option value="vegan">Vegan</option>
                    </select>
                </label>
            </template>

            <template x-if="occasion">
                <label>Finish
                    <select x-model="finish" @change="onFinishChange()">
                        <option value="">Choose...</option>
                        <template x-for="f in availableFinishes" :key="f">
                            <option :value="f" x-text="finishLabel(f)"></option>
                        </template>
                    </select>
                </label>
            </template>

            <template x-if="finish === 'fondant'">
                <label>Color theme
                    <input type="text" x-model="colorTheme" @input="refreshQuote()" placeholder="e.g. gold and ivory">
                </label>
            </template>

            <template x-if="occasion">
                <div class="checkbox-row">
                    <label style="display:inline; font-weight:normal;">
                        <input type="checkbox" x-model="hasMessage" @change="onMessageToggle()"> Message on cake
                    </label>
                    <template x-if="hasMessage">
                        <input type="text" maxlength="30" x-model="message" @input="refreshQuote()"
                               placeholder="Up to 30 characters" style="margin-top:8px;">
                    </template>
                </div>
            </template>

            <template x-if="occasion">
                <label>Candles (0-50)
                    <input type="number" min="0" max="50" x-model.number="candles" @input="refreshQuote()">
                </label>
            </template>
        </div>

        <div class="summary-column">
            <div class="price-box" x-show="occasion">
                <h2 class="summary-title">Order Summary</h2>
                <template x-if="quoteErrors.length">
                    <ul class="error">
                        <template x-for="e in quoteErrors" :key="e"><li x-text="e"></li></template>
                    </ul>
                </template>
                <template x-if="!quoteErrors.length && quote">
                    <div>
                        <div class="price-line"><span>Subtotal</span><span>&#8377;<span x-text="quote.subtotal"></span></span></div>
                        <div class="price-line"><span>GST (18%)</span><span>&#8377;<span x-text="quote.gst"></span></span></div>
                        <div class="price-line price-total"><span>Total</span><span>&#8377;<span x-text="quote.total"></span></span></div>
                    </div>
                </template>
                <template x-if="!quoteErrors.length && !quote">
                    <p class="summary-placeholder">Complete the choices on the left to see your live price.</p>
                </template>
            </div>

            <button @click="submit()" :disabled="!occasion || !flavor || !finish || quoteErrors.length > 0 || !quote">
                Place Order
            </button>
            <div class="success" x-show="submitted">Order placed! Order ID: <span x-text="orderId"></span></div>
        </div>
    </div>
</div>
<script src="{{ asset('js/cakeorder.js') }}"></script>
</body>
</html>
