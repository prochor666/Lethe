Lethe
=====

PHP toolkit, version 0.10.0.

Required: PHP 7.2 or later. Core syntax and isolated bootstrap checks passed on PHP 7.2.24 and 8.5.12. Exact target runtimes PHP 7.4.33 and 8.3.36 still require verification.

## Changes in 0.10.0

- Updated bootstrap, configuration, session registry, autoloading and URL handling for newer PHP runtimes while retaining PHP 7.2 syntax compatibility.
- Improved random generators and added hexadecimal tokens and file-based request throttling helpers.
- Extended MySQL driver with explicit port handling and optional persistent connections.
- Extended image processing with Imagick support, GD fallback, diagnostics, watermarking and image profile handling. Imagick is preferred when available.
- Removed the unused, unfinished Charon queue class.

`mbstring` is required by the core. Image and database features require their corresponding PHP extensions. Persistent MySQL connections remain opt-in.

Application integration, image operations and actual database connections must be verified in the consuming projects; the isolated checks do not replace those tests.
