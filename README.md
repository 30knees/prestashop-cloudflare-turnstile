# Prestashop Cloudflare Turnstile

Maintained PS9 fork by 30knees, based on Pixel Open 1.1.7. Vendor history and
license remain intact on `upstream`; maintained changes live on `main`.

## Presentation

[Turnstile](https://developers.cloudflare.com/turnstile/) is Cloudflare's smart CAPTCHA alternative. The module allows Turnstile to protect your Prestashop forms:

- Contact
- Login
- Register
- Reset password
- Newsletter subscription
- Custom or third-party forms

![Cloudflare Turnstile](screenshot.png)

## Requirements

Drops support for PrestaShop 8.x and PHP below 8.5; requires PrestaShop 9.2.x and PHP 8.5.

Install and native upgrade reject unsupported platforms before lifecycle writes.

## Installation

Build the versioned **pixel_cloudflare_turnstile-1.2.0.zip** with `python tools/build.py`
from committed source. The manifest records every packaged file and ZIP SHA-256.

### Admin

Go to the admin module catalog section and click **Upload a module**. Select the downloaded zip file.

### Manually

Move the downloaded file in the Prestashop **modules** directory and unzip the archive. Go to the admin module catalog section and search for "Cloudflare Turnstile".

## Configuration

- **Sitekey**: the sitekey given for the site in your Cloudflare dashboard
- **Secret key**: the secret key given for the site in your Cloudflare dashboard
- **Theme**: the Turnstile theme (auto, light or dark)
- **Forms to validate**: the forms where a Turnstile validation is required

For the registration form, the widget is automatically added with a hook. For "contact", "login" and "reset password" forms, **you need to manually add the widget in the template files**, usually before the validation button.

**Never select a form to validate without the widget in the form template.**

### Widget

```html
{widget name='pixel_cloudflare_turnstile'}
```

Override the default configured theme by adding a theme option (auto, light or dark):

```html
{widget name='pixel_cloudflare_turnstile' theme='dark'}
```

Override the default action name by adding an action option:

```html
{widget name='pixel_cloudflare_turnstile' action='my-form'}
```

### Forms

| Form           | Template                                                                      |
|----------------|-------------------------------------------------------------------------------|
| Contact        | themes/{themeName}/modules/contactform/views/templates/widget/contactform.tpl |
| Login          | themes/{themeName}/templates/customer/_partials/login-form.tpl                |
| Reset password | themes/{themeName}/templates/customer/password-email.tpl                      |

### Protect a custom or third-party form

1. Add the Cloudflare Turnstile widget in the Smarty form template:

```html
{widget name='pixel_cloudflare_turnstile' custom='1' action='custom-form'}
```

**Note:** The action param is a free name that will appear in the Turnstile statistics

2. In a module, add a new hook to call Turnstile validation on form post:

```php
public function install(): bool
{
    return parent::install() &&
        $this->registerHook('actionFrontControllerInitBefore');
}

public function hookActionFrontControllerInitBefore(array $params): void
{
    $controllerClass = get_class($params['controller']);

    if ($controllerClass === 'MyFormController' && Tools::isSubmit('myForm')) {
        Pixel_cloudflare_turnstile::turnstileValidation();
    }
}
```

If the validation fails, the customer is redirected to the previous page with an error message.

### Testing

Use the **Test mode** option in the module configuration to test without real Cloudflare keys:

| Mode                       | Description                                      |
|----------------------------|--------------------------------------------------|
| Disabled (production)      | Uses your real Cloudflare keys                   |
| Always passes              | Widget always validates successfully             |
| Always fails               | Widget always fails validation                   |
| Force interactive challenge| Forces the user to complete an interactive check |

This automatically uses Cloudflare's official test keys, so you don't need to manually change your sitekey and secret key.

## Release 1.2.0

The port preserves configuration and credentials across upgrades, Reset and
uninstall/reinstall. Remove retained credentials explicitly through shop
configuration management if permanently retiring this module.

Settings require native and session-bound CSRF checks, configure permission and
one active shop. All fields validate before a transaction; a failed write rolls
back and clears the configuration cache. The secret input always stays empty;
leave it blank to keep the current key. The settings heading reads the installed
version from module metadata.

Provider validation accepts only HTTP 200 with a JSON boolean `success: true`.
Malformed tokens/replies fail closed without leaking transport diagnostics.
Widget attributes are escaped and each render uses its current options. Error
redirects stay on the shop origin.

`turnstile://status` is a read-only MCP resource for the current active shop. It
reports installed/code versions, protected forms, test mode, credential presence
and hook registration. It does not return keys, verify challenges, modify settings
or certify theme placement. No custom write tool is exposed: secret rotation and
form enablement remain in native settings with human review of widget placement.

Run `bash .ci/run.sh`, then `python tests/run_ps92.py`. The native suite uses only a
disposable localhost shop, synthetic keys and disabled mail. Official Cloudflare
dummy-key network checks are a separate `fixture.php provider` step. See
[qualification evidence](docs/verification.md) for completed checks and limits.

Release verification:42 settings cases,46 component checks,14 guard mutations,
248 native lifecycle/render assertions,12 transaction/cache,11 SDK,16 authenticated
MCP and123 HTTP checks passed. Exact CI/staging parity and95 staged assertions
across13 languages passed. Official dummy keys and SimpleWithdrawal2.6.1 passed
separate synthetic checks. Browser reflow and keyboard focus passed; true200%
zoom and full accessibility remain unverified.

The current staging theme remains held for separate migration. Enabling protection
without a widget can block a form; this release does not alter held theme files.
No production deployment or real-key challenge acceptance is implied by the port.