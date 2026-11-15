/*
| The street map on /map (Leaflet + OpenStreetMap's own tiles: free, no key).
|
| The page always contains the simplified drawing. This script swaps it for
| the street map, and swaps back if no map tile arrives within a few seconds
| (no internet at the pitch): the drawing is the backup, so the page never
| ends up empty. Data comes from the data-map attribute (MapController).
*/
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const GIVE_UP_AFTER_MS = 7000;

// Small DOM helper: build elements with text only (never HTML from data).
function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}

function pin(html, className, size) {
    return L.divIcon({ html, className, iconSize: [size, size], iconAnchor: [size / 2, size / 2], popupAnchor: [0, -size / 2] });
}

function popupFor(shop) {
    const box = el('div', 'fl-popup');
    box.append(el('p', 'fl-popup-title', shop.name));
    box.append(el('p', 'fl-popup-meta', `${shop.distance} from campus · ${shop.deals}`));

    const actions = el('div', 'fl-popup-actions');
    const deals = el('a', 'fl-popup-primary', 'See deals');
    deals.href = `#shop-${shop.number}`;
    const directions = el('a', 'fl-popup-secondary', 'Directions');
    directions.href = shop.directions;
    directions.target = '_blank';
    directions.rel = 'noopener';
    actions.append(deals, directions);
    box.append(actions);

    return box;
}

export function startMap(root) {
    const data = JSON.parse(root.dataset.map);
    const container = root.querySelector('[data-street-map]');

    root.dataset.state = 'street';

    const map = L.map(container, { scrollWheelZoom: false, zoomControl: true });
    const tiles = L.tileLayer(TILES, {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    });

    let loaded = false;
    tiles.once('tileload', () => { loaded = true; });
    setTimeout(() => {
        if (!loaded) {
            map.remove();
            root.dataset.state = 'drawing';
            root.querySelector('[data-offline-note]')?.removeAttribute('hidden');
        }
    }, GIVE_UP_AFTER_MS);
    tiles.addTo(map);

    const origin = [data.origin.lat, data.origin.lng];
    L.marker(origin, {
        icon: pin('<span class="fl-you-dot"></span>', 'fl-you', 22),
        title: data.origin.label,
        keyboard: false,
        zIndexOffset: -100,
    }).addTo(map).bindTooltip(data.origin.label, { permanent: true, direction: 'bottom', offset: [0, 10], className: 'fl-you-label' });

    const points = [origin];
    data.shops.forEach((shop) => {
        const at = [shop.lat, shop.lng];
        points.push(at);
        L.marker(at, {
            icon: pin(`<span>${Number(shop.number)}</span>`, 'fl-pin', 34),
            title: `${shop.number}: ${shop.name}`,
            alt: `${shop.number}: ${shop.name}`,
        }).addTo(map).bindPopup(popupFor(shop), { closeButton: true, minWidth: 200 });
    });

    map.fitBounds(L.latLngBounds(points), { padding: [36, 36], maxZoom: 16 });
}
