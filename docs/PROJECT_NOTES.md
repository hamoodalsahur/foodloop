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
| Laravel prototype | ❌ Stage 0 next | — |

---

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
