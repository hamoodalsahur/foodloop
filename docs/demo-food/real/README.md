# Real photos for the demo

Put a real photo in this folder with one of the names below and the demo uses it instead of the drawn
picture. No code changes: just add the files and reseed.

| File name (any of `.jpg`, `.jpeg`, `.png`, `.webp`) | Shown on |
|---|---|
| `bakery-bundle.jpg` | Bakery Surprise Bundle · Roti Lane Bakery |
| `croissants.jpg` | Butter croissants × 3 · Roti Lane Bakery |
| `curry-puffs.jpg` | Curry puffs × 4 · Mak Long's Kuih |
| `iced-latte.jpg` | Iced latte · Daily Grind Café |
| `sandwich-pack.jpg` | Sandwich & pastry pack · Daily Grind Café |
| `nasi-ayam.jpg` | Nasi ayam set (2 pax) · Kafe Seri Ilmu |

Then, in the project folder:

```
php artisan migrate:fresh --seed
```

Any product without a real photo keeps its drawn picture, so you can add them one at a time.

## What makes a good photo

- **Yours to use.** A photo you or a teammate took, or one from a free-licence site (Unsplash, Pexels,
  Wikimedia Commons). Don't use pictures saved from Google Images, shop websites or social media.
- **Landscape, food in the middle.** The app crops to a square in lists and to a wide strip on the
  listing page, so keep the edges unimportant.
- **About 1200 px wide and under 500 KB.** Bigger works but makes the pages slow.
- **No faces, no brand logos** if you can avoid it.

These are still demo pictures: the shops, prices and listings they appear on are made up.
