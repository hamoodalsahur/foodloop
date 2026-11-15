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

/*
| Busy buttons. When a form is sent, its button shows that something is
| happening and can't be tapped twice (a slow connection otherwise looks
| like nothing happened, and a second tap sends the form again).
|
|   <x-button type="submit" data-busy="Reserving…">Reserve</x-button>
|
| data-busy is optional: without it the button only stops a second tap.
*/
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (event.defaultPrevented || form.method.toLowerCase() !== 'post') {
        return;
    }

    if (form.dataset.sending) {
        event.preventDefault();
        return;
    }

    form.dataset.sending = '1';
    const button = event.submitter;

    // After this tick: the browser has already read the button's name and value.
    setTimeout(() => {
        if (!button) {
            return;
        }
        button.setAttribute('aria-busy', 'true');
        button.disabled = true;

        if (button.dataset.busy) {
            const label = button.querySelector('[data-label]') ?? button.querySelector('span') ?? button;
            button.dataset.idleLabel = label.innerHTML;
            label.textContent = button.dataset.busy;
        }
    });
});

// Coming back with the Back button shows the page as it was left: make its buttons usable again.
window.addEventListener('pageshow', (event) => {
    if (!event.persisted) {
        return;
    }
    document.querySelectorAll('form[data-sending]').forEach((form) => delete form.dataset.sending);
    document.querySelectorAll('button[aria-busy="true"]').forEach((button) => {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        if (button.dataset.idleLabel !== undefined) {
            (button.querySelector('[data-label]') ?? button.querySelector('span') ?? button).innerHTML = button.dataset.idleLabel;
        }
    });
});

// The street map only loads on the map page, as its own small download.
const streetMap = document.querySelector('[data-map]');
if (streetMap) {
    import('./map').then(({ startMap }) => startMap(streetMap));
}

// Installable app: register the service worker (public/sw.js). Browsers only
// allow this on https (or localhost), so on plain http it is simply skipped.
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
