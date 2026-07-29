# Contributing

Use PHP 8.5 and the Composer versions recorded in `composer.lock`.

1. Create a focused branch.
2. Run `composer install`.
3. Add or update PHPUnit tests.
4. Run `composer verify` and `composer audit --locked`.
5. Use a Conventional Commit such as `feat: add maintenance banner`.
6. Open a pull request describing behavior, security impact, and verification.

Never commit `.env`, production credentials, customer data, or database dumps.
