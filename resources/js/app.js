import Alpine from 'alpinejs';

/*
| Alpine handles the small interactions. Each one is registered here by name
| and used in Blade with x-data="name(...)". Pages still work without
| JavaScript: the server prints the starting values.
*/

/**
 * A live "00:42:10" countdown.  <x-countdown :until="$listing->pickup_end" />
 *
 * The server says how many seconds are left (so a wrong clock on the phone
 * doesn't matter). When it reaches zero the page reloads once, to show what
 * comes next (window open, deal ended…).
 */
Alpine.data('countdown', (secondsLeft, reloadWhenDone = true) => ({
    endsAt: Date.now() + secondsLeft * 1000,
    remaining: secondsLeft,
    timer: null,

    init() {
        if (this.remaining > 0) {
            this.timer = setInterval(() => this.tick(), 1000);
        }
    },

    tick() {
        this.remaining = Math.max(0, Math.round((this.endsAt - Date.now()) / 1000));

        if (this.remaining === 0) {
            clearInterval(this.timer);

            if (reloadWhenDone) {
                window.location.reload();
            }
        }
    },

    get clock() {
        const two = (n) => String(n).padStart(2, '0');

        return `${two(Math.floor(this.remaining / 3600))}:${two(Math.floor((this.remaining % 3600) / 60))}:${two(this.remaining % 60)}`;
    },

    destroy() {
        clearInterval(this.timer);
    },
}));

/**
 * The quantity stepper on the reserve form, with the total on the button.
 * Writes money the same way as App\Support\Money: "RM8", "RM4.50".
 */
Alpine.data('quantityPicker', ({ max, priceSen, initial = 1 }) => ({
    quantity: Math.min(Math.max(1, initial), max),
    max,

    fewer() {
        if (this.quantity > 1) this.quantity--;
    },

    more() {
        if (this.quantity < this.max) this.quantity++;
    },

    get total() {
        const sen = this.quantity * priceSen;
        const decimals = sen % 100 === 0 ? 0 : 2;

        return 'RM' + (sen / 100).toLocaleString('en-MY', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    },
}));

window.Alpine = Alpine;

Alpine.start();
