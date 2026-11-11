Lethe
=====

PHP toolkit, version 0.10.3.

Required: PHP 7.2 or later. Version 0.10.2 passed existing driver, native prepared-query, registry and missing-extension checks on PHP 7.2.24, 7.4.33, 8.3.32 and 8.5.12. Database checks used a disposable loopback MariaDB 12.0 instance; no application database was used.

## Changes in 0.10.3

- Handle a file disappearing between the existence check and unlink in Storage::deleteFile(). Only the confirmed missing-file warning is suppressed; other filesystem errors and exceptions remain visible to the caller's handler.
- Always restore the previous error handler, including when unlink throws. The existing boolean return contract and Cache behavior are preserved.
- Standalone regression: `php tests/lethe/storage-delete-regression.php`, with optional `LETHE_PACKAGE_DIR` selecting another toolkit checkout. Tests use temporary files only, without a database or network.

## Changes in 0.10.2

- Keep valid UTF-8 intact in Format::urlSafe(), avoiding ambiguous encoding guesses for upload filenames on PHP 8.5.

- Preserve `Reg::query(null)` returning false without PHP 8.1+ deprecations.
- Add `Db::preparedResult()` / `Db::preparedQuery()` and matching MySQL driver methods.
- Bind scalar/null parameters using mysqli; prepared reads work without mysqlnd.
- Prepared writes return affected row counts. Existing `query()`, `result()`, escaping, charset and opt-in persistence contracts remain unchanged.
- New prepared operations require mysqli; other database drivers keep their existing API and report unsupported prepared operations explicitly.
- Upgrading the toolkit alone does not migrate application queries or password/session storage. Warp Auth requires its separate SQL migration and PHP 7.4+.

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

The additional `mysql-prepared-integration.php` check requires `LETHE_TEST_DISPOSABLE=1` as well as the explicit local test port. It covers scalar/null values, Unicode, SQL payloads, affected rows, bound LIMIT, FULLTEXT/LIKE/REGEXP, legacy calls after errors and both connection persistence modes. Test FULLTEXT indexes are created in the disposable database; existing application indexes still need deployment verification.
