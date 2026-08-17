# Oson Sertifikat

PHP + MySQL + JavaScript/AJAX preparation platform for Physics Milliy Sertifikat.

## Stack

- PHP 8+
- MySQL 8+
- Procedural MySQLi
- HTML/CSS
- Vanilla JavaScript / Fetch API
- JSON configuration

## Database

Import `database/schema.sql` into MySQL/phpMyAdmin.

Database connection is configured in:

`includes/db.php`

The project intentionally uses simple procedural MySQLi rather than PDO or an ORM.

## Configuration

Editable application configuration is in `config/*.json`.

Questions, users, attempts, sessions, progress and other relational data belong in MySQL.
