# FoodLoop · 2-minute demo script

A walk through the working prototype for the pitch: a student rescues food, a seller hands it over, the
student sees their impact, and an admin keeps the platform trustworthy.

Everything on screen is **demo data** (made-up shops, people and prices) and **payments are simulated**.
Say so once at the start; the pages say it too.

This script was run click by click in Chrome on freshly seeded data on 14 Nov 2026. It has **not** been
rehearsed on the Windows laptop yet: do one full run there before the pitch.

## Before you present (about 5 minutes)

**1. Reset the demo data.** Do this **no more than 30 minutes before you present**. The deals count down
for real: about 45 minutes after seeding the Bakery Surprise Bundle stops taking reservations, and about
95 minutes after seeding Aina's sandwich pickup (step 2) closes.

In Laragon's Terminal, inside `C:\laragon\www\foodloop`:

```
php artisan migrate:fresh --seed
php artisan serve
```

Success looks like `DemoSeeder ... DONE`, then `Server running on [http://127.0.0.1:8000]`. Leave that
window open.

**2. Keep the clock running.** Open a second Terminal in the same folder:

```
php artisan schedule:work
```

Leave it open too. (It closes finished listings and marks no-shows. The demo works without it, but the
numbers stay tidier with it.)

**3. Open three browser windows and log in.** Each role needs its own window that doesn't share a login,
so use three different ones. Every password is `password`.

| Window | Use | Log in at `http://localhost:8000/login` as | Then open |
|---|---|---|---|
| A · the student | Chrome, made narrow like a phone (or press F12 and pick a phone size) | `aina@foodloop.test` | `http://localhost:8000` |
| B · the seller | Chrome Incognito window | `dailygrind@foodloop.test` | lands on the seller dashboard |
| C · the admin | Microsoft Edge (or Firefox) | `admin@foodloop.test` | lands on the admin overview |

Line the windows up so you can click from A to B to C without hunting.

## The script

Times are where you should be on the clock. Words in **bold** are what you click.

### 0:00 · Open (window A, the deals page)

> "Campus food sellers throw away good food every night. FoodLoop lets students rescue it at half price.
> This is the working prototype. The shops and prices are demo data and payments are simulated."

Point at the screen: deals **closing soonest first**, the minutes left, the **Halal only** filter, the
distance.

### 0:15 · A student reserves (window A)

1. Click **Bakery Surprise Bundle**.
2. Point at **−60%**, the live countdown, and the facts box: pickup time, how many are left, halal status,
   allergens.
3. Click **Reserve for RM8**.

> "One tap, and she gets a pickup code. No delivery: she collects it herself in the pickup window."

You are now on **You're booked.** with a 4-digit code and "Pickup window opens in…". Point at the line
about free cancellation.

### 0:45 · The seller hands over (window B)

> "Earlier today Aina also reserved a sandwich pack from Daily Grind Café. She's at the counter now and
> shows her code."

1. In **Customer's code** type `2468`.
2. Click **Check and collect**.

The green line says **Collected: Aina R. · Sandwich & pastry pack × 1. Hand it over.**

> "The code only works at this shop, and only during the pickup window. If a student doesn't show up,
> the food is released and they get a strike. Three strikes pause reserving for a week."

### 1:10 · The student's impact (window A)

Click **Impact** in the menu.

> "Every pickup counts. Fifteen meals rescued, RM133 saved, and what all students rescued this month."

The big number is **15** (it was 14 before the pickup), **You saved RM133**, and the sandwich pack is at
the top of **Recent**.

### 1:25 · The admin keeps it trustworthy (window C)

1. Under **Sellers to approve**, point at **Sweet Crumbs Bakehouse**: it says it's halal certified but
   uploaded no certificate, so there is no Approve button.
2. Click **Approve** next to **Kedai Kopi Sinar** (certificate uploaded).
3. Under **Reported listings**, a student reported stale bread. Click **Warn seller**.

> "Shops are checked before they can sell, 'halal certified' needs a certificate an admin has seen, and
> students can report a listing."

### 1:50 · Close (window C, or back to A)

Point at **Platform totals** at the bottom of the admin page (meals rescued, portions to food banks).

> "And whatever is left at closing is offered to a food bank instead of the bin. That's FoodLoop."

## If something goes wrong

| What you see | What to do |
|---|---|
| The bundle says **Closed** or **Sold out**, or there's no Reserve button | Too long since seeding. Skip the reserve step: click **Orders** in window A and open the reservation with code **4721** instead. It is the same "You're booked." page. |
| Code `2468` gives "The pickup time for this reservation has passed" | More than 95 minutes since seeding. Reseed (step 1 above) or skip to the Impact step and say "after the pickup…". |
| Code `2468` gives "No reservation with that code is waiting at your shop" | It was already collected in a rehearsal. Reseed. |
| "You're offline" strip, or the page won't load | The `php artisan serve` window was closed. Start it again and press F5. |
| "Something broke on our side" (error 500) | Laragon's MySQL stopped. In Laragon click **Start All**, then press F5. |
| You are logged in as the wrong person | Click the round initials button (top right) and **Log out**, or use the right window. |
| The food pictures are grey plates | Run `php artisan storage:link` once, then reseed. |

## Good to know while presenting

- **Reset any time** with `php artisan migrate:fresh --seed` (about 5 seconds). It wipes everything you
  clicked and brings back the same starting point.
- **Other things worth showing if you have more time:**
  - Sign up as a new student from **Log in → Create an account** (window with nobody logged in).
  - `http://localhost:8000/map`: the simplified map of shops with deals.
  - Log in as `daniel@foodloop.test`: a student who is blocked after three no-shows.
  - Log in as `rotilane@foodloop.test`: **Donations** shows leftovers offered to the food bank, and
    **Listings → New listing** shows how a seller posts food.
  - In window A, open a listing and click **Something wrong? Report this listing**; it appears on the
    admin overview straight away.
- **What is not real:** no real payments, no real map (the map page is a simplified drawing), no push or
  SMS notifications, and the food bank partner is a placeholder name.
