function cakeOrder() {
    return {
        occasion: '', tierCount: 1, tiers: [], flavor: '', dietary: 'none',
        finish: '', colorTheme: '', hasMessage: false, message: '', candles: 0,
        quote: null, quoteErrors: [], submitted: false, orderId: null,

        sizesByOccasion: {
            birthday: [0.5, 1, 2],
            baby_shower: [1, 2],
            wedding: [2, 3, 5],
        },

        // NOTE: these getters mirror the server's dependency rules purely
        // for progressive-disclosure UX (hiding options that would be
        // rejected). They are NOT the source of truth for validity or
        // price — every change still round-trips to /api/cake-orders/quote,
        // which shares the same CakeConfiguratorEngine used on submit.
        get sizeOptions() {
            return this.sizesByOccasion[this.occasion] || [];
        },
        get availableFlavors() {
            let all = ['vanilla', 'chocolate', 'red_velvet', 'fruit'];
            if (this.occasion === 'baby_shower') all = all.filter(f => f !== 'red_velvet');
            if (this.dietary === 'vegan') all = all.filter(f => f !== 'red_velvet');
            const minSize = this.tiers.length ? Math.min(...this.tiers) : 0;
            if (minSize < 2) all = all.filter(f => f !== 'fruit');
            return all;
        },
        get availableFinishes() {
            let all = ['buttercream', 'whipped', 'fondant'];
            if (this.dietary === 'vegan') all = all.filter(f => f !== 'buttercream');
            const minSize = this.tiers.length ? Math.min(...this.tiers) : 0;
            if (this.occasion !== 'wedding' && minSize < 2) all = all.filter(f => f !== 'fondant');
            return all;
        },

        flavorLabel(f) { return { vanilla: 'Vanilla', chocolate: 'Chocolate', red_velvet: 'Red Velvet', fruit: 'Fruit' }[f]; },
        finishLabel(f) { return { buttercream: 'Buttercream', whipped: 'Whipped Cream', fondant: 'Fondant' }[f]; },

        // Downstream invalidation: changing an earlier choice clears every
        // later field that depended on it, instead of leaving it stale.
        onOccasionChange() {
            this.tierCount = 1;
            this.tiers = this.occasion ? [this.sizesByOccasion[this.occasion][0]] : [];
            this.flavor = '';
            this.finish = '';
            this.colorTheme = '';
            this.refreshQuote();
        },
        onTierCountChange() {
            const def = this.sizesByOccasion[this.occasion][0];
            const next = [];
            for (let i = 0; i < this.tierCount; i++) next.push(this.tiers[i] ?? def);
            this.tiers = next;
            this.refreshQuote();
        },
        onDietaryChange() {
            if (this.dietary === 'vegan' && this.flavor === 'red_velvet') this.flavor = '';
            if (this.dietary === 'vegan' && this.finish === 'buttercream') this.finish = '';
            this.refreshQuote();
        },
        onFinishChange() {
            if (this.finish !== 'fondant') this.colorTheme = '';
            this.refreshQuote();
        },
        onMessageToggle() {
            if (!this.hasMessage) this.message = '';
            this.refreshQuote();
        },

        buildSpec() {
            return {
                occasion: this.occasion,
                tiers: this.tiers,
                flavor: this.flavor,
                dietary: this.dietary,
                finish: this.finish,
                color_theme: this.colorTheme,
                has_message: this.hasMessage,
                message: this.message,
                candles: this.candles,
            };
        },

        async refreshQuote() {
            if (!this.occasion || !this.flavor || !this.finish) { this.quote = null; this.quoteErrors = []; return; }
            const res = await fetch('/api/cake-orders/quote', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(this.buildSpec()),
            });
            const data = await res.json();
            if (data.valid) { this.quote = data.price; this.quoteErrors = []; }
            else { this.quote = null; this.quoteErrors = data.errors || ['Invalid configuration.']; }
        },

        async submit() {
            const res = await fetch('/api/cake-orders', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(this.buildSpec()),
            });
            const data = await res.json();
            if (data.valid) { this.submitted = true; this.orderId = data.order_id; }
            else { this.quoteErrors = data.errors || ['Invalid configuration.']; }
        },
    };
}
