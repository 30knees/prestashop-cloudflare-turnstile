# Changelog

## 1.2.0 - 2026-11-08

Drops support for PrestaShop 8.x and PHP below 8.5; requires PrestaShop 9.2.x and PHP 8.5.

- Preserve all configuration scopes and credentials during Reset, upgrade and uninstall/reinstall.
- Require native CSRF validation, an independent session-bound form token, a logged-in authorized employee and one active shop.
- Save validated settings atomically; retain a blank secret input and never echo the configured secret.
- Require a strict successful provider response; reject malformed/oversized tokens and sanitize transport failures.
- Escape widget attributes, render each widget's current options and restrict error redirects to the shop origin.
- Add read-only current-shop MCP status without keys, tokens, provider requests or writes.
- Add exact package, component, guard mutation, native lifecycle, HTTP and MCP qualification suites.

## Earlier settings contribution (upstream PR 20)

- Validate every configuration field before saving; reject invalid selection values and malformed input.
- Report failed configuration writes without a success message and explain partial persistence.
- Require POST and module configure permission for settings writes; use the native configure URL.
- Keep malformed submissions renderable, show the module version, and add isolated settings regressions.

## 1.1.7

- Fix module config redirect URL for Nginx compatibility

## 1.1.6

- Add test mode option (disabled, always passes, always fails, force interactive)

## 1.1.5

- Add appearance mode option (always, execute, interaction-only/invisible)
- Add German, Spanish and Italian translations

## 1.1.4

- Add newsletter registration form ([@jf-viguier](https://github.com/jf-viguier))

## 1.1.3

- Fixed widget not showing on third party form

## 1.1.2

- Fix current theme directory retrieval ([@jf-viguier](https://github.com/jf-viguier))

## 1.1.1

- Fix renew password form turnstile error

## 1.1.0

- Prestashop 8.0.0 compatibility

## 1.0.3

- Custom or third party form validation

## 1.0.2

- Action option added

## 1.0.1

- Display full error messages instead of error codes
- PHPDoc updated

## 1.0.0

- First stable release
