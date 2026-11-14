# FoodLoop · build plan

Status: ✅ approved by the owner 13 Nov 2026 · Stages 0–5 done, Stage 6 next · Written 13 Nov 2026 by Claude (design lead) for the build sessions.

**Who does what.** Stages marked **F** are the hard, foundational ones: run them on the strongest model
(Fable). Stages marked **A** follow patterns that F stages set up, so any model can do them by copying
those patterns. Do the stages **in order**. Each stage ends with working, tested code committed to `main`,
so the project is usable wherever the work stops.

**Read only what the stage needs.** Each stage lists its inputs. Don't read every mockup or every doc.

---

## 1. Product in one paragraph

Campus sellers post surplus food at a discount with a pickup window. Students browse, reserve (pay now,
simulated, or pay at pickup), get a 4-digit pickup code, and collect it in the window. The seller checks
the code and marks it collected. Reservations not collected 15 minutes after the window ends become
no-shows; 3 no-shows block reserving for 7 days. When a listing closes with stock left, the seller can
offer it to a partner food bank. Admins approve sellers and handle reports.

## 2. Stack (decided)

| Part | Choice | Why |
|---|---|---|
| Framework | Laravel, latest stable (check `composer create-project laravel/laravel` at Stage 0) | Owner's stack |
| Database | MySQL 8 (owner's Laragon/Herd). Tests run on SQLite in-memory **and** MySQL | Guide: test on the real database |
| Views | Blade + Blade components, Tailwind CSS via Vite, Alpine.js for small interactions (qty stepper, countdown) | No SPA needed; simplest for other models to extend |
| Auth | Laravel Fortify (headless) with our own Blade views | Maintained, gives rate limiting and password reset; our views match the mockups |
| Tests | Pest (or PHPUnit if Pest is awkward) | |
| Time zone | `Asia/Kuala_Lumpur` (set in `config/app.php`) | All pickup windows are local time |
| Money | Integers in **sen** (`price_sen`), shown as `RM8.00` by one helper | No float rounding errors |
| Payments | **Simulated only** (no gateway). `payment_method`: `pay_now` or `pay_at_pickup` | Prototype scope |
| Photos | `storage/app/public/listings`, `php artisan storage:link`; demo photos from `docs/demo-food/*.svg` | |

## 3. Business rules (the code must enforce these)

⚠️ Proposed by Claude; the team still has to confirm them. Keep each number in **one config file**
(`config/foodloop.php`) so changing a rule is a one-line edit.

| Rule | Value | Config key |
|---|---|---|
| Max quantity per customer per listing (across all their reservations on it that aren't cancelled) | 3 | `max_qty_per_listing` |
| Reservations close | when the pickup window **opens** (team decision, 14 Nov) | `Listing::reservationsCloseAt()` |
| Free cancellation until | 30 min before pickup window starts | `free_cancel_minutes` |
| "Changed my mind": cancelling is always free within | 5 min of reserving | `change_of_mind_minutes` |
| Late cancellation | counts as a no-show strike | |
| Cancelled pay-now reservation | payment becomes `refunded` (simulated); no-shows are not refunded | |
| Grace after window ends | 15 min, then reservation becomes `no_show` | `grace_minutes` |
| Strikes before block | 3 | `strikes_to_block` |
| Block length | 7 days | `block_days` |
| When a block starts | the customer's waiting reservations are cancelled (no extra strike, stock back on sale) | |
| Halal status | required on every listing: `certified`, `muslim_owned`, `not_halal` | |
| `certified` allowed only if | the seller has an approved halal certificate on file | |
| Seller must be | `approved` by an admin before a listing can go live | |

**Correction to the workflow whiteboard:** it says no-show stock goes "back on sale". It can't, because the
no-show is only decided after the pickup window has ended. That stock is added to the listing's leftovers,
which feed the food-bank offer instead.

## 4. Data model

All tables have `id` and timestamps. Enums are PHP backed enums in `app/Enums/`.

| Table | Key columns | Notes |
|---|---|---|
| `users` | name, email, password, `role` (customer, seller, admin), `no_show_strikes` (int, default 0), `blocked_until` (nullable datetime) | |
| `sellers` | `user_id` (unique), business_name, ssm_no, phone, address, lat, lng, `halal_claim` (certified, muslim_owned, not_halal), `halal_cert_path` (nullable), `status` (pending, approved, rejected, suspended), approved_at | One shop per seller account |
| `listings` | `seller_id`, `type` (regular, flash, bundle), title, description, `category` (bakery, meals, kuih, drinks, groceries), photo_path, `original_price_sen`, `price_sen`, `quantity_total`, `quantity_available`, `pickup_start`, `pickup_end`, `halal_status`, `allergens` (json array), `offer_leftovers` (bool), `status` (draft, live, paused, sold_out, closed), published_at, closed_at | `quantity_available` only changes inside the reservation service |
| `reservations` | `listing_id`, `customer_id`, quantity, `total_sen`, `payment_method`, `payment_status` (simulated_paid, unpaid), `pickup_code` (char 4), `status` (reserved, collected, cancelled, no_show), `expires_at` (= pickup_end + grace), collected_at, cancelled_at, `late_cancel` (bool) | Index (`listing_id`, `status`), (`status`, `expires_at`) |
| `reports` | `reporter_id`, `listing_id`, `reason` (halal_info, food_quality, wrong_description, other), details, `status` (open, actioned, dismissed), `admin_note`, resolved_by, resolved_at | |
| `food_bank_partners` | name, contact | Seed one placeholder: `[FOOD BANK PARTNER NAME]` |
| `donations` | `listing_id`, `food_bank_partner_id`, quantity, `status` (offered, collected, declined) | Created when a listing closes with leftovers and `offer_leftovers` is on |

**Pickup code:** 4 random digits, unique among the seller's `reserved` reservations (retry on clash).

**Impact numbers** (no stored totals, always computed): meals rescued = sum of quantity of `collected`
reservations; money saved = sum of (original − price) × quantity of those. Label them "from completed
pickups only".

## 5. Reservation lifecycle (the state machine)

```
reserved ──(seller enters correct code in window)──▶ collected
   │
   ├──(customer cancels ≥ 30 min before window)───▶ cancelled           stock returned
   ├──(customer cancels later)─────────────────────▶ cancelled + late_cancel   stock returned, +1 strike
   └──(expires_at passes, scheduler)───────────────▶ no_show             +1 strike, stock → leftovers
```

No other transitions. Every transition goes through `App\Services\ReservationService`, inside a DB
transaction, so controllers never change status or stock directly.

---

## 6. Stages

### Stage 0 · Project setup · F · ✅ done 13 Nov 2026

**Inputs:** this file §2, `docs/WORKING_AGREEMENTS.md`.

- New Laravel app at the repo root (keep `docs/`, README). Fortify, Tailwind + Vite, Alpine, Pest.
- `.env.example` with MySQL settings and comments; `.env` git-ignored.
- `config/foodloop.php` with the §3 values. App time zone set.
- Give the owner the PowerShell setup steps for Laragon/Herd (≤ 10 min, exact commands, what success
  looks like), and put them in README under "Run it locally".
- **Done when:** `php artisan test` passes (example test), the app's home page loads locally, README has setup steps.

### Stage 1 · Database, models, demo data · F · ✅ done 14 Nov 2026

**Inputs:** §3, §4.

- Migrations, enums, models with relationships and casts, factories.
- `DemoSeeder`: the mockup data (Roti Lane Bakery, Kafe Seri Ilmu, Daily Grind Café, Mak Long’s Kuih; the
  listings and prices from the mockups; one admin, one seller per shop, a few customers; demo photos
  copied from `docs/demo-food/`). Clearly named demo accounts listed in README.
- **Done when:** `php artisan migrate:fresh --seed` works on SQLite and MySQL; model tests for
  relationships and money formatting pass.

### Stage 2 · Reservation engine · F (the hardest part) · ✅ done 14 Nov 2026

**Inputs:** §3, §4, §5.

- `ReservationService` with `reserve()`, `cancel()`, `collect(seller, code)`, `markNoShow()`.
- `reserve()` locks the listing row (`lockForUpdate`) so two students can't buy the last item. It refuses:
  listing not live, window ended, quantity over stock or over the max, customer blocked, customer is the
  seller, seller not approved.
- `collect()` only finds codes among **that seller's** `reserved` reservations, and only within the window
  (allow from window start to `expires_at`).
- Strike handling on the user: +1 strike; at 3, set `blocked_until` and reset strikes to 0.
- Scheduled commands (registered in the scheduler, run every minute):
  `reservations:expire` (no-shows) and `listings:close` (past `pickup_end` → `closed`; create a donation
  offer when leftovers > 0 and `offer_leftovers` is on). Both safe to re-run.
- **Tests (must have):** no overselling under concurrent reserves (simulate two reserves for the last item);
  every refusal above; free vs late cancel; no-show after grace and not before; 3rd strike blocks for 7
  days; code from another seller is rejected; re-running the commands changes nothing the second time.
- **Done when:** all tests pass on SQLite and MySQL.

### Stage 3 · Roles, policies, layouts and the design system · F · ✅ done 14 Nov 2026

**Inputs:** `docs/mockups/c01-deals.png`, `c02-listing.png`, `c08-seller-dashboard.png` (look at these three only).

- Middleware/policies: customer pages, seller pages (approved sellers only for publishing), admin pages.
  A seller can only touch their own listings and reservations.
- Tailwind theme tokens from style C: forest `#0E2A20`, marigold `#FFB800`, cream `#FAF6EE`,
  muted text `#55605A`, line `#E6DECC`, on-dark muted `#A8C2B6`, error `#B42318`; Space Grotesk headings,
  Inter body (Google Fonts).
- Layouts: `layouts/customer` (phone-first, bottom nav: Deals, Map, Orders, Impact) and
  `layouts/dashboard` (forest sidebar, stacks on phones) for seller and admin.
- Blade components that every later page reuses: `x-button` (primary, outline, marigold), `x-card`,
  `x-price-box`, `x-deal-row`, `x-status-pill` (reserved, collected, no_show, cancelled), `x-stat-tile`,
  `x-empty-state`, `x-error-state` (with "Try again"), `x-spec-grid`, `x-food-photo`.
- Write `docs/UI_GUIDE.md`: one example of each component and the rules (44 px touch targets,
  contrast, money helper, no emoji).
- **Done when:** a components demo page (`/dev/components`, local only) shows every component; role
  access tests pass.

### Stage 4 · Student flow, end to end · F (the reference slice) · ✅ done 14 Nov 2026

**Inputs:** `c01`–`c07` mockups, `docs/UI_GUIDE.md`.

- Deals list (closing soonest first; halal-only and category filters; empty and error states), listing
  page (sold-out state), reserve form (quantity, payment method), booked page with pickup code and
  countdown, cancel button with the free/late wording, Orders list.
- Login and register screens (customer) in the same style.
- Feature tests for the happy path and the sold-out, blocked and window-ended cases.
- This slice is **the pattern** later stages copy: controller → form request → service → Blade page →
  feature test. Write a short "How to add a page" section in `docs/UI_GUIDE.md`.
- **Done when:** a student can go from deals → reserve → code on the seeded data in a browser; tests pass;
  screenshots of each page taken at phone width and compared with the mockups.

---

*Stages below follow the patterns above. Any model can do them.*

### Stage 5 · Seller pages · A · ✅ done 14 Nov 2026

**Inputs:** `c08`, `c09` mockups, UI guide, Stage 4 code as the example.

- Seller register + shop profile (pending approval screen). Dashboard: stat tiles, pickups table with code
  check and "Mark collected" (calls `ReservationService::collect`), live listings with progress.
- Create/edit listing with photo upload, live price/discount preview, halal and allergen fields, pause.
- "Offer to food bank" on closed listings with leftovers.
- **Done when:** a seller can post, a student can reserve it, the seller collects it; feature tests pass.

### Stage 6 · Impact page · A

**Inputs:** `c04` mockup. Computed numbers only (§4). Weekly bar chart from collected reservations.

### Stage 7 · Admin · A

**Inputs:** `c10` mockup. Approve or reject sellers (show halal certificate), reports (hide listing,
warn seller, dismiss), customers with strikes or blocks, platform totals. Report button on the listing page.

### Stage 8 · Polish and demo prep · A

Map placeholder page, notifications text, phone-width pass on every page, seed data for the pitch demo,
README final, a 2-minute demo script in `docs/DEMO_SCRIPT.md`.

---

## 7. Out of scope (don't build)

Real payment gateway, delivery, push or SMS notifications, real maps, native mobile apps.

## 8. Definition of done for every stage

Tests pass on SQLite and MySQL · pages checked in a browser at phone and desktop widths · no secrets in
git · `docs/PROJECT_NOTES.md` entry and status table updated · `docs/WORKING_AGREEMENTS.md` status
updated · one commit in the owner's voice, no co-author lines · owner told the exact commands to see it.
