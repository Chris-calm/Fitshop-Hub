# Fitshop Hub (Local PHP + Tailwind)

A school project Health & Fitness app with a shop, fitness dashboard, personalized plan, and basic orders/tracking.

## Stack
- PHP 8+, Tailwind via CDN
- MySQL/MariaDB
- Session-based cart, PDO for DB

## Setup (XAMPP)
1. Copy the folder to `c:/xampp/htdocs/Health&Fitness`.
2. Start Apache + MySQL.
3. Create DB `fitshop_hub` in phpMyAdmin.
4. Import SQL:
   - `sql/schema.sql`
   - `sql/steps.sql`
5. Configure DB: edit `config/config.php` (host, user, pass).
6. Visit `http://localhost/Health&Fitness/index.php`.

## Features
- Shopping: categories, product detail, cart, checkout (GCash/Maya stub)
- Orders in MySQL + shipment tracking
- Auth: register/login/logout, profile, avatar upload
- Personalized plan on register (goal/activity/equipment/diet)
- Fitness modules: Guides, Choreography, Gym (personalized)
- Fitness dashboard: steps ring + manual steps input (stored in DB)

## Mobile (AWebServer)
- Copy project to AWebServer www root
- Import SQL and edit `config/config.php`
- Ensure `pdo_mysql` is enabled

## GitHub
1. Initialize repo:
```
cd c:/xampp/htdocs/Health&Fitness
git init
```
2. Copy example config and edit secrets locally:
```
copy config\config.example.php config\config.php
```
3. Commit (config.php is gitignored):
```
git add .
git commit -m "Initial Fitshop Hub"
```
4. Create a GitHub repo (web UI), then add remote and push:
```
git remote add origin https://github.com/<your-username>/fitshop-hub.git
git branch -M main
git push -u origin main
```

## Notes
- Do not commit credentials or `config/config.php`.
- Uploads saved to `/uploads/avatars` are ignored by git.
- For production or future work, consider moving to Laravel/Next.js.
