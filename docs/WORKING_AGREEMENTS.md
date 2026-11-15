# FoodLoop · working agreements

How the owner and Claude work on FoodLoop. Read this at the start of every session, then check the
repo for changes before acting. Last updated: 15 Nov 2026 (demo:refresh and the bigger demo menu).

## Project facts

| | |
|---|---|
| Project and goal | FoodLoop: students reserve discounted surplus food from nearby sellers and collect it themselves |
| Type | ENT600 Technology Entrepreneurship group assignment (NPD report + 5-minute pitch); responsive web app |
| Repository | `hamoodalsahur/foodloop` on GitHub, branch `main` (public) |
| Owner's computer | Windows, PowerShell, Laragon or Herd for PHP/MySQL |
| Stack | Laravel (PHP) + MySQL; Blade views styled to the approved mockups |
| Mockups | Design canvas "FoodLoop UI Mockups" (claude.ai artifact) + checked PNG exports |
| Workflow diagram | Whiteboard "FoodLoop System Workflow" (claude.ai artifact) |
| Notes file | `docs/PROJECT_NOTES.md` |
| Who writes what | Claude designs, builds, tests and keeps notes/evidence. The team (or another AI) writes the report from the notes |
| Deadlines | Report 19 Nov 2026 · pitch Week 9, 22–26 Nov 2026 |
| Hard requirements | Follow the ENT600 NPD template; group of 5–6; 2D prototype evidence (screens) |

## Rules from the workflow guide that apply

- Owner decides; Claude recommends. Casual, short messages, outcome first.
- Visual work: mockups of every state (normal, empty, error, success, phone width) approved before building.
- Sizeable work: plan first, build after a "yes".
- Commits: short, in the owner's voice, **no co-author or session trailers**. Stage only what belongs.
- Never commit secrets (`.env`); keep `.env.example`.
- Every feature has tests; UI checked in a real browser with screenshots.
- Demo data is labelled as demo data and never presented as real results.
- Owner's manual tasks: ≤ 10 minutes, exact PowerShell commands, realistic examples.

## Decisions (with reasons)

