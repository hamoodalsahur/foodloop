# foodloop
FoodLoop is a proposed software-based marketplace designed to connect food businesses with customers interested in unsold surplus food at discounted prices. 

Students and nearby customers reserve discounted surplus food from campus sellers (cafés, bakeries,
food stalls) and collect it themselves during a pickup window. Leftovers can be offered to a food bank.
Built for the ENT600 Technology Entrepreneurship New Product Development (NPD) assignment.

![Customer screens](docs/mockups/c00-customer-screens-overview.png)

## Status

| Part | Status |
|---|---|
| System workflow and business rules | ✅ designed |
| UI mockups (style C: forest, marigold, cream) | ✅ approved |
| Laravel + MySQL prototype | 🚧 Stages 0–4 done: a student can sign up, browse deals, reserve and get a pickup code · Stage 5 (seller flow) next |

All shop names, prices and numbers in the mockups and in the app are **demo data**, not real offers or
research results. Payments are simulated.

## Run it locally

For Windows with [Laragon](https://laragon.org) (it brings PHP, MySQL, Composer and Node). First time:
about 10 minutes, mostly downloads.

**0. Check your tools (1 min).** Start Laragon, click **Start All**, then click **Terminal** and run:

```
php -v
composer -V
node -v
mysql --version
```

You need PHP 8.3 or newer, Composer 2.x, Node 20.19 or newer (22 is fine) and MySQL 8.x. If PHP is older
than 8.3, switch it in Laragon: Menu → PHP → Version. If a command is "not recognized", you are probably in
plain PowerShell: use Laragon's **Terminal** button instead.

Every command in this README works in Laragon's Terminal (which is a Command Prompt) and in PowerShell.

**1. Get the code (1 min).**

```
cd C:\laragon\www
git clone https://github.com/hamoodalsahur/foodloop.git
cd foodloop
```

Already cloned? Run `git pull` inside the folder instead.

**2. Install and set up (5–8 min).**

```
composer install
copy .env.example .env
php artisan key:generate
mysql -u root -e "CREATE DATABASE IF NOT EXISTS foodloop; CREATE DATABASE IF NOT EXISTS foodloop_test;"
php artisan migrate:fresh --seed
php artisan storage:link
npm install
npm run build
```

Success looks like: `composer install` ends without red errors, `migrate:fresh --seed` ends with
`DemoSeeder ... DONE`, and `npm run build` ends with `✓ built`. If your MySQL root user has a password, add `-p` to the `mysql`
command and put the password in `DB_PASSWORD` in `.env`.

`ERROR 2003 ... Can't connect to MySQL server on 'localhost:3306' (10061)` means MySQL isn't running: click
**Start All** in Laragon's window and wait until MySQL shows as started, then run the command again.

**3. Start it.**

```
php artisan serve
```

Open <http://localhost:8000>. You should see the cream FoodLoop page with a marigold "Good food shouldn't
go to waste." card. Stop the server with `Ctrl + C`.

While changing the design, also run `npm run dev` in a second terminal so style changes show immediately.

**Keep the clock running.** Two jobs run every minute: one turns reservations that weren't collected into
no-shows, the other closes listings whose pickup window is over and offers leftovers to the food bank.
They only run while this command is open in its own terminal (leave it running during a demo):

```
php artisan schedule:work
```

Success looks like a new pair of lines every minute naming `reservations:expire` and `listings:close`.

**Using Herd instead?** The same commands work, but free Herd has no MySQL, so you need Herd Pro's MySQL
service or another MySQL 8 server. Put its user and password in `.env`.

## Demo data and demo accounts

`php artisan migrate:fresh --seed` wipes the database and fills it with the **demo data** from the mockups
(about 5 seconds). Run it again any time to reset, for example right before a demo: pickup times are set
from the moment you run it, so there are always deals closing soon.

Every account signs in with the password **`password`**. These are made-up people and shops for the demo
only; never use this data or this password on a real server.

| Role | Email | Who |
|---|---|---|
| Admin | `admin@foodloop.test` | FoodLoop Admin |
| Seller | `rotilane@foodloop.test` | Roti Lane Bakery (halal certified) |
| Seller | `kafeseriilmu@foodloop.test` | Kafe Seri Ilmu |
| Seller | `dailygrind@foodloop.test` | Daily Grind Café |
| Seller | `maklong@foodloop.test` | Mak Long’s Kuih |
| Seller, waiting for approval | `kedaikopisinar@foodloop.test` · `dapurbonda@foodloop.test` · `sweetcrumbs@foodloop.test` | Kedai Kopi Sinar · Dapur Bonda · Sweet Crumbs Bakehouse |
| Customer | `aina@foodloop.test` | Aina Rahman: has a reservation tonight (code 4721) and 14 past pickups |
| Customer | `hafiz@foodloop.test` · `priya@foodloop.test` · `meiling@foodloop.test` | Hafiz, Priya, Mei Ling: reservations today |
| Customer, blocked | `daniel@foodloop.test` · `farah@foodloop.test` | Blocked after 3 no-shows, for 5 and 2 more days |
| Customer, 2 strikes | `jason@foodloop.test` | One more no-show and he is blocked |

Log in at <http://localhost:8000/login>. Customers land on the deals, sellers on `/seller`, the admin on
`/admin` (the seller and admin areas are still placeholders).

## Try the student flow

With `php artisan serve` running and the demo data seeded (about 2 minutes):

1. Open <http://localhost:8000>. You see tonight's deals, closing soonest first. Try the tabs
   (Nearest, Cheapest), the **Halal only** and **Distance** tiles and the food chips.
2. Tap **Bakery Surprise Bundle**. You see the −60% band, the live countdown and the facts box.
3. Tap **Log in to reserve**, then **Create an account** and sign up with any name and email
   (or log in as `aina@foodloop.test` / `password`). You come straight back to the bundle.
4. Pick how many, choose **Pay now** or **Pay at pickup**, tap **Reserve for RM…**.
5. You land on **You're booked.** with your 4-digit pickup code, the countdown to the pickup window and
   the free-cancellation time. **Orders** in the menu lists all your reservations.
6. Tap **Cancel**, then **Yes, cancel**: it's free at this point and the food goes back on sale.

To see the other states: reserve everything that's left of a deal and open it again (**Sold out**), open
<http://localhost:8000/?category=groceries> (**All gone nearby**), or log in as `daniel@foodloop.test`
(blocked: **You can't reserve until…**). Map and Impact in the menu are greyed out until Stage 6.

## See the design system

While `php artisan serve` is running, these pages show the building blocks every screen is made of, with
sample data. They only exist on your own machine (`APP_ENV=local`):

| Address | What it shows |
|---|---|
| <http://localhost:8000/dev/components> | Every component: buttons, cards, price box, deal rows, status pills, empty and error states… |
| <http://localhost:8000/dev/layouts/customer> | The phone-first customer layout with the bottom navigation |
| <http://localhost:8000/dev/layouts/dashboard> | The seller/admin layout with the forest sidebar |

Success looks like the pages in `docs/screenshots/stage3-*.png`. If the food pictures are grey plates, run
`php artisan migrate:fresh --seed` and `php artisan storage:link` first. The rules for building screens are
in `docs/UI_GUIDE.md`.

## Run the tests

Quick run on SQLite in memory (about 15 seconds):

```
php artisan test
```

The same tests on MySQL, plus the race test where 8 requests go for the last item at the same moment
(about 45 seconds; uses the `foodloop_test` database, MySQL must be running):

```
php artisan test -c phpunit.mysql.xml
```

Success looks like a list of green ticks ending in `Tests: 2 skipped, 366 passed` for the quick run and
`Tests: 368 passed` on MySQL (the 2 skipped ones are the race test, which needs MySQL). Run both before
every commit.

## Repo layout

| Path | What's in it |
|---|---|
| `app/`, `routes/`, `resources/`, `database/`, `tests/` | The Laravel app |
| `app/Models/`, `app/Enums/` | The data: users, sellers, listings, reservations, reports, donations, and their allowed values |
| `app/Services/ReservationService.php` | The reservation engine: reserve, cancel, collect by code, no-show, strikes. The only code that changes stock or a reservation's status |
| `app/Policies/`, `app/Http/Middleware/EnsureRole.php`, `routes/web.php` | Who may open which area (customer, seller, admin) and who may touch which listing or reservation |
| `app/Http/Controllers/`, `resources/views/deals`, `listings`, `orders`, `auth` | The student pages: deals, one listing, reserve, orders and pickup code, log in and register |
| `resources/views/components/` | The design system: layouts and reusable components. Guide: `docs/UI_GUIDE.md` |
| `app/Services/ListingCloser.php`, `routes/console.php` | Closing finished listings, the food-bank offer, and the every-minute schedule |
| `database/seeders/DemoSeeder.php` | The demo shops, people, listings and order history |
| `config/foodloop.php` | The business rules as numbers (grace minutes, strikes, max quantity…). Change a rule here, nowhere else |
| `resources/css/app.css` | The style C colours and fonts as Tailwind tokens (`bg-forest`, `text-marigold`, `bg-cream`, `font-display`) |
| `docs/BUILD_PLAN.md` | The build plan: stack, business rules, data model, stages |
| `docs/PROJECT_NOTES.md` | What changed, why, and how it was tested |
| `docs/WORKING_AGREEMENTS.md` | Decisions, current status and next steps |
| `docs/mockups/` | PNG screenshots of every approved screen |
| `docs/screenshots/` | Screenshots of the real app, taken when each stage was checked |
| `docs/demo-food/` | Drawn food pictures used as stand-ins for seller photos |

## Stack

Laravel 13 (PHP 8.3+) and MySQL 8 · Blade, Tailwind CSS 4 and Alpine.js built with Vite · Laravel Fortify
for sign-in · Pest for tests · time zone Asia/Kuala_Lumpur.
