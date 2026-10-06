# Library Management System

A PHP and MySQL/MariaDB web application for managing library books, physical copies, members, lending, returns, reservations, fines, payments, notifications, reports, and audit activity.

## Features

- Secure login, logout, registration, password change, and password-reset workflow
- Role-based access for administrators, librarians, and members
- Book catalog with authors, publishers, categories, ISBNs, cover images, and copy tracking
- Member registration, profiles, status management, and profile images
- Lending, renewals, returns, overdue tracking, and fine calculation
- Book reservations with ready, completed, cancelled, and expired states
- Fine waivers, payments, receipts, and payment history
- In-app notifications and unread notification counts
- Dashboard statistics, recent activity, audit logs, and CSV reports
- Configurable library name, contact details, loan rules, fine rate, currency, date format, and logo
- Responsive layouts for desktop, tablet, and mobile screens

## Technology

- PHP 8.2 or newer
- MySQL 5.7+ or MariaDB 10.4+
- PDO with prepared statements
- HTML, CSS, and vanilla JavaScript
- Font Awesome icons and Google Fonts loaded from CDNs
- XAMPP, WAMP, LAMP, or another PHP web server stack

## Requirements

Before installing, make sure the following are available:

- Apache with PHP enabled
- MySQL or MariaDB
- PHP extensions including `pdo_mysql`, `fileinfo`, and `mbstring`
- PHP CLI if the overdue-processing script will be scheduled

## Installation with XAMPP

1. Copy the project into the Apache document root. For XAMPP on Windows:

	```text
	C:\xampp\htdocs\librarymanagementsystem
	```

2. Start **Apache** and **MySQL** from the XAMPP Control Panel.

3. Create the database. Open phpMyAdmin at `http://localhost/phpmyadmin`, create a database named `library_management_system`, and import [`database.sql`](database.sql).

	The SQL dump also identifies the tested environment as PHP 8.2 and MariaDB 10.4.

4. Review the database settings in [`config/database.php`](config/database.php). The default local configuration is:

	```text
	Host: localhost
	Database: library_management_system
	User: root
	Password: empty
	```

5. Confirm the application URL in [`config/config.php`](config/config.php):

	```php
	define('APP_URL', 'http://localhost/librarymanagementsystem');
	```

	Change this value if the project folder or host name is different.

6. Ensure these directories exist and are writable by PHP:

	```text
	uploads/
	uploads/books/
	uploads/members/
	```

7. Open the application at [http://localhost/librarymanagementsystem](http://localhost/librarymanagementsystem).

## First Use

1. Sign in with an existing account from the imported database, or register a new member account.
2. An administrator can create and manage librarian and member accounts from **Users**.
3. Configure the library name, contact information, lending rules, fine rate, currency, and date format from **Settings**.
4. Upload the system logo from **Settings**. The logo is saved in `uploads/` and appears on the login and registration pages.
5. Add authors, publishers, categories, books, and physical book copies before issuing lend.

Do not use blank database passwords or development credentials in a production deployment. The SQL dump may contain development data, so review or remove it before deploying.

## User Roles

### Administrator

Administrators have access to system settings, users, members, books, copies, lending, returns, reservations, fines, payments, notifications, reports, and audit logs.

### Librarian

Librarians manage the catalog, members, lending, returns, reservations, fines, payments, notifications, and operational reports. Administrative settings and user administration are restricted.

### Member

Members can browse the catalog, view book details, borrow history, current lend, fines, reservations, notifications, and their profile.

## Main URLs

| Area | URL |
| --- | --- |
| Login | `login.php` |
| Registration | `register.php` |
| Password reset | `forgot-password.php` |
| Admin dashboard | `admin/dashboard.php` |
| Librarian dashboard | `librarian/dashboard.php` |
| Member dashboard | `member/dashboard.php` |
| Settings | `admin/settings/index.php` |
| Catalog | `member/catalog.php` |

## API Endpoints

The `api/` directory contains endpoints used by dashboards and interactive pages:

- `api/books.php` - book searches and catalog data
- `api/dashboard-stats.php` - dashboard statistics
- `api/lend.php` - loan-related data
- `api/members.php` - member-related data
- `api/notifications.php` - notification data and updates
- `api/recent-activity.php` - recent activity data
- `api/returns.php` - return-related data
- `api/search.php` - global search

Use the application pages and their existing request parameters when calling these endpoints. Access checks should remain enabled; do not expose them directly without authentication and authorization.

## Scheduled Overdue Processing

Run [`cron/process-overdue.php`](cron/process-overdue.php) periodically to process overdue lend and related fines or notifications:

```text
php cron/process-overdue.php
```

On Windows, schedule the command with Task Scheduler and use the PHP executable bundled with XAMPP, normally:

```text
C:\xampp\php\php.exe C:\xampp\htdocs\librarymanagementsystem\cron\process-overdue.php
```

Run it at least once per day.

## Configuration and Uploads

Application and security defaults are defined in [`config/config.php`](config/config.php) and [`config/constants.php`](config/constants.php), including:

- Application URL and name
- Session timeout and CSRF token lifetime
- Pagination size
- Maximum upload size, currently 5 MB
- Allowed image types: JPEG, PNG, GIF, and WebP
- Default loan, renewal, reservation, and fine values

Book covers are stored in `uploads/books/`, member profile images in `uploads/members/`, and the system logo in `uploads/`.

## Security Notes

The application includes session-based authentication, role checks, CSRF protection for forms, prepared SQL statements, password hashing, input validation, and upload type/size checks. Before production use:

- Set `display_errors` to `0` and configure server-side error logging.
- Use a strong database account with only the required permissions.
- Set a non-empty database password and update `config/database.php`.
- Serve the application over HTTPS.
- Protect `config/`, `uploads/`, and other non-public files with web-server rules.
- Review the default data and rotate all development credentials.
- Restrict access to scheduled scripts and administrative pages.

## Testing

The project includes a manual test checklist covering authentication, authorization, catalog management, members, lending, returns, reservations, fines, notifications, reports, security, responsiveness, and performance.

See [`TESTING.md`](TESTING.md) for the full checklist and issue-report template.

For a PHP syntax check, run:

```text
C:\xampp\php\php.exe -l login.php
```

Replace `login.php` with the file being checked.

## Project Structure

```text
admin/       Administration pages and management workflows
api/         JSON/data endpoints used by the application
assets/      CSS, JavaScript, and image assets
config/      Database configuration and application constants
cron/        Scheduled maintenance scripts
includes/    Authentication, CSRF, layout, navigation, and shared functions
librarian/   Librarian dashboard
member/      Member dashboard and self-service pages
uploads/     Uploaded logo, book cover, and member image files
```

## License

No license file is currently included. Add the appropriate license before distributing the system.
