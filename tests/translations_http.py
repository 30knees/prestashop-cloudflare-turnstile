"""Render translated settings errors over real HTTP, with native failure injection."""
import argparse
import html
import json
import os
from pathlib import Path
import subprocess
from http_ps92 import Client, Forms, settings

root = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--container', required=True)
parser.add_argument('--baseline', action='store_true')
args = parser.parse_args()
assert args.container.startswith('turnstile-ps92-')
docker = r'C:\Program Files\Docker\Docker\resources\bin\docker.exe' if os.name == 'nt' else 'docker'
expected = json.loads((root / 'tests/settings-translations.json').read_text(encoding='utf-8'))
checks = 0
failures = []

def native(action, *params):
    return subprocess.check_output([docker, 'exec', '-u', 'www-data', '-e', 'TURNSTILE_TEST_SHOP=1', '-w', '/var/www/html', args.container, 'php', '/module/tests/native/localization.php', action, *params], text=True, encoding='utf-8').strip()

def check(ok, label):
    global checks
    checks += 1
    if not ok:
        failures.append(label)

try:
    for locale, messages in expected.items():
        native('language', locale)
        actual = json.loads(native('messages', locale))
        for source, translated in messages.items():
            check(actual[source] == translated.replace('%field%', 'Field'), locale + ': native catalogue ' + source)
        check(messages['You cannot configure these settings.'] in actual['denied_html'], locale + ': configure-denied rendering')
        client = Client()
        code, page, _, _ = client.request('/admin-dev/')
        login = next(f for f in Forms(page).forms if f['attrs'].get('id') == 'login_form')
        code, page, _, _ = client.request(login['attrs']['action'], login['fields'] | {'email': 'demo@prestashop.local', 'passwd': 'PrestaShop92!'})
        assert code == 200 and 'id="login_form"' not in page, 'Native login failed'
        manager = next(link for link in Forms(page).links if '/improve/modules/manage' in link)
        _, page, _, _ = client.request(manager)
        configure = next(link for link in Forms(page).links if '/configure/pixel_cloudflare_turnstile' in link)
        code, page, _, url = client.request(configure)
        assert code == 200
        form = settings(page)
        check(messages['Leave blank to keep the configured secret key.'] in html.unescape(page), locale + ': secret guidance rendered')
        before = native('snapshot')
        fields = form['fields'] | {'submitpixel_cloudflare_turnstile': '1', 'CLOUDFLARE_TURNSTILE_THEME': 'invalid'}
        code, body, _, _ = client.request(url, fields)
        translated_label = {'de-DE': 'Thema', 'es-ES': 'Tema', 'fr-FR': 'Thème', 'it-IT': 'Tema'}[locale]
        check(code == 200 and messages['%field% is invalid'].replace('%field%', translated_label) in html.unescape(body), locale + ': invalid field rendered')
        check(native('snapshot') == before, locale + ': invalid submission preserves all settings')
        fields = form['fields'] | {'submitpixel_cloudflare_turnstile': '1', 'turnstile_form_token': 'invalid'}
        code, body, _, _ = client.request(url, fields)
        check(code == 200 and messages['You cannot save these settings.'] in html.unescape(body), locale + ': denied save rendered')
        native('fail-write')
        try:
            fields = form['fields'] | {'submitpixel_cloudflare_turnstile': '1', 'CLOUDFLARE_TURNSTILE_SITEKEY': 'synthetic-must-rollback', 'CLOUDFLARE_TURNSTILE_THEME': 'dark'}
            code, body, _, _ = client.request(url, fields)
            check(code == 200 and messages['Settings were not saved. Reload this page before retrying.'] in html.unescape(body), locale + ': storage failure rendered')
            check('synthetic private translation failure' not in body, locale + ': private storage detail hidden')
            check(native('snapshot') == before, locale + ': failed write rolls back all settings')
        finally:
            native('clear-failure')
finally:
    native('language', 'en-US')

result = {'checks': checks, 'failures': failures, 'baseline': args.baseline}
print(json.dumps(result, ensure_ascii=False, indent=2))
if args.baseline:
    assert any('catalogue %field% is invalid' in item for item in failures), 'Original regression was not reproduced'
else:
    assert not failures, 'Localized rendering failed'
