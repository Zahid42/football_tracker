FOOTBALL TRACKER — STAGE 1
==============================

Requirements:
- Windows
- XAMPP with Apache, PHP 8+, MySQL/MariaDB

Install:
1. Copy this folder to C:\xampp\htdocs\football_tracker\
2. Start Apache and MySQL in XAMPP.
3. Open http://localhost/phpmyadmin/
4. Import database.sql
5. Open http://localhost/football_tracker/
6. Admin: http://localhost/football_tracker/admin/
7. Default login:
   Email: admin@football.local
   Password: password
8. Immediately change the password in Admin > Settings.

If MySQL has a root password, edit config/database.php.

Stage 1 includes:
- PDO database connection
- Session authentication
- CSRF protection
- Admin login/logout
- Admin dashboard
- Profile management
- Teams CRUD
- Competitions CRUD
- Seasons CRUD
- Venues CRUD
- Basic match CRUD
- Dynamic public profile
- Match history
- Basic statistics/charts
- Sample database data

This package uses CDN Bootstrap/Bootstrap Icons/Chart.js. Apache/PHP/MySQL remain local.
