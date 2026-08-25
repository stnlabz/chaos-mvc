# Installation

## Overview

Chaos MVC includes a web-based installer for initial system setup.

After the Chaos MVC files have been deployed to the web server, installation begins at:

```text
/install
```

The installer configures the database connection, installs the required database schema, creates the initial administrator account, writes the Chaos MVC database configuration, and locks the installer after completion.

## Requirements

Before starting the installer, make sure the hosting environment provides:

- PHP supported by the current Chaos MVC release
- MySQL or MariaDB
- A database created for Chaos MVC
- A database user with permission to create and modify tables in that database
- Write access to `/app/core/config.php`
- Write access to the configured `LOG_PATH`
- A web server configured to serve the Chaos MVC `/public` directory

The database itself must exist before running the installer.

The installer creates the Chaos MVC tables inside the supplied database.

## Upload Chaos MVC

Upload or extract the Chaos MVC release to the target hosting location.

A normal installation contains the framework directories and the public web root, including:

```text
/app
/public
```

The web server document root should point to:

```text
/public
```

## Start the Installer

Open the Chaos MVC installer in a browser:

```text
https://your-domain.example/install
```

The installer presents two configuration sections:

1. Database
2. Administrator

## Database Configuration

Provide the database connection information for the Chaos MVC installation.

Required values are:

- Database Host
- Database User
- Database Name

The database password may be left empty only when the database account itself does not require one.

A common database host is:

```text
localhost
```

The installer tests the supplied database connection before continuing.

If the connection cannot be established, installation stops and the installer reports the failure.

## Administrator Account

The installer creates the first Chaos MVC administrator account.

Provide:

- Username
- Display Name
- Email Address
- Password

The initial administrator is created with:

```text
role = admin
user_level = 9
```

The password is stored using PHP password hashing.

## Installation Process

After submitting the installer form, Chaos MVC performs the installation in the following order:

```text
/install
   |
   v
Validate installer input
   |
   v
Test database connection
   |
   v
Load /app/install/schema.sql
   |
   v
Create Chaos MVC database tables
   |
   v
Create initial administrator account
   |
   v
Write /app/core/config.php
   |
   v
Write install.lock
   |
   v
Redirect to /login
```

If a required step fails, the installer stops and reports the applicable error.

## Database Schema

The installer loads:

```text
/app/install/schema.sql
```

The installation schema creates the base Chaos MVC database tables required by the release.

The current base schema includes:

- `accounts`
- `media`
- `modules`
- `posts`
- `comments`

The `accounts` table is used by the authentication and account-management systems.

The installer creates the initial administrator directly in the `accounts` table.

## Generated Database Configuration

After the schema and administrator account are created successfully, the installer writes the database connection settings to:

```text
/app/core/config.php
```

The generated configuration defines:

```php
DB_HOST
DB_USER
DB_PASS
DB_NAME
```

This file contains installation-specific database configuration and should not be replaced with configuration from another Chaos MVC installation.

## Installer Lock

After successful installation, Chaos MVC writes:

```text
LOG_PATH/install.lock
```

The installer checks for this lock before starting.

When the lock exists, `/install` redirects to:

```text
/login
```

This prevents the completed installation process from being run again through the normal installer route.

## Login

After installation completes successfully, the browser is redirected to:

```text
/login
```

Log in using the administrator username and password created during installation.

## Installation Failure

If installation does not complete, review the error displayed by the installer.

Common causes include:

- Incorrect database host
- Incorrect database username or password
- Database does not exist
- Database user lacks required permissions
- `/app/install/schema.sql` cannot be read
- Database schema creation fails
- Administrator account creation fails
- `/app/core/config.php` is not writable
- `LOG_PATH` is not writable and `install.lock` cannot be created

Correct the reported condition and run `/install` again.

If installation stopped after some database tables were already created, inspect the database before retrying. A partially installed schema may need to be removed before a clean installation can be performed.

## Existing Installations

Do not run the installer over an existing working Chaos MVC installation.

The installer is intended for initial installation.

Framework updates are separate from installation and should use the applicable Chaos MVC update process for the installed release.

## After Installation

After logging in, verify the installation before adding optional functionality.

Recommended checks include:

- Admin login works
- Admin dashboard loads
- Accounts management loads
- Posts load
- Media loads
- Modules load
- Public routes resolve correctly
- Database-backed content can be read

Optional modules may then be installed according to their own documentation.

## Notes

Chaos MVC uses a protected Core with capability that grows outward through modules.

The installer establishes the base framework only. Optional modules and application-specific functionality are installed separately.

For current release information, documentation, and updates, use the official Chaos MVC project resources.
