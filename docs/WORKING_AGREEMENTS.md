# FoodLoop · working agreements

How the owner and Claude work on FoodLoop. Read this at the start of every session, then check the
repo for changes before acting. Last updated: 14 Nov 2026.

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
| 13 Nov | Business rules: 15-min grace then auto-release; 3 no-shows = 7-day reservation limit; free cancel until 30 min before the window | Proposed by Claude; ⚠️ team still to confirm |
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
| 13 Nov | Fortify screens and password reset wait for Stage 4; two-factor and passkeys are not used | Keeps the prototype small; the reset email needs a screen first |

## Current status

| Item | Status |
|---|---|
| System workflow whiteboard | ✅ done |
| First-draft mockups (green style) | ✅ done, superseded by style C |
| Style directions A–D | ✅ done; C chosen |
| Style C: all screens + states | ✅ approved; food pictures added after owner's feedback |
| Build plan (`docs/BUILD_PLAN.md`) | ✅ approved by owner (13 Nov) |
| Laravel prototype | 🚧 Stage 0 (setup) ✅ done 13 Nov · Stage 1 (database, models, demo data) ✅ done 14 Nov · Stage 2 (reservation engine) next |
| Concept testing with students | ❌ not started |

## Owner still needs to

- Run the README "Run it locally" steps once on the Windows machine (about 10 min) and report any error.
- Confirm the business rules above with the team.
- Optional: send real photos of campus food to replace the drawn ones.
- Decide whether the repo should stay public (it is public now).
- Name a real food bank partner (placeholder for now).

## Pending questions

- Which campus/area is the pilot location?
- Does the lecturer accept interface screenshots as the "2D prototype"?

## Next steps

1. ✅ Owner approved the style C screens (asked for food pictures, now added).
2. ✅ Build plan written: `docs/BUILD_PLAN.md`. Stages 0–4 (marked **F**) are the foundation and go to
   the strongest model (Fable); stages 5–8 (**A**) follow those patterns and suit any model.
3. ✅ Stage 0 done: Laravel app set up, tests pass on SQLite and MySQL (see `docs/PROJECT_NOTES.md`).
4. ⏳ Owner runs the README "Run it locally" steps on Windows and reports what happens.
5. ✅ Stage 1 done: database, models, demo data (75 tests, SQLite + MySQL).
6. ⏳ Stage 2: reservation engine (the hardest part). Plan first, then build.
