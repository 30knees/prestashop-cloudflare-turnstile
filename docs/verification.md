# Turnstile qualification

## 1.2.2: provider script only where a widget renders - 20 November 2026

Target remains PrestaShop 9.2.x / PHP 8.5 only. Runtime source
2095ed7c5e5aee00d0e0326db5a4c513e6235a0d; exact 23-file ZIP SHA-256
`d388bdf8f74ed77c51393c93aa7e111ccbfd54fedb64ae3af23b07658637281c`. Only the entry point
and the widget template differ from 1.2.1. No upgrade script, settings, translation or MCP
change.

- Change: `hookActionFrontControllerSetMedia()` keeps the stylesheet on every page and
  registers `api.js` (remote, head, async) only when `canProcess()` accepts the current
  controller. `renderWidget()` assigns `provider_script` to the first widget of a request
  that has no head copy, and the template prints
  `<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>`
  inside the widget wrapper, after the container. A static flag shared by both paths keeps it
  to one copy per request; a missing sitekey renders neither widget nor script.
- Cloudflare's [client-side rendering guide](https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/)
  places `api.js` in the head or before `</body>`; implicit rendering scans for `cf-turnstile`
  elements when the script loads and is meant for forms present at page load. The guide does
  not discuss a script inside the body; here the container is always parsed before the script
  can run, which is the property both documented placements rely on. Dynamically inserted
  widgets need explicit rendering per the guide; a search of the theme and workspace modules
  found no `turnstile.render`/`reset`/`execute` caller and no AJAX-inserted widget.
  SimpleWithdrawal's custom widget is the one consumer outside the protected controllers.
- PHP 8.5.12 (Docker `php:8.5-cli`): 24 files lint; 42 settings cases; 108 component checks
  (46 earlier plus placement for eight controllers in three form configurations, once per
  request, contact/registration head copy, custom form page, disabled newsletter, missing
  sitekey, checkout); runtime gates for 9.2.0/9.2.99 accepted and 9.1.0/9.3.0 refused,
  plus the install hook set; 19 guard mutations (5 new for script placement) rejected.
  PHP 8.2.35: unsupported runtime refused and its guard mutation rejected. Exact package
  manifest: 23 files.
- Disposable PS 9.2.0 / PHP 8.5.12 shop (existing test shop): exact 1.2.1 install, seed,
  native upgrade to the exact 1.2.2 ZIP, hooks and preservation, render (179 fixture
  assertions); `tests/http_ps92.py` 201 checks including an 18-case storefront matrix
  (home, product, contact, registration, login, password in three form configurations):
  head copy only on protected form pages, one async/defer copy after the first widget
  elsewhere, none where no widget renders. Teardown: uninstall and preservation pass.