| Date | Decision | Why |
|---|---|---|
| 13 Nov | Web app (no IoT), customer collects food; no delivery | Cheaper to build; keeps the focus on surplus food, not logistics |
| 13 Nov | Focus on building the system first; the report waits | Owner's call |
| 13 Nov | Visual style C "Bold", palette forest `#0E2A20`, marigold `#FFB800`, cream `#FAF6EE`; Space Grotesk headings, Inter body | Owner picked C; Claude replaced the original tomato-red palette (owner asked for a better one) with colours that read as food + sustainability |
| 13 Nov | Business rules: 15-min grace then auto-release; 3 no-shows = 7-day reservation limit; free cancel until 30 min before the window | Proposed by Claude; ⚠️ team still to confirm (owner refined four of them on 14 Nov, below) |
| 13 Nov | Halal status is a required listing field (JAKIM certified / Muslim-owned / not halal) | Essential for Malaysian users |
| 13 Nov | Leftovers can be offered to a partner food bank ("sell-then-donate loop") | Differentiates FoodLoop from Value Food / ReMeal |
| 13 Nov | GitHub repo `hamoodalsahur/foodloop` (created public) | Owner's choice |
| 13 Nov | Food pictures: drawn illustrations as stand-ins for seller photos | Owner wanted pictures; no licence issues; real photos can replace them |
| 13 Nov | Build split: strongest model (Fable trial) does stages 0–4 (setup, data model, reservation engine, roles + design system, student flow); other models do 5–8 | Owner wants the trial spent on the hard foundation so cheaper models can continue it |
| 13 Nov | Stack details: Fortify auth with own Blade views, Tailwind + Alpine, Pest, money in sen, simulated payments, time zone Asia/Kuala_Lumpur | Simple for later models to extend; avoids float money bugs |
| 13 Nov | No-show stock is not resold (window already ended); it becomes leftovers for the food-bank offer | Fixes a contradiction in the first workflow diagram |
| 13 Nov | Local setup: Laragon/Herd on Windows | Owner's choice |
| 13 Nov | Mockup approvals: clickable canvas + PNGs | Owner's choice |
| 13 Nov | Fonts are bundled with the app (`@fontsource` npm packages), not linked from Google Fonts | Same fonts, but the demo works with no internet |
| 14 Nov | Money is written `RM8` or `RM4.50` (two decimals whenever there are sen) | The mockups mixed `RM4.5` and `RM6.50`; this is the normal way |
| 14 Nov | Demo pickup times are relative to the moment of seeding; reseed right before a demo | The demo always shows deals closing soon |
| 14 Nov | The halal-certificate rule and the approved-seller rule are checked inside the `Listing` model on every save | No page or script can skip them |
| 14 Nov | Listings close when their pickup window ends; the food-bank offer is made after the 15-min grace, counting unsold portions plus no-shows | Only then is the number of leftovers final |
| 14 Nov | At the third strike the block starts and the strike count restarts at 0 | As written in the build plan (Stage 2) |
| 14 Nov | Cancelling within 5 minutes of reserving is always free | Owner's decision: fair to someone who reserved late and changed their mind |
| 14 Nov | Max 3 portions per customer per listing (not just per reservation) | Owner's decision: closes the loophole of reserving twice |
| 14 Nov | A cancelled pay-now reservation becomes "refunded (simulated)"; no-shows are not refunded | Owner's decision |
| 14 Nov | When a customer gets blocked, their waiting reservations are cancelled and the food goes back on sale | Owner's decision |
| 14 Nov | Every page follows route → controller → form request → service → Blade → feature test (`docs/UI_GUIDE.md`, "How to add a page") | One pattern for the later stages to copy |
| 14 Nov | ~~Distances are measured from a made-up "you are here" point~~ (replaced 15 Nov, below) | The pilot location was still open |
| 14 Nov | ~~Left out of the prototype: deal alerts, "Notify me next time", password reset~~ (all built 15 Nov) | In the mockups but not in the build plan |
| 14 Nov | Every screen is built from the components and rules in `docs/UI_GUIDE.md`; colours by name, money through the helper, 44 px touch targets, no emoji | Keeps later stages (and other models) on the approved style |
| 13 Nov | Fortify screens and password reset wait for Stage 4; two-factor and passkeys are not used | Keeps the prototype small; the reset email needs a screen first |
| 14 Nov | Reservations close when the pickup window **opens**, not when it ends | Team decision on the Stage 4 question; matches the mockup's "Reservations close in" |
| 14 Nov | "Halal only" stays as built: hides only "not halal" (certified and Muslim-owned both show) | Team: certified-only filter is too much hassle |
| 14 Nov | Fable trial work ends after Stage 4; Stages 5–8 are built in the owner's main chat | Owner's call |
| 14 Nov | Once anyone has reserved, a listing's price, portions, pickup time and halal status are locked; name, photo, description and pausing stay editable | Owner's decision: students keep the deal they booked |
| 14 Nov | Shops sign up on their own "Sell on FoodLoop" page (`/sell`) | Owner's decision: clearer than a role choice on the student sign-up |
| 15 Nov | Feature freeze: from now on only fixes and polish on the most-used pages | Owner: priority to the features people use most, not more features |
| 15 Nov | Demo shop details read `DEMO-0001` / "Demo number" / "Demo address near campus" | Clearly made up but not looking unfinished; realistic numbers could match real companies |
| 15 Nov | Notifications are in-app only (bell and inbox), no email, push or SMS | As the build plan says; nothing to set up for the demo |
| 15 Nov | ⚠️ Team to confirm: on a laptop (`APP_ENV=local`, no real email) the forgot-password page shows an "Open the reset link" button so the reset can be demoed | The prototype sends no real email; never shown in production |
| 14 Nov | Fable's calls in Stages 6–8 (⚠️ team to confirm): "Warn seller" is one click; suspending a shop keeps its reservations; the impact page says "Everyone on FoodLoop" (computed) instead of "Campus"; the map is a simplified drawing, not a street map (replaced 15 Nov by a street map, drawing kept as backup) | Kept the prototype small; real maps need internet at the pitch |
| 15 Nov | Pilot location: **UiTM Kedah, Merbok** (Sungai Petani). "You are here" is the main gate for every student (no GPS); the deals page shows 5 km by default | Owner's decision; the shops around campus are 0.1–4.5 km away |
| 15 Nov | Demo shops are **real food places within 5 km of campus**, with real names and positions, clearly labelled as demo (they haven't joined); their SSM and phone numbers stay fake. The 3 shops waiting for approval stay made up | Owner's decision. Made-up shops there so no real business appears to have applied without an SSM number |
| 15 Nov | ⚠️ Team to note: Bonda Mawar Cake House is shown as "halal certified" in the demo. That is demo data, not a check of their real certificate; say so if asked, or switch it to Muslim-owned in `DemoSeeder` | The demo needs one certified shop |
| 15 Nov | Street map with Leaflet + OpenStreetMap tiles (free, no key); falls back to the drawing after 7 s without tiles | Real map for the pitch without accounts or cost; never an empty page offline |
| 15 Nov | Installable web app (PWA) instead of a native app; opened on phones through a free Cloudflare https tunnel | A Play Store / App Store app is weeks of work; the PWA looks and opens like an app |
| 15 Nov | `php artisan demo:refresh` resets only the food: accounts, past orders, donations, reports, strikes stay. `migrate:fresh --seed` stays the full wipe | Owner: reset the demo without losing accounts or history |
| 15 Nov | Demo menu of 29 items; each reset posts 6–9 at random. Two deals never change (Bakery Surprise Bundle with code 4721, Sandwich & pastry pack with code 2468) | Owner wanted variety; the demo script needs those two |
| 15 Nov | Fable's calls on the refresh (⚠️ owner to confirm): it removes **every** waiting reservation and closes **every** open listing (also ones you made yourself as a seller, drafts too); a refreshed-away listing never goes to the food bank or the seller's "Left over at closing?" list; the croissant deal, Mei Ling's pickup and her report are re-added at most once a day; tests run with `DEMO_MENU=mockup` (the six mockup deals) | Keeps the history honest and the dashboards from filling up with refresh leftovers |

## Current status

| Item | Status |
|---|---|
| System workflow whiteboard | ✅ done |
| First-draft mockups (green style) | ✅ done, superseded by style C |
| Style directions A–D | ✅ done; C chosen |
| Style C: all screens + states | ✅ approved; food pictures added after owner's feedback |
| Build plan (`docs/BUILD_PLAN.md`) | ✅ approved by owner (13 Nov) |
| Laravel prototype | 🚧 Stage 0 (setup) ✅ done 13 Nov · Stage 1 (database, models, demo data) ✅ done 14 Nov · Stage 2 (reservation engine) ✅ done 14 Nov · Stage 3 (roles, layouts, design system) ✅ done 14 Nov · Stage 4 (student flow) ✅ done 14 Nov · Stage 5 (seller flow) ✅ · Stage 6 (impact) ✅ · Stage 7 (admin) ✅ · map ✅ · Stage 8 (polish, demo script) ✅ · all on `main` 15 Nov |
| Concept testing with students | ❌ not started |

## Owner still needs to

- Run the README "Run it locally" steps once on the Windows machine (about 10 min) and report any error.
- Confirm the business rules above with the team (the 4 questions from Stage 2 are answered and built).
- Open the design-system pages once (README → "See the design system") and say if anything looks off.
- Walk through README → "Try the student flow" and "Try the seller flow" once on the Windows machine and say if anything looks off.
- Optional: put real photos of campus food in `docs/demo-food/real/` (names in its README, 29 items now) and run `php artisan demo:refresh`.
- Run `git pull`, `php artisan migrate:fresh --seed` once, then try `php artisan demo:refresh` and look at the deals page and `/map` on the phone.
- Rehearse `docs/DEMO_SCRIPT.md` once on the Windows laptop.
- Decide whether the repo should stay public (it is public now).
- Name a real food bank partner (placeholder for now).

## Pending questions

- ~~Which campus/area is the pilot location?~~ UiTM Kedah, Merbok (15 Nov).
- Does the lecturer accept interface screenshots as the "2D prototype"?

## Next steps

1. ✅ Owner approved the style C screens (asked for food pictures, now added).
2. ✅ Build plan written: `docs/BUILD_PLAN.md`. Stages 0–4 (marked **F**) are the foundation and go to
   the strongest model (Fable); stages 5–8 (**A**) follow those patterns and suit any model.
3. ✅ Stage 0 done: Laravel app set up, tests pass on SQLite and MySQL (see `docs/PROJECT_NOTES.md`).
4. ⏳ Owner runs the README "Run it locally" steps on Windows and reports what happens.
5. ✅ Stage 1 done: database, models, demo data (75 tests, SQLite + MySQL).
6. ✅ Stage 2 done: reservation engine (151 tests on MySQL incl. a real race for the last item).
7. ✅ Owner's four rule decisions built (changed-my-mind 5 min, max 3 per customer per listing, refunds, blocked customers lose waiting reservations).
8. ✅ Stage 3 done: roles, policies, layouts, design system, `/dev/components`, `docs/UI_GUIDE.md` (251 tests on MySQL).
9. ✅ Stage 4 done: the student flow end to end (368 tests on MySQL, walked through in Chrome at phone and desktop width).
10. ✅ Stage 5 done: the seller flow (420 tests on SQLite, checked in Chromium at phone and desktop width).
11. ⏳ Owner walks through README → "Try the seller flow" on Windows.
12. ✅ Stages 6, 7, map and 8 done by Fable, squashed into `main` 15 Nov (513 tests on SQLite, 515 on MySQL per Fable).
13. ⏳ Owner rehearses `docs/DEMO_SCRIPT.md` once on the Windows laptop.
14. ⏳ Team checks Fable's open decisions (below) and adds real food photos if wanted.
15. ✅ 15 Nov (Fable): in-app notifications for every role, "Notify me next time" and deal alerts, the rest of the admin mockup (Ask for info, Listings, Food banks), Forgot your password. 588 tests on SQLite, 590 on MySQL.
16. ✅ 15 Nov: polish pass on the most-used pages (see PROJECT_NOTES). Features are frozen from here: only fixes and polish.
17. ✅ 15 Nov: real street map, real shops around UiTM Kedah, installable on phones (see PROJECT_NOTES).
17a. ✅ 15 Nov (Fable): `php artisan demo:refresh` and the bigger random demo menu with 8 new drawings (see PROJECT_NOTES). 643 tests on SQLite; MySQL not run here.
18. ⚠️ Replace `docs/demo-food/real/bakery-bundle.jpeg`: it is a watermarked Vecteezy preview. Use the licensed download or another photo.
19. Next: the NPD report, written from `docs/PROJECT_NOTES.md`.

## Commit roles (14 Nov)

Fable builds and pushes finished stages to the `fable-work` branch with notes in its commits; the
owner's other Claude chat squashes them into `main` with one message in the owner's style. Details in
`CLAUDE.md` → "Commits: two roles". Why: the owner wants Fable's trial spent only on development and
the commit history written consistently in one voice.
