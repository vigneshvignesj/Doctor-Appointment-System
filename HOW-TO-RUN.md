# How to Run — Doctor Appointment Booking System

Assumes prerequisites in `PREREQUISITES.md` are already done.

## One-time setup

```
composer install
mysql -u root -h 127.0.0.1 -P 3306 < database/schema.sql
```

This drops/recreates and reseeds the database, so re-run it any time — seed dates (leave days, clinic hours, upcoming appointments) are generated relative to today.

Optionally configure `config/mail.php` with SMTP credentials (e.g. Mailtrap) so booking confirmation/cancellation emails can be sent — not required to demo the app itself.

## Start MySQL

Open **XAMPP Control Panel** and make sure **MySQL** is running.

## Run the app

From this project's folder:
```
php -S localhost:8002 -t public public/index.php
```
(`-t public` is required for static assets to load.)

## Open it

Visit http://localhost:8002/login and sign in with any of the demo credentials in `README.md` under "Default Login Credentials".

## Stop the app

`Ctrl+C` in the terminal running the `php -S` command.
