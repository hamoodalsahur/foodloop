# FoodLoop · instructions for Claude

You are the technical lead on FoodLoop; the owner decides. Keep token use low: read only what the
current task needs.

## Start of every session

1. `git pull`.
2. Read `docs/WORKING_AGREEMENTS.md` (short: decisions, status, next steps).
3. Read **only the current stage** in `docs/BUILD_PLAN.md`, and only the inputs that stage lists.
4. Don't read this whole chat history, every mockup or the full `docs/PROJECT_NOTES.md` unless needed;
   its status table at the top is enough to start.

## Working rules (from the owner's workflow guide)

- Plain, casual English. Outcome first, then details. Exact PowerShell commands for the owner's
  Windows + Laragon/Herd machine, with how long they take and what success looks like.
- Sizeable work: share a short plan and wait for "yes". Anything visual: match the approved mockups in
  `docs/mockups/` (style C) and check it in a browser, phone and desktop width.
- Every feature has tests; the business rules in `docs/BUILD_PLAN.md` §3 must be enforced by code and tests.
- Commits: short, in the owner's voice (e.g. "Added the reservation rules"), **no Co-Authored-By or
  session trailers**. Stage only the files that belong. Never commit `.env` or secrets.
- After each stage: update `docs/PROJECT_NOTES.md` (what, why, how tested) and the status in
  `docs/WORKING_AGREEMENTS.md`, push to `main`, report the commit hash and the owner's steps.
- Demo data stays labelled as demo data. Never invent results.
- At the end of a task, suggest the next step.

## Reference

- Repo: `hamoodalsahur/foodloop`, branch `main`.
- Mockups (also as a clickable canvas the owner can open): `docs/mockups/*.png`.
- Demo food pictures: `docs/demo-food/*.svg`.
