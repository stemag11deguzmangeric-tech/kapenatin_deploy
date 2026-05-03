====================================================
  KAPE NATIN - DEPLOYMENT INSTRUCTIONS
====================================================

BEFORE UPLOADING TO INFINITYFREE:
-----------------------------------
1. Open config.php and replace the placeholder values:
   - $host     → your InfinityFree DB host (e.g. sql123.infinityfree.com)
   - $user     → your DB username (e.g. sql123456_kapenatin)
   - $password → your DB password
   - $db_name  → your DB name (e.g. sql123456_kapenatin)

   You get all these from:
   InfinityFree Panel > MySQL Databases


HOW TO DEPLOY ON INFINITYFREE (FREE):
---------------------------------------
1. Go to infinityfree.com > sign up > create a hosting account
2. In the panel, go to MySQL Databases > create a new database
3. Click phpMyAdmin > select your DB > Import tab > upload database.sql
4. Update config.php with your live DB credentials (step above)
5. Go to Online File Manager > open htdocs/ folder
6. Upload ALL files from this zip (including bg.png and Quiapo_Free.ttf)
7. Visit your subdomain — the site should be working!


DEFAULT LOGIN ACCOUNTS (from seed data):
-----------------------------------------
Admin:    username=admin    password=password
Cashier:  username=cashier  password=password
Barista:  username=barista  password=password
Customer: username=maria    password=password


FILES IN THIS PACKAGE:
------------------------
index.php           - Homepage
login.php           - Login page
logout.php          - Logout handler
signup.php          - Registration page
menu.php            - Menu + add to cart
cart.php            - Shopping cart
checkout.php        - Checkout + order placement
orders.php          - Customer order history
staff_dashboard.php - Staff/admin panel
config.php          - DB connection (EDIT THIS FIRST!)
database.sql        - Import this into phpMyAdmin
bg.png              - Background image (upload this too)
Quiapo_Free.ttf     - Custom font (upload this too)


NOTE: This build is for TESTING ONLY.
For production, apply proper password hashing and CSRF protection.
====================================================
