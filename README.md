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
| Laravel + MySQL prototype | 🚧 Stage 0 (project setup) done · Stage 1 (database, demo data) next |

All shop names, prices and numbers in the mockups and in the app are **demo data**, not real offers or
research results. Payments are simulated.

## Run it locally

For Windows with [Laragon](https://laragon.org) (it brings PHP, MySQL, Composer and Node). First time:
about 10 minutes, mostly downloads.

**0. Check your tools (1 min).** Start Laragon, click **Start All**, then click **Terminal** and run:

```powershell
php -v        # needs 8.3 or newer
composer -V   # any 2.x
node -v       # needs 20.19 or newer (22 is fine)
mysql --version   # needs 8.x
```

If PHP is older than 8.3, switch it in Laragon: Menu → PHP → Version. If a command is "not recognized",
you are probably in plain PowerShell: use Laragon's **Terminal** button instead.

**1. Get the code (1 min).**

```powershell
cd C:\laragon\www
git clone https://github.com/hamoodalsahur/foodloop.git
cd foodloop
```

Already cloned? Run `git pull` inside the folder instead.

**2. Install and set up (5–8 min).**

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
mysql -u root -e "CREATE DATABASE IF NOT EXISTS foodloop; CREATE DATABASE IF NOT EXISTS foodloop_test;"
php artisan migrate
php artisan storage:link
npm install
npm run build
```

Success looks like: `composer install` ends without red errors, `migrate` lists three tables as `DONE`,
and `npm run build` ends with `✓ built`. If your MySQL root user has a password, add `-p` to the `mysql`
command and put the password in `DB_PASSWORD` in `.env`.

**3. Start it.**

```powershell
php artisan serve
```

Open <http://localhost:8000>. You should see the cream FoodLoop page with a marigold "Good food shouldn't
go to waste." card. Stop the server with `Ctrl + C`.

While changing the design, also run `npm run dev` in a second terminal so style changes show immediately.

**Using Herd instead?** The same commands work, but free Herd has no MySQL, so you need Herd Pro's MySQL
service or another MySQL 8 server. Put its user and password in `.env`.

## Run the tests

```powershell
php artisan test                        # quick: SQLite in memory, about 5 seconds
php artisan test -c phpunit.mysql.xml   # same tests on MySQL (uses the foodloop_test database)
```

Success looks like a list of green ticks ending in `Tests: 10 passed`. Run both before every commit.

## Repo layout

| Path | What's in it |
|---|---|
| `app/`, `routes/`, `resources/`, `database/`, `tests/` | The Laravel app |
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
