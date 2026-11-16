# FoodLoop · quick start

The short version of everything you need to close FoodLoop, start it again, and log in. The full details
are in the README. All commands are for Laragon's terminal on the Windows laptop, in the project folder:

```
cd C:\laragon\www\foodloop
```

---

## 1. Closing everything

Nothing needs saving: accounts, orders and deals live in the MySQL database and stay there after you
close everything.

1. In each terminal that is running something (`php artisan serve`, `php artisan schedule:work`,
   `cloudflared …`), press **Ctrl + C**, then close the terminal.
2. In Laragon, click **Stop All**, then close Laragon.
3. On a phone, the installed FoodLoop app stops working once the tunnel is closed. That's expected; the
   link changes next time anyway.

Before you walk away, check two things:

- `git status`. If it lists files (for example new photos in `docs/demo-food/real/`), they are only on this
  laptop. Ask Claude to commit them, or copy them somewhere safe.
- **Don't** run `php artisan migrate:fresh --seed` unless you want to wipe everything. It deletes every
  account you made yourself and all order history, and puts the demo data back.

---

## 2. Starting it again (every time, about 2 minutes)

1. Open Laragon and click **Start All** (starts MySQL).
2. Open **Terminal** in Laragon and run:

   ```
   cd C:\laragon\www\foodloop
   php artisan demo:refresh
   php artisan serve
   ```

   - `demo:refresh` posts a fresh set of deals closing soon. Accounts and order history stay. It ends with
     `Demo deals refreshed: … fresh listings posted`.
   - `serve` ends with `Server running on [http://127.0.0.1:8000]`. Leave this terminal open.
3. Open <http://localhost:8000> in Chrome.
4. Optional, a second terminal: `php artisan schedule:work`. It expires old reservations and marks
   no-shows in the background. The demo works without it.

Deals close an hour or two after `demo:refresh` (that's the business rule: reservations close when
pickup starts). If the deals page is empty, just run `php artisan demo:refresh` again.

### On a phone (optional, about 1 minute)

With `php artisan serve` running, open another terminal:

```
cloudflared tunnel --url http://localhost:8000
```

Copy the `https://….trycloudflare.com` link from the box near the top and open it on the phone. To install:
**Android:** Chrome menu ⋮ → Add to Home screen. **iPhone:** Safari Share → Add to Home Screen. The link is
new every time, so re-add it to the home screen each session.

---

## 3. After Claude (or Fable) changed the code

Run these once before starting it as above (about 2 minutes):

```
git pull
composer install
npm install
php artisan migrate
npm run build
php artisan test -c phpunit.mysql.xml
```

- `migrate` only adds what's new; it never wipes data. "Nothing to migrate" is fine too.
- The tests should end with a line like `Tests: 707 passed` (the number grows as features are added).
- If `git pull` complains that the history changed, run `git fetch origin` then
  `git reset --hard origin/main` (this throws away changes that only exist on the laptop).

---

## 4. Before the pitch

1. Start everything as in section 2, but use `php artisan migrate:fresh --seed` **instead of**
   `demo:refresh`, no more than 30 minutes before presenting. The numbers then match
   `docs/DEMO_SCRIPT.md` exactly (it also removes test accounts and orders you made).
2. Open the demo script and follow it. Code **2468** (Aina's sandwich pack at Se.Bunga) can be collected
   straight away; code **4721** (Bakery Surprise Bundle) waits for its pickup window, or use
   Admin → Orders → Mark collected (admin override).

---

## 5. Accounts

Every demo account uses the password **`password`**. The people are made up; the approved shops are real
places near UiTM Kedah that have **not** joined FoodLoop (their deals are demo data).

### Admin

| Email | Who |
|---|---|
| `admin@foodloop.test` | FoodLoop Admin: approve shops, reports, customers, food banks, **Orders** (audit + admin override) |

### Students (customers)

| Email | Name | Good for showing |
|---|---|---|
| `aina@foodloop.test` | Aina Rahman | The main demo student: waiting orders 4721 and 2468, order history, impact page |
| `hafiz@foodloop.test` | Hafiz Mansor | Another waiting order at Bonda Mawar (code 3390) |
| `priya@foodloop.test` | Priya Selvam | A normal student with some history |
| `meiling@foodloop.test` | Mei Ling Tan | Has collected orders and filed a report |
| `daniel@foodloop.test` | Daniel Khoo | Blocked from reserving for 5 days (3 no-shows) |
| `farah@foodloop.test` | Farah Nordin | Blocked for 2 days |
| `jason@foodloop.test` | Jason Lim | One no-show away from a block |

### Shops (sellers), approved

| Email | Shop | Where |
|---|---|---|
| `sebunga@foodloop.test` | Se.Bunga Kafe UiTM | On campus, 0.3 km. Collect code 2468 here in the demo |
| `bondamawar@foodloop.test` | Bonda Mawar Cake House | Sungai Layar, 4.5 km. Busiest dashboard, shown as halal certified (demo) |
| `merbokroasted@foodloop.test` | Merbok Roasted | In front of the main gate, 0.1 km |
| `kopisaigon@foodloop.test` | Kopi Saigon Merbok | Taman Lembah Bujang, 1.5 km |
| `kuihtupah@foodloop.test` | Kuih Tradisional Tupah | Kampung Tupah Lama, 2.7 km |

### Shops waiting for admin approval (made up)

| Email | Shop |
|---|---|
| `kedaikopisinar@foodloop.test` | Kedai Kopi Sinar |
| `dapurbonda@foodloop.test` | Dapur Bonda |
| `sweetcrumbs@foodloop.test` | Sweet Crumbs Bakehouse |

### Accounts you made yourself

They are in the database on the laptop until the next `migrate:fresh --seed`. To list every account in
the database (with MySQL running):

```
php artisan tinker --execute="foreach (App\Models\User::orderBy('role')->orderBy('email')->get() as $u) echo $u->role->value.'  '.$u->email.'  '.$u->name.PHP_EOL;"
```

Passwords aren't shown (they are stored scrambled). Forgot one? Use **Forgot your password?** on the log
in page: on the laptop it shows an "Open the reset link" button.

---

## 6. If something goes wrong

| What you see | Fix |
|---|---|
| `SQLSTATE[HY000] [2002]` or `ERROR 2003` | MySQL isn't running: Laragon → **Start All** |
| The deals page is empty | The deals expired: `php artisan demo:refresh` |
| A page without colours or layout | `npm run build`, then reload. Don't use `npm run dev` with the phone tunnel |
| The phone says the link doesn't work | The tunnel stopped or restarted: start it again and use the new link |
| `Address already in use` on serve | It's already running in another terminal; use that one, or `php artisan serve --port=8001` |
