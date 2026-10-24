# Prerequisites & Setup — Doctor Appointment Booking System (Windows)

Everything below is Windows-specific. This project is plain PHP (custom MVC) with Composer dependencies for email and Excel import.

## 1. Install XAMPP

1. Download XAMPP with **PHP 8.1 or newer** from https://www.apachefriends.org (default install path `C:\xampp`).
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Add `C:\xampp\php` and `C:\xampp\mysql\bin` to your system `Path`, then confirm `php -v` / `mysql --version` in a new terminal.

## 2. Install Composer

Download and run `Composer-Setup.exe` from https://getcomposer.org/download/, then confirm with `composer -V`.

## 3. Enable required PHP extensions

Needed for `phpmailer/phpmailer` and `phpoffice/phpspreadsheet`:

1. Edit `C:\xampp\php\php.ini`.
2. Uncomment:
   ```
   extension=gd
   extension=zip
   ```
3. Save and restart Apache from the XAMPP Control Panel.

## 4. Configure MySQL/MariaDB port

`config/database.php` is committed with `host=127.0.0.1, port=3306` — XAMPP's own default MariaDB port, so no change should be needed on a standard XAMPP install. Only edit `config/database.php` (or move MariaDB's port via `C:\xampp\mysql\bin\my.ini`) if your local MySQL runs elsewhere.

## 5. Install project dependencies

```
composer install
```

## 6. Database setup

```
mysql -u root -h 127.0.0.1 -P 3306 < database/schema.sql
```
(adjust `-P` to your port). This drops/recreates and seeds `doctor_appointment_booking_system`. Seed dates (leave days, clinic hours, upcoming appointments) are generated relative to the current date at import time, so re-import any time and the demo data still looks current.

## 7. Configure outgoing email (optional for demo)

Edit `config/mail.php` with SMTP credentials from a free test inbox such as https://mailtrap.io, so booking-confirmation/cancellation emails can be sent and inspected locally. This is optional for demoing the app itself — `Core\Mailer::send()` fails silently (logged, not thrown) if SMTP isn't configured.

## 8. Verify everything

```
php -v
composer -V
mysql -u root -h 127.0.0.1 -P 3306 -e "SELECT 1;"
```
Ready — see `HOW-TO-RUN.md` to start the app.
