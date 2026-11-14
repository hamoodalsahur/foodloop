# FoodLoop · project notes

The project's memory: what changed, why, how it was tested, and results. Newest entries at the top.
Everything shown with shop names, prices or counts in the mockups is **demo data**, not research data.

## Status

| Part | Status | Where |
|---|---|---|
| System workflow (swimlanes, exception rules, reservation states) | ✅ | Whiteboard artifact "FoodLoop System Workflow" |
| First-draft mockups, 7 screens | ✅ (superseded) | Canvas "FoodLoop UI Mockups", page "First draft" |
| Style directions A–D | ✅ | Same canvas, page "Style directions" |
| Style C full screen set + states | ✅ approved by owner (13 Nov), food pictures added | Same canvas, page "C · Full set"; PNGs in `docs/mockups/` |
| Demo food pictures | ✅ (illustrations, stand-ins for real photos) | `docs/demo-food/*.svg` |
| Build plan (data model, rules, stages) | ✅ | `docs/BUILD_PLAN.md` |
| Laravel prototype | 🚧 Stage 0 (setup) ✅ · Stage 1 (database, demo data) ✅ · Stage 2 (reservation engine) ✅ · Stage 3 (roles, layouts, design system) ✅ · Stage 4 (student flow) ✅ · Stage 5 (seller flow) ✅ · Stage 6 next | repo root; setup steps and demo accounts in `README.md` |

---

## 14 Nov 2026 · Stage 5 · The seller side