- Headless Chrome on that shop (Cloudflare's public always-pass test sitekey): the widget
  rendered on home and product with the script beside it and on contact and registration
  with the head copy; each container gained the provider-created `cf-turnstile-response`
  input, absent from the server HTML. No dummy token was issued in headless mode for either
  placement, so challenge completion is not verified.
- [Hosted CI 44746147220](https://github.com/30knees/prestashop-cloudflare-turnstile/actions/runs/44746147220)
  passes on 2095ed7: static suites, fresh native lifecycle with exact 1.1.4, 1.2.0 and 1.2.1
  upgrade sources (576 fixture assertions), 12 transaction, 11 scope, 16 authenticated MCP,
  201 HTTP and 56 localization checks. The CI ZIP is byte-identical to the local ZIP.
- Hosted staging (PS 9.2.1 / PHP 8.5.13, forms contact, register, newsletter): native
  1.2.1-to-1.2.2 upgrade under the staging lock; 23 package hashes, 15 supplemental files,
  four settings rows, five hooks, shop association, policy (including CCC and the
  SimpleWithdrawal Turnstile switch), neighboring modules, theme, design module, overrides,
  core files, robots.txt and commerce counts keep their before fingerprints. CLI renders of
  six German pages (CCC off in memory only): before, `api.js` was in the head of all six;
  after, the home, login and product pages load none and show no widget, contact and
  registration keep one head copy with their widget, and the withdrawal form carries one
  async/defer copy after its widget. The stylesheet stays on all six. Native SDK status
  reports 1.2.2. Both scanners report the same findings as 1.2.1 (SDK classes resolved by
  the staged SDK read; three SQL-identifier backticks).
- MCP: this release changes no agent-facing surface; `turnstile://status` is unchanged.
- Not checked: a browser view on staging (Basic auth), real-key challenge completion,
  PageSpeed re-run, production. A theme override of the widget template must keep the
  `provider_script` block.

## 1.2.1: settings localization - 17 November 2026

Target remains PrestaShop9.2.x/PHP8.5 only; platform requirements and behavior are
unchanged. Runtime source c5e051a08c6128622864122415995caabdc9af6e. Exact23-file ZIP
SHA-256 `845aedbcc496ac79a018a4707e3e6018c4e47239bad6ae1c311505da74c24616`.
The release translates six settings messages in the four bundled locales and
routes three previously literal messages through the native translator.

- PHP8.5.12:24 files lint;42 settings,46 components,14 existing guard mutations
  and platform acceptance/refusal checks pass. No new authorization guard added.
- Previous exact1.2.0 ZIP:44 localization failures in56 checks; the12 settings
  preservation/privacy controls pass. Candidate: all56 checks pass, including
  native catalogue/placeholder matching, translated HTTP validation, token-denial
  and database-failure rendering. The real write failure rolls back all settings.
- Fresh disposable PS9.2.0/PHP8.5.12/MariaDB10.11:442 lifecycle/render/preservation
  assertions, exact1.1.4 and1.2.0 upgrades, enabled/disabled Reset and uninstall;
  12 native transaction/cache,11 SDK/scope,16 authenticated MCP and123 HTTP checks
  pass. Mail is disabled and all credentials/settings are synthetic.
- [CI44049322943](https://github.com/30knees/prestashop-cloudflare-turnstile/actions/runs/44049322943)
  passes on2fa203c. Downloaded Linux ZIP bytes match the local Windows ZIP exactly.
  Unsupported PHP refusal and removal of its guard also pass in hosted CI.
- Hosted staging: native1.2.0-to-1.2.1 upgrade;23 package hashes,15 supplemental
  files, four settings rows, five hooks, association and surrounding configuration,
  source and commerce fingerprints preserved. No settings save or staging backup.
  All28 native localized-message/denied-render checks pass. Both scanners run
  against14 runtime PHP files; SDK reports are resolved by native staged metadata
  and status reads, and SQL-identifier backticks are reviewed false positives.
- MCP assessment: no new agent operation. The existing read-only current-shop
  status resource is unchanged; local authenticated and staged direct checks pass.
- Not checked in this run: visual reflow, keyboard/200%zoom (browser access host
  unavailable), native-speaker wording, public staging MCP transport or real-key
  Cloudflare challenges. Earlier browser evidence does not verify the new strings.
- Production: owner-authorized native 1.2.0-to-1.2.1 upgrade completed on
  17 November at 09:11 UTC, on PrestaShop 9.2.1 / PHP 8.5.12. All 23 package
  hashes, 15 supplemental files, four settings rows/credentials, five hooks and
  shop association verified; policy and neighboring module versions unchanged.
  A scoped private host recovery set was verified before replacement. All 28
  native translation/denied-render checks pass on production, along with direct
  SDK/status reads. Public storefront/contact/registration GETs return 200, both
  protected forms include the widget, and public CSS matches the release manifest.
  No settings save or provider challenge was submitted. Visual/native-speaker
  and authenticated production MCP acceptance remain separate. Older records
  below describe their dated historical state.

## Turnstile 1.2.0 qualification - 8 November 2026

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
