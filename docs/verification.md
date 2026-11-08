# Turnstile 1.2.0 qualification - 8 November 2026

Drops support for PrestaShop 8.x and PHP below 8.5; requires PrestaShop 9.2.x and PHP 8.5.

Target: PrestaShop9.2.0-9.2.99/PHP8.5 only, with install/native-upgrade preflight
before parent writes. The upgrade script preserves all settings and hook positions.
Canonical23-file ZIP SHA-256:
`0b93fc87bbd08631321999d34fef9ab183befa4591f11b1e223d012b096d73ce`.
Runtime source `1e5d2d2943200adfadb2d638c6d8d4c054a389f7`; tag `v1.2.0`.
Windows and Linux CI ZIP bytes and contents match exactly. Successful candidate
[CI42465828469](https://github.com/30knees/prestashop-cloudflare-turnstile/actions/runs/42465828469)
was downloaded and compared before staging activation.

## Source and static checks

The existing owner-authorized public fork is retained. Pristine vendor history
stays on `upstream`; the earlier settings contribution stays on
`fix/settings-validation`. Vendor1.1.7 remains the latest published release and
upstream PR20 remained open on8November. Vendor copyright/license files remain.

PHP8.5.12 lint passes23 source/test files. Both maintained scanners ran on staging
against14 runtime PHP files, including retained translations. No removed
controller helpers or undefined `$this` calls. Three optional MCP SDK class
reports are resolved by installed official MCP Server1.0.3 tests; three backtick
matches are SQL identifiers, not shell execution.

## Behavioral and native qualification

- `tests/settings.php`:42 cases on PHP8.5.12. All-field validation, native CSRF,
  independent session token, POST, employee configure/shop permission, active
  single-shop context, secret masking/retention and failed-write rollback.
- `tests/components.php`:46 fake-transport provider/form/widget/redirect checks.
  Strict HTTP200 and JSON boolean success, malformed/oversized tokens/replies,
  transport errors, stale errors, missing keys, unknown test modes, widget option
  validation and same-origin error redirects.
- `tests/mutations.php`:14 guard removals rejected on PHP8.5.12. Unsupported-PHP
  refusal and its floor mutation pass on Windows PHP8.2.32 and CI PHP8.2.35.
  PS9.2.0/9.2.99 accepted;9.1/9.3 refused. PHP8.6 guarded but not executed.
- `python tests/run_ps92.py`:248 native lifecycle/render/preservation assertions
  on exact ZIP, PS9.2.0/PHP8.5.12/MariaDB10.11, synthetic data and disabled mail.
  Fresh install, enabled Reset, uninstall/reinstall, exact1.1.4 upgrade and
  disabled Reset pass. Native Reset re-enables the module. Configuration/keys,
  upgrade hook positions and commerce/policy/core fingerprints are preserved.
  Uninstall removes registration/hooks and intentionally retains configuration.
- Frozen1.1.4 ZIP SHA-256:
  `784b3f2f5a23bcbf2a71b6cba228deebe72ca6f80b6033ad9bd5a22bf5603a7f`.
  All18 original package files matched staging before upgrade.
- Native Smarty checks reproduce/fix cached widget options with caching enabled.
  Each action/theme/appearance and changed escaped sitekey renders independently.
  Caching is disabled only on the template instance. Engineering-guide trap81
  records the failed initial native test and correction.
- `tests/native/atomic.php`:12 real database-trigger failure, rollback, native
  configuration-cache parity and same-process successful-retry checks. Authority
  is independently tested over HTTP.
- `tests/http_ps92.py`:123 real HTTP checks across two native configure routes
  and two independent sessions. Missing/forged/array/other-session tokens, GET,
  malformed input and anonymous writes fail. Valid saves preserve blank secrets;
  six controls have associated labels; registration/footer widgets and script
  render. No real customer or account is created.

Official Cloudflare dummy pass/fail keys also pass3 local network assertions
(including disabled mail). The exact SimpleWithdrawal2.6.1 consumer passes3
checks: dummy pass, dummy fail and missing token. These are synthetic checks,
not real-key challenge acceptance. Sources: [server validation](https://developers.cloudflare.com/turnstile/get-started/server-side-validation/)
and [dummy-key testing](https://developers.cloudflare.com/turnstile/troubleshooting/testing/).

## MCP assessment

The earlier settings-only contribution needed no endpoint. The full port needs
deployment diagnostics for protected forms and the SimpleWithdrawal dependency,
so `turnstile://status` is implemented and explicitly loaded for real discovery.
It reports versions, forms, test mode, credential-presence booleans and hooks for
one consistent active shop. No keys, challenge tokens, provider calls or writes.

Eleven native SDK/scope and16 authenticated Viewer HTTP checks pass on MCP
Server1.0.3: discovery/read, invalid/anonymous denial, unknown URI, disabled
association/global module rejection and configuration/hook preservation. Extra
arguments, aggregate/inactive/deleted/mismatched contexts fail. An initial
concurrent settings/MCP check detected the unrelated test writes; final isolated
execution and CI pass. Public staging/production MCP transport is unverified.

## Staging gate

Native CLI upgrade1.1.4 to1.2.0 exited0; stored/code version and activation match.
All23 package hashes match. Fifteen supplemental translation/files remain
byte-identical; two native XML metadata files account for40 total module files.
Four configuration rows, five hook positions and shop association are unchanged.
Protected forms remain contact/register/newsletter, light theme, existing real
keys and disabled test mode. No staging settings were saved.

Five policy rows, neighboring modules, six commerce counts,1,949 held GreenMouse
files,155 Preview files,11 overrides, core Tools/FrontController and robots.txt
hash/size/mtime remain unchanged. No staging backup or regeneration was made.
The read-only probe passes95 assertions across13 languages: current widgets,
no secret output, official SDK metadata and context refusals.

Maintained home render:191,618 bytes, Preview active/compatible. The known first
menu-cache miss clears on repeat; existing core/vendor/held-theme deprecations
remain. This is CLI rendering evidence, not public HTTP acceptance.

## Browser and remaining acceptance

Agent Browser/node_repl verified local and authenticated staging settings1.2.0,
empty secret input, six associated labels and solid keyboard focus. Staging390px
and320px widths have375px/305px document widths. Long template paths wrap. Desktop
settings and dummy registration/footer widgets were inspected; dummy tokens
appeared without solving a challenge.

The existing staging tab reported a Page.getFrameTree timeout during Back
navigation; immediate AX readback proved navigation had completed. The same
connection was reused and a fresh agent tab opened the observed settings URL.
No Computer Use fallback or permission change.

Four Ctrl+plus presses produced no measured width/DPR change; true200% zoom is
unverified. Full screen-reader/accessibility, all-language browser acceptance,
real-key challenges, contact/login/password widget placement in the assembled
theme and owner acceptance remain separate. GreenMouse stays held and unchanged.
Status does not certify placement. Provider action/hostname policy remains the
existing Cloudflare configuration; no new enforcement is claimed.

Production and the embedded shop copy are unchanged. Workspace release evidence
contains CI/main/tag readback, staging logs and screenshots without stored
secret values or staging backups.