- **What:** a shop can now sign up, post food and hand it over.
  - *Sell on FoodLoop* (`/sell`, owner's choice: its own page, linked from log in and register): the
    person's account plus shop name, SSM number, phone, address and halal status. "Halal certified" needs
    the JAKIM certificate (PDF/JPG/PNG, kept private, not on the public disk). The shop starts **waiting
    for approval**: it can write drafts but nothing goes live until an admin approves it (Stage 7).
  - *Dashboard* (`/seller`, mockup c08): Waiting for pickup, Reservations today, Sales this week (collected
    orders), Rescued this month; the pickups table with **Check and collect** by code and **Mark collected**
    on rows whose window is open; live listings with how many are reserved; the "Left over at closing?"
    card with **Offer to food bank**.
  - *Listings* (`/seller/listings`): grouped On the deals page / Paused / Drafts / Closed.
  - *New and edit listing* (mockup c09): type, name, category, photo upload, description, normal and
    FoodLoop price with the live discount, portions, pickup day and times, halal status, allergens,
    "offer leftovers"; a live customer preview. Save draft, Publish, Pause, Delete draft.
  - *Pickups* (today and coming days), *Donations*, *Shop profile*.
- **Rules (owner's decisions today):** once anyone has reserved, the **price, portions, pickup time and
  halal status are locked** (greyed out, and ignored by the server even if sent); name, photo,
  description and pausing stay open. Cancelled reservations don't count, so if everyone cancels it
  unlocks. Other rules enforced in code and tests: FoodLoop price below the normal price; up to 50
  portions; a window up to 6 hours on one day; publishing needs a pickup that hasn't started (reservations
  close when it opens); "Halal certified" only with an approved certificate; only drafts that were never
  reserved can be deleted; closed listings can't be edited; leftovers (unsold + no-shows) can be offered
  once, after every customer has collected or missed their pickup; a shop can only touch its own
  listings and pickups (another shop's code gets "no reservation with that code").
- **How:** `SellerListingService` holds the listing rules, `SaveListingRequest` checks the form,
  `SellerOverview` gathers the dashboard numbers, policies decide who may touch what. Collecting still goes
  through `ReservationService::collect`. New form components `x-form.select` and `x-form.textarea`.
- **Browser check:** every seller page at 390 px and 1280 px in Chromium, no sideways scrolling. Fixed
  while checking: the pickups table and the edit form pushed the phone page sideways; locked fields now
  look locked; the new-listing form late in the evening suggested a window past midnight (now tomorrow
  12–2 PM). On phones the pickups table scrolls sideways; the code box above it is the quick way in.
- **Tested:** 420 passed, 2 skipped on SQLite (MySQL-only race test not run here). New: 53 seller tests,
  including one that goes from a new listing to a student reserving to the seller collecting by code.

## 14 Nov 2026 · Reservations now close when pickup opens

- **What:** the team answered the two Stage 4 questions. (1) Reserving stops when the pickup window
  **opens** (was: when it ends). One place decides it, `Listing::reservationsCloseAt()`, used by the
  reservation service, the deals list (sorting, "min left", "closing within the hour") and the listing
  page countdown. A live listing whose pickup has started shows a new **"Closed"** state ("Pickup started
  at 7:00 PM · no new reservations"). Cancelling a reservation still puts stock back on sale only before
  pickup opens. (2) "Halal only" stays as built.
- **Demo data:** the curry puffs, latte and nasi ayam windows now start 20, 35 and 60 minutes after
  seeding, so the deals page still matches the mockup order and times; Hafiz's latte pickup is now
  waiting instead of collected (it couldn't be collected before the window opens). The sandwich pack's
  pickup has started, so it no longer shows on the deals page.
- **Tested:** 366 passed, 2 skipped on SQLite (the MySQL-only race test was not run here). Tests changed
  to the new rule: reserving 1 minute before opening works and is refused once it opens; the late
  "changed my mind" case now reserves 10 minutes before opening; deals sort and count by opening time.
## 14 Nov 2026 · Stage 4 · The student flow, end to end

- **What:** a student can now go from the deals page to a pickup code.
  - *Deals page* (`/`, mockups c01, c05, c06): closing soonest first; tabs Closing soon / Nearest /
    Cheapest / New; tiles for Surprise Bundles left, Halal only and Distance; food category chips; the
    strip "N deals close within the hour"; "All gone nearby." when nothing matches, with buttons that undo
    one filter each; "Couldn't load deals." with Try again when the database doesn't answer.
  - *Listing page* (`/listings/{id}`, c02, c07): discount band, live countdown, facts box (pickup, left,
    halal, allergens), quantity stepper, pay now / pay at pickup, "Reserve for RM…". Sold-out and ended
    versions suggest other deals. Guests see "Log in to reserve" and come straight back after logging in.
  - *You're booked* (`/orders/{id}`, c03): pickup code, countdown (window opens in / closes in / last
    chance), three-step bar, details, the free or late cancellation wording, Directions, and Cancel with
    a confirm step. Also shows collected, cancelled (refunded) and missed (strikes) reservations.
  - *Orders* (`/orders`): waiting for pickup first, then earlier ones.
  - *Log in and register* (`/login`, `/register`) in style C. New accounts are customers. After login
    customers go to the deals, sellers to `/seller`, admins to `/admin`.
  - *Error pages* 403, 404, 419, 429, 500, 503 in the same style, and a red "You're offline" strip.
  - New shared pieces: `x-notice`, `x-countdown`, `x-back-link`, `x-form.input`, big `page` versions of
    `x-empty-state` and `x-error-state`, `App\Support\Distance`, `PickupWindow`, `DealFilters`,
    `App\Queries\DealsQuery`. `docs/UI_GUIDE.md` has a new "How to add a page" section for Stages 5 to 8.
- **Choices made on the way (and why):**
  - *Reservations close when the pickup window ends,* as the build plan and the engine say. The mockup's
    listing countdown (00:42:10) matched the time until the window *opens*; ours counts to the window's
    end, so the demo bundle shows about 02:45:00. The booked page does show "Pickup window opens in
    00:4x:xx" like the mockup. **Question for the team:** should reserving stop when the window opens
    instead?
  - *"Halal only" is on by default* (as in the mockup) and hides only listings marked not halal.
    Certified and Muslim-owned both stay, and every listing says which one it is, with a line explaining
    that "Certified" means an admin checked the certificate. **Question for the team:** should "Halal
    only" mean certified only?
  - *Distance is measured from a made-up point* (`config/foodloop.php`, `demo_origin`), not the phone's
    real position, and the page says the distances are demo data. Default "Within 2 km"; the tile steps
    2 km → 5 km → any.
  - *Sold-out deals are not in the list;* their page still opens and says "Sold out" (c07). A listing
    whose window has passed shows "Ended" even before the every-minute job closes it.
  - *Drafts and paused listings answer "not found"* for everyone but their shop and admins.
  - *Money and quantity:* the button total updates as you change the quantity; the limit shown is what
    this customer may still take (3 minus what they already hold, at most what's left).
  - *Demo seed:* pickup windows now start from the last 5-minute mark, so times read "8:45–10:45 PM"
    instead of "8:43–10:43 PM". The "min left" numbers are therefore 15 to 20, 30 to 35 and so on.
- **In the mockups but left out** (not in the build plan): deal alerts ("Turn on deal alerts"), "Notify
  me next time" on a sold-out deal, a real payment method name ("DuitNow") on the booked page (it says
  "Paid (simulated)"), and password reset. Map and Impact are greyed out in the menu until Stage 6.
- **Good to know:** with the session stored in the database (the default in `.env.example`), a full
  database outage shows the styled 500 page ("Something broke on our side", Try again) rather than the
  deals error state, because the session fails first. The deals error state covers a failing deals query.
- **Tested:** 368 tests on MySQL 8.0.46 (1,288 assertions), 366 + 2 skipped on SQLite. New: 117 tests
  for the deals page (order, every filter, tiles, empty and error states), the listing page (each state
  and each kind of visitor), reserving (happy path, form checks, and each refusal: sold out, blocked,
  window ended, not enough left, already holds three, shop suspended), the booked page in every phase,
  cancelling free and late, the orders list, login and register, and the whole journey on the seeded demo
  data.
  - *Browser (Chrome, 390 px and 1280 px):* a scripted walk through the real pages: deals → listing →
    log in → register → back on the listing → 2 portions, pay at pickup → booked page (countdown seen
    ticking) → orders → cancel with confirm → sell a deal out → sold-out page → ended page → account menu
    → log out → 404 → deals with the listings table hidden (503 error state) → Aina's orders and code
    4721 → wrong password → seller login lands on `/seller`. On every page: no sideways scroll, fonts
    loaded, no broken pictures, every button, field and stand-alone link at least 44 px tall (links
    inside a sentence are not counted), no console errors
    other than the two error pages opened on purpose.
  - Compared side by side with mockups c01, c02, c03, c05, c06, c07. Found and fixed this way: "Egg,
    milk, wheat" wrapped in the facts box, the shop name was squeezed by the wider "RM4.50" box, the
    booked page repeated "You're booked" in a second box, and the earlier-orders rows cut off the date.
  - Screenshots: `docs/screenshots/stage4-*.png`.
- **Not tested:** on Windows; with a screen reader; with JavaScript switched off (the pages are built to
  work without it, but this was not checked in a browser).

## 14 Nov 2026 · Stage 3 · Roles, policies, layouts and the design system

- **What:**
  - *Access.* `role:` middleware (`App\Http\Middleware\EnsureRole`) and three route groups in
    `routes/web.php`: customers `/orders`, sellers `/seller`, admins `/admin`. Guests go to `/login`; the
    wrong role gets a 403. Each area has a placeholder page until its stage builds the real one.
  - *Policies* (`app/Policies`): a seller can update, delete, pause and publish only their own shop's
    listings, and publish only when the shop is approved. Only the customer who made a reservation can
    cancel it; only the shop it is for can collect it. Only admins review shops, moderate listings and
    resolve reports. Only customer accounts reserve and report.
  - *Design system.* All style C colours as Tailwind tokens (`resources/css/app.css`). Layouts:
    `x-layouts.customer` (bottom nav on phones, header links on wide screens) and `x-layouts.dashboard`
    with ready-made `x-layouts.seller` and `x-layouts.admin`. Components: `x-button`, `x-card`,
    `x-price-box`, `x-deal-row`, `x-status-pill`, `x-stat-tile`, `x-empty-state`, `x-error-state`,
    `x-spec-grid`, `x-food-photo`, plus `x-tag` (the small "Sold out" / "Surprise Bundle" label the
    mockups use). `App\Support\TimeLeft` writes "18 min left".
  - *Preview pages, local only:* `/dev/components`, `/dev/layouts/customer`, `/dev/layouts/dashboard`,
    built from sample data in memory. `docs/UI_GUIDE.md` has the rules and one example of each component.
- **Choices made on the way (and why):**
  - *A seller who is not approved can still enter the seller area* (to prepare drafts) and sees a notice;
    they just can't publish.
  - *Deals count down to the end of the pickup window* and turn marigold at 35 min or less (in the mockup
    18 and 33 min are highlighted, 42 is not).
  - *Photos use a path relative to the site* (`/storage/...`), so they load whether the owner opens
    `localhost:8000` or `foodloop.test`.
  - *Guests are redirected to `/login`,* which only gets its screen in Stage 4. Until then a guest who
    opens `/orders`, `/seller` or `/admin` in a browser lands on an error page. Expected for now.
  - *The admin menu items* (Overview, Sellers, Listings, Reports, Customers) are a first guess; Stage 7
    should match them to the admin mockup.
- **Tested:** 251 tests on MySQL 8.0.46 (944 assertions), 249 + 2 skipped on SQLite. New: each area lets
  the right role in, sends guests to login and gives the other roles a 403; every policy rule above;
  each component's output; the layouts; the preview pages are 404 outside local; no view contains a hex
  colour.
  - *Browser (Chrome, 390 px and 1280 px):* the three preview pages and the three areas signed in as
    real demo accounts (Aina, Roti Lane, the pending Kedai Kopi Sinar, the admin). No sideways scroll,
    both fonts loaded, no broken pictures, every link and button at least 44 px tall. Compared with
    mockups c01, c02 and c08. Two things found and fixed this way: a long deal title pushed the phone
    page 327 px too wide, and the logo link was only 36 px tall.
  - Contrast ratios for every allowed text/background pair are in `docs/UI_GUIDE.md` (lowest: 4.9).
  - Screenshots: `docs/screenshots/stage3-*.png`.
  - *One flaky test found and fixed.* "Matches the pickups table on the seller mockup" (from Stage 1)
    failed once in about ten full MySQL runs and could not be reproduced. It had two weak spots: it relied
    on the order MySQL happened to return rows in, and Aina's 12 older demo orders had random pickup codes
    that could, rarely, equal one of the five codes the test looks for. Now the query is ordered and
    every seeded pickup code is fixed (history: 1000 to 1011). Passed 4 full MySQL runs in a row after.
- **Not tested:** on Windows; with a screen reader.

## 14 Nov 2026 · Stage 2 follow-up · The owner's four rule decisions

- The owner answered the four questions below (Stage 2 entry). Built as decided:
  1. **Changed my mind:** cancelling within 5 minutes of reserving is always free
     (`change_of_mind_minutes`).
  2. **Max 3 per customer per listing,** counted across their reservations on it that aren't cancelled.
     Config key renamed to `max_qty_per_listing`.
  3. **Refunds:** cancelling a pay-now reservation sets payment to `refunded` (simulated).
  4. **Blocked customers lose their waiting reservations:** when a block starts they are cancelled, the
     portions go back on sale, and `reservations:expire` sweeps for any left behind.
- **Three details Claude assumed and told the owner (no objection):** a late cancel is still refunded (the
  strike is the penalty); a no-show who paid up front is not refunded; the automatic cancels at a block
  are not "late" and add no strikes.
- The rules table in `docs/BUILD_PLAN.md` §3 is updated. No database changes.
- **Tested:** 22 new tests (`tests/Feature/Reservations/TeamDecisionsTest.php`), on SQLite and MySQL.

## 14 Nov 2026 · Stage 2 · Reservation engine

- **What:** `App\Services\ReservationService` with `reserve()`, `cancel()`, `collect(seller, code)`,
  `markNoShow()` and `expireOverdue()`. It is the only code that changes a reservation's status or a
  listing's stock. `App\Services\ListingCloser` closes finished listings and makes the food-bank offer.
  Two commands, `reservations:expire` and `listings:close`, are scheduled every minute in
  `routes/console.php` (run them locally with `php artisan schedule:work`). Every "no" has a reason code
  and a ready-to-show sentence in `App\Enums\RefusalReason`.
- **How overselling is prevented:** `reserve()` runs in a transaction and locks the listing row
  (`lockForUpdate`) before it looks at the stock, so a second request waits and then sees the new number.
  It also locks the shop row so two reservations at one shop can't get the same pickup code. Locks are
  always taken in the same order (listing, seller, reservation, user) to avoid deadlocks.
- **Rules as built (all values from `config/foodloop.php`):**
  - Reserve is refused when: own listing, customer blocked, seller not approved, sold out, listing not
    live, window ended, quantity below 1, above the max (3), or above what's left.
  - Cancel is free up to and including 30 min before the window starts; later it is a late cancel
    (+1 strike). Either way the portions go back on sale, and a sold-out listing becomes live again
    (unless it is paused, closed, past its window, or the shop is no longer approved).
  - Collect only finds codes of `reserved` reservations at that seller's own shop, from the window start
    until the end of the grace period. Pay-at-pickup becomes paid (simulated) at that moment.
  - No-show only after the grace period (window end + 15 min): +1 strike, portions are not resold.
  - Third strike: blocked for 7 days from that moment and the count restarts at 0. A longer block that is
    already running is never shortened.
- **Choices made on the way (and why):**
  - *Closing.* Every published listing becomes `closed` when its window ends, sold-out ones too (the
    screens can still show "sold out" when nothing was left). Drafts are left alone.
  - *The food-bank offer waits for the grace period.* Leftovers = unsold portions + no-shows, and the
    no-shows are only known 15 min after the window. So the offer is created once no reservation on the
    listing is still waiting. One offer per listing, to the first food bank partner.
  - *Demo data follows the engine:* the two blocked demo customers now have 0 strikes (the count restarts
    at a block), and the ended croissant deal is `closed` instead of `sold_out`.
- **Questions for the team (built strictly to the written rules; say if you want them changed):**
  1. Someone who reserves less than 30 min before the window (or during it) can never cancel for free;
     any cancel is a strike. Do you want a short "changed my mind" allowance, e.g. 5 minutes?
  2. "Max 3" is per reservation. Nothing stops one customer making two reservations on the same listing.
     Should it be max 3 per customer per listing?
  3. A cancelled pay-now reservation still says "paid (simulated)"; the data model has no "refunded".
     Fine for a prototype with no real money, or add a refunded status?
  4. A blocked customer keeps the reservations they already made. OK?
- **Tested:** 151 tests on MySQL 8.0.46 (653 assertions), 149 + 2 skipped on SQLite. New in this stage:
  every refusal; free cancel exactly at the cutoff and late one second after; collect at the first and
  last allowed minute, too early, too late, another seller's code, a used code; no-show not before grace;
  first, second and third strike; re-running both commands changes nothing; a whole evening run minute
  by minute; the commands find nothing to do on fresh demo data.
  - *Race test (MySQL only):* 8 separate PHP processes reserve the last item at the same instant: exactly
    1 succeeds, 7 get "sold out". Same with 5 in stock and 2 each: 2 succeed, 1 portion left. To check the
    test itself, the lock was removed once: all 8 "won" and the test failed; with the lock back it passes
    (run 5 times).
  - Pickup codes: with 9,999 of the 10,000 codes waiting at a shop, the next reservation gets the one
    free code.
- **Not tested:** on Windows. The race test starts PHP processes with Symfony Process, which supports
  Windows, but it has only been run on Linux.

## 14 Nov 2026 · Stage 1 · Database, models, demo data

- **What:** 6 migrations for the §4 data model (users get `role`, `no_show_strikes`, `blocked_until`; new
  tables `sellers`, `listings`, `reservations`, `reports`, `food_bank_partners`, `donations`). 12 enums in
  `app/Enums/`, each with a `label()` for the screens. 7 models with relationships and casts, a factory for
  each. `App\Support\Money` turns sen into text. `DemoSeeder` builds the mockup data; the demo accounts are
  listed in README.
- **Rules enforced in code already (with tests):**
  - A listing can only be `certified` if its shop is approved, claims certification and has a certificate
    file. Checked on every save in the `Listing` model, so no page can skip it.
  - A listing can only go `live` if its shop is approved (pending, rejected and suspended are refused).
  - Halal status is required on every listing (the database refuses an empty one).
  - A sign-up can't set its own role, strikes or block: those fields are not fillable.
- **Choices made on the way (and why):**
  - *Money text:* whole ringgit shows as `RM8`, anything with sen as `RM4.50`. The mockup wrote `RM4.5` in
    one place and `RM6.50` in another; two decimals is the normal way to write money.
  - *Demo times move with the clock.* Pickup windows are set relative to the moment of seeding (bundle window
    opens in about 45 min, curry puffs close in about 20 min, …; since Stage 4 they start from the last
    5-minute mark so the clock times look real), so the demo always has deals closing soon. Reseed
    right before the pitch.
  - *`ssm_no` can be empty,* because the admin mockup shows a shop with the SSM number "Missing".
  - *Demo stock adds up:* every portion that is not available belongs to a reservation.
- **Demo data that is NOT in the mockups (made up to fill gaps, all labelled demo):** the croissants'
  original price (RM12), quantities for listings other than the bundle, allergens and descriptions other
  than the bundle's, the customers' surnames, the halal status of three shops (set to Muslim-owned), and the
  shop coordinates (offsets from a made-up point). Registration numbers, phones and addresses are left as
  `[SSM NO.]`, `[PHONE]`, `[SHOP ADDRESS]`; the food bank is `[FOOD BANK PARTNER NAME]`.
- **Mockup numbers the demo data does not reproduce:** the big totals ("12 deals close within the hour",
  "8 bundles left", seller "RM312 this week", admin "48 live listings", "1,204 meals rescued") and the
  Warung Pagi report (it shows a shop listed as certified without a certificate, which the rules now make
  impossible to create). Aina's own numbers do match: 14 meals, RM118 saved, 1-2-4-2-3-2 per week.
- **Tested:** 75 tests, 368 assertions, passing on SQLite in memory and on MySQL 8.0.46: relationships,
  casts, money formatting, the rules above, enums in step with `config/foodloop.php`, and the seeded data
  against the mockups (shops, prices, pickup codes, Aina's impact, no-show limits, photos exist, stock adds
  up). The seeder is also tested at awkward times (Monday 00:05, Sunday 23:55, New Year's Day).
  `php artisan migrate:fresh --seed` run on a SQLite file and on MySQL; rollback works; a demo photo loads
  at `/storage/listings/demo/bakery-bundle.svg`. No screens changed, so no browser screenshots this stage.
- **Not tested:** on Windows (same as Stage 0).

## 13 Nov 2026 · Stage 0 · Project setup

- **What:** Laravel 13.42 app at the repo root (PHP 8.3+). Fortify 1.42 for sign-in, Tailwind 4 +
  Alpine 3 through Vite 8, Pest 4 for tests. `config/foodloop.php` holds the §3 rule values;
  time zone is Asia/Kuala_Lumpur; `.env.example` is set up for MySQL with comments. The style C colours
  and fonts are Tailwind tokens in `resources/css/app.css`. A temporary home page (`/`) in style C replaces
  Laravel's welcome page until the real screens arrive. README has the Windows setup steps.
- **Choices made on the way (and why):**
  - *Fonts ship with the app.* Space Grotesk and Inter come from the `@fontsource` npm packages instead of
    a Google Fonts link. Same fonts, but they load with no internet (safer for the pitch demo).
  - *Fortify has no screens yet.* `views` is off and only registration + login/logout are on. Stage 4 adds
    our own login/register screens and turns on password reset (its email link needs a screen to land on).
    Two-factor and passkeys are not used; their migrations were removed.
  - *Two test configs.* `phpunit.xml` = SQLite in memory (fast). `phpunit.mysql.xml` = same tests on the
    MySQL database `foodloop_test`.
- **Tested:** 10 tests, 32 assertions (home page loads and carries the demo-data note; every §3 value and
  the time zone; register, duplicate email, login, wrong password, logout). Passed on SQLite in memory and
  on MySQL 8.0.46. `npm run build` succeeds. Home page opened in Chrome at 390 px and 1280 px: status 200,
  no console errors, no failed requests, both fonts loaded, nothing wider than the screen, the "How it
  works" tabs switch (Alpine works). Screenshots: `docs/screenshots/stage0-home-*.png`.
- **Not tested:** the Windows/Laragon setup steps in README. They are the standard Laravel commands and
  were run on Linux in the build environment, not on the owner's machine. Owner to run them and report.

## 13 Nov 2026 · Build plan and session instructions

- **What:** `docs/BUILD_PLAN.md` (stack, business rules with config keys, data model, reservation state
  machine, 9 stages with inputs and "done when") and `CLAUDE.md` (what every new session reads first).
- **Why:** the owner is switching to Fable for a limited trial. Stages 0–4 hold the hard, foundational
  work (reservation engine with row locking, scheduler jobs, roles, design-system components, one full
  reference slice), so later models extend patterns instead of inventing them. New sessions read the
  repo docs instead of this long chat, which saves usage.
- **Correction:** the first workflow said no-show stock goes back on sale; it can't (window already over).
  It now becomes leftovers for the food-bank offer. Whiteboard updated to match.
- **Tested:** n/a (planning). Business rules still need the team's confirmation.

## 13 Nov 2026 · Food pictures added; repo set up

- **What:** Owner approved style C but asked for pictures of the food. Claude drew 7 flat illustrations
  (bakery bundle, croissants, curry puffs, iced latte, nasi ayam, nasi campur, sandwich pack), saved in
  `docs/demo-food/`. They now appear on: deals list (photo thumbnails), listing (photo header, labelled
  "Example of a typical bundle"), sold out (greyed photo), impact (recent rescues), seller dashboard
  (live listings), create listing (uploaded-photo preview) and admin (reported listings).
- **Why illustrations, not stock photos:** no licence questions, and they match the palette. In the real
  app sellers upload their own photos. If the team sends real photos of campus food, they can replace these.
- **Layout change:** the deal rows lost the giant minute numbers to make room for the photo; urgency now
  shows as "18 min left" with a marigold box for deals closing soonest.
- **Tested:** all 10 screens re-opened in the canvas viewer; every image loaded (the check used local
  copies of the pictures because uploaded images don't show in the check environment); no errors; nothing
  wider than the screen at phone width. New PNGs exported.
- **Repo:** `hamoodalsahur/foodloop` (note: it is **public**). First commit adds `docs/`.

## 13 Nov 2026 · Style C full set, states, browser check

- **What:** 10 screens in style C on the canvas page "C · Full set":
  C1 deals, C2 listing, C3 booked + pickup code, C4 impact, C5 empty (nothing nearby),
  C6 error (offline, with Try again), C7 sold out, C8 seller dashboard, C9 create listing, C10 admin.
  C1 → C2 → C3 click through in Play; the seller's "Mark collected" and the create-listing preview work.
- **Added colour:** error red `#B42318`, used only for errors, no-shows and "Reject".
- **Tested:** every screen opened in the real canvas viewer (headless Chromium), screenshots looked at;
  desktop pages also at 420 px phone width. No script errors. Checked that nothing is wider than the screen at phone width.
  Limits: Google Fonts can't load in the check environment, so the PNGs show fallback fonts (Space Grotesk/Inter
  will show for the owner); browser default form controls.
- **Fixed after the check:** scrollbars hidden in phone scroll areas; seller table cells no longer wrap
  (pickups table moved to full width); a hidden table label made the seller page scroll sideways on phones
  (fixed); "−60%" no longer overlaps the bag icon (icon removed); orphaned "1" in "Surprise Bundle × 1";
  error icon ring shape; ticker text shortened.
- **PNGs:** `docs/mockups/c00-customer-screens-overview.png` plus one PNG per screen (`c01`–`c10`, and
  `-phone` versions of C8 and C10).

## 13 Nov 2026 · Style C chosen, palette replaced

- **What:** Owner picked direction C (bold, countdown-led). Palette changed from tomato red / black /
  beige to forest `#0E2A20`, marigold `#FFB800`, cream `#FAF6EE`.
- **Why:** Owner asked for a better palette. Green signals food rescue and sustainability; marigold is
  warm and food-like and still grabs attention. Rejected: keeping red (felt harsh); lime (too close to
  direction A).
- **Urgency:** deals closing within ~30 min get a marigold box behind the minutes, instead of red text.
- **Tested:** colour contrast of text pairs checked by calculation (forest on cream and on marigold, white on forest, all ≥ 4.5:1).
  Visual browser check: ⏳ in progress.

## 13 Nov 2026 · Style directions A–D

- **What:** Four looks, each with a marketplace and listing screen: A Midnight (dark, lime),
  B Airy (white, photo-first), C Bold (urgent, big numbers), D Soft bento (pastel tiles, dashboard home).
- **Why:** Owner wanted a more modern UI and to choose between options.
- **Tested:** not checked in a browser at the time (⚠️ done before the workflow guide applied).

## 13 Nov 2026 · First draft: workflow + 7 screens

- **What:** Whiteboard with the main flow (seller posts → system publishes → customer browses → reserves →
  system holds stock + issues code → customer collects → seller verifies → system updates stock/impact),
  plus rules for no-shows, cancellations, leftovers (food bank), reports and seller verification.
  Seven screens: marketplace, listing, reservation, impact, seller dashboard, create listing, admin.
- **Business rules introduced (⚠️ team to confirm):** 15-minute grace after the pickup window, then
  auto-release; 3 no-shows → 7-day reservation limit; free cancellation until 30 min before the window;
  halal status required on every listing; allergens listed by the seller; leftovers offered to a food bank.
- **Why these rules:** collection-only marketplaces depend on no-show handling; halal status matters to
  most Malaysian customers; the food bank loop differentiates FoodLoop from existing apps.
- **Competitor research used:** Value Food (Klang Valley, surprise bags 50–70% off, 100k+ downloads,
  300+ merchants, per Malay Mail, 9 Jul 2026); ReMeal (pickup-only, deal-hunter users, per Vulcan Post,
  2 Dec 2024); SWCorp edible food waste ~6,000 t/day in 2025 (NST, 15 Jun 2026).
