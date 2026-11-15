# Real photos for the demo

Put a real photo in this folder with one of the names below and the demo uses it instead of the drawn
picture. No code changes: just add the files and run `php artisan demo:refresh` (or reseed).

## One photo per item

Any of `.jpg`, `.jpeg`, `.png`, `.webp` works. The menu has 29 items; each refresh puts 6 to 9 of them on
sale, so a photo shows up whenever its item is picked.

| File name | Shown on | Without a photo it shows |
|---|---|---|
| `bakery-bundle.jpg` | Bakery Surprise Bundle · Bonda Mawar Cake House | its own drawing |
| `croissants.jpg` | Butter croissants × 3 · Bonda Mawar Cake House | its own drawing |
| `cheese-tarts.jpg` | Cheese tarts × 4 · Bonda Mawar Cake House | the `pastry` drawing |
| `chocolate-cake.jpg` | Chocolate cake slice × 2 · Bonda Mawar Cake House | the `cake-slice` drawing |
| `pandan-cake.jpg` | Pandan layer cake slice × 2 · Bonda Mawar Cake House | the `cake-slice` drawing |
| `sausage-buns.jpg` | Sausage buns × 4 · Bonda Mawar Cake House | the `pastry` drawing |
| `brownies.jpg` | Fudge brownies × 3 · Bonda Mawar Cake House | the `cake-slice` drawing |
| `curry-puffs.jpg` | Curry puffs × 4 · Kuih Tradisional Tupah | its own drawing |
| `kuih-lapis.jpg` | Kuih lapis × 6 · Kuih Tradisional Tupah | the `kuih` drawing |
| `onde-onde.jpg` | Onde-onde × 10 · Kuih Tradisional Tupah | the `kuih` drawing |
| `seri-muka.jpg` | Seri muka × 6 · Kuih Tradisional Tupah | the `kuih` drawing |
| `kuih-ketayap.jpg` | Kuih ketayap × 5 · Kuih Tradisional Tupah | the `kuih` drawing |
| `kuih-bundle.jpg` | Kuih Surprise Bundle · Kuih Tradisional Tupah | the `surprise-bag` drawing |
| `iced-latte.jpg` | Iced latte · Kopi Saigon Merbok | its own drawing |
| `spanish-latte.jpg` | Iced Spanish latte · Kopi Saigon Merbok | the `iced-drink` drawing |
| `matcha-latte.jpg` | Iced matcha latte · Kopi Saigon Merbok | the `iced-drink` drawing |
| `vietnamese-coffee.jpg` | Vietnamese iced coffee · Kopi Saigon Merbok | the `iced-drink` drawing |
| `teh-tarik.jpg` | Teh tarik × 2 · Kopi Saigon Merbok | the `hot-drink` drawing |
| `nasi-ayam.jpg` | Nasi ayam set (2 pax) · Merbok Roasted | its own drawing |
| `nasi-ayam-panggang.jpg` | Nasi ayam panggang · Merbok Roasted | the `rice-meal` drawing |
| `nasi-lemak.jpg` | Nasi lemak ayam goreng · Merbok Roasted | the `rice-meal` drawing |
| `mee-goreng.jpg` | Mee goreng mamak · Merbok Roasted | the `noodles` drawing |
| `dinner-bundle.jpg` | Dinner Surprise Bundle · Merbok Roasted | the `surprise-bag` drawing |
| `sandwich-pack.jpg` | Sandwich & pastry pack · Se.Bunga Kafe UiTM | its own drawing |
| `nasi-campur.jpg` | Nasi campur · Se.Bunga Kafe UiTM | its own drawing |
| `nasi-goreng.jpg` | Nasi goreng kampung · Se.Bunga Kafe UiTM | the `rice-meal` drawing |
| `bihun-goreng.jpg` | Bihun goreng · Se.Bunga Kafe UiTM | the `noodles` drawing |
| `banana-cake.jpg` | Banana cake slice × 2 · Se.Bunga Kafe UiTM | the `cake-slice` drawing |
| `cafe-bundle.jpg` | Café Surprise Bundle · Se.Bunga Kafe UiTM | the `surprise-bag` drawing |

## One photo for a whole kind of food (the quick way)

Six of the items above have their own drawing. The others share eight general drawings. A real photo with
one of these names replaces that drawing for **every** item that uses it and has no photo of its own:

| File name | Replaces the drawing for |
|---|---|
| `kuih.jpg` | Kuih lapis, onde-onde, seri muka, kuih ketayap |
| `cake-slice.jpg` | Chocolate cake, pandan layer cake, banana cake, brownies |
| `pastry.jpg` | Cheese tarts, sausage buns |
| `iced-drink.jpg` | Iced Spanish latte, iced matcha latte, Vietnamese iced coffee |
| `hot-drink.jpg` | Teh tarik |
| `rice-meal.jpg` | Nasi ayam panggang, nasi lemak, nasi goreng kampung |
| `noodles.jpg` | Mee goreng mamak, bihun goreng |
| `surprise-bag.jpg` | Kuih, Dinner and Café Surprise Bundles |

So each item shows the first of these that exists: its own real photo, its own drawing, the real photo of
its kind, the drawing of its kind.

## Then, in the project folder

```
php artisan demo:refresh
```

About 5 seconds. It posts fresh deals with the new photos and keeps every account and past order.
(`php artisan migrate:fresh --seed` works too, but wipes everything.) Deals that were already closed keep
the picture they had.

Any item without a real photo keeps its drawn picture, so you can add them one at a time.

## What makes a good photo

- **Yours to use.** A photo you or a teammate took, or one from a free-licence site (Unsplash, Pexels,
  Wikimedia Commons). Don't use pictures saved from Google Images, shop websites or social media.
- **Landscape, food in the middle.** The app crops to a square in lists and to a wide strip on the
  listing page, so keep the edges unimportant.
- **About 1200 px wide and under 500 KB.** Bigger works but makes the pages slow.
- **No faces, no brand logos** if you can avoid it.

These are still demo pictures: the shops, prices and listings they appear on are made up.
