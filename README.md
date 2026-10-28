Lethe
=====

PHP toolkit, version 0.10.1.

Required: PHP 7.2 or later. Core syntax and isolated bootstrap checks passed on PHP 7.2.24 and 8.5.12. Exact target runtimes PHP 7.4.33 and 8.3.36 still require verification.

## Changes in 0.10.1

- Restore caller MySQLi report mode after driver operations, including errors.
- Isolate persistent connections by full credentials and recover closed/lost handles without replaying failed statements.
- Fix result handling after SQL errors, release result sets and close non-persistent connections reliably.
- Handle unavailable MySQL extensions and invalid connection configuration without undefined-function errors.
- Add regression checks against a disposable MariaDB server; existing public API, utf8 charset and opt-in persistence remain unchanged.

## Changes in 0.10.0

- Updated bootstrap, configuration, session registry, autoloading and URL handling for newer PHP runtimes while retaining PHP 7.2 syntax compatibility.
- Improved random generators and added hexadecimal tokens and file-based request throttling helpers.
- Extended MySQL driver with explicit port handling and optional persistent connections.
- Extended image processing with Imagick support, GD fallback, diagnostics, watermarking and image profile handling. Imagick is preferred when available.
- Removed the unused, unfinished Charon queue class.

`mbstring` is required by the core. Image and database features require their corresponding PHP extensions. Persistent MySQL connections remain opt-in.

Application integration, image operations and actual database connections must be verified in the consuming projects; the isolated checks do not replace those tests.

## MySQL driver regression checks

The standalone checks are in `tests/lethe/`. The integration check requires an explicitly supplied `LETHE_MYSQL_TEST_PORT` for a disposable local MariaDB server with an empty root password; it refuses the normal port 3306. It creates and removes its own isolated databases and account. Never point it at an application database server.

Load mbstring and mysqli for `mysql-driver-integration.php`. Run `mysql-driver-no-extension.php` with mbstring but without a MySQL extension. `LETHE_PACKAGE_DIR` can select another checkout of the package.

The driver preserves its existing error-array, empty-result and charset contracts, restores the caller MySQLi report mode and does not automatically replay failed statements.
