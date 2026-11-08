"""Build the canonical allowlisted ZIP from committed Git bytes, independent of checkout OS."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import io
import json
import os
import subprocess
import sys
import tarfile
import zipfile

root = Path(__file__).resolve().parents[1]
git = r'C:\Program Files\Git\cmd\git.exe' if os.name == 'nt' else 'git'

def run(*args):
    return subprocess.check_output([git, '-C', str(root), *args])

import re
entry = run('show', 'HEAD:pixel_cloudflare_turnstile.php').decode('utf-8')
version = re.search(r"\$this->version\s*=\s*'([^']+)'", entry).group(1)
module = {'name': 'pixel_cloudflare_turnstile', 'version': version}
identity = {'artifact': 'pixel_cloudflare_turnstile-' + version + '.zip'}
inputs = ['pixel_cloudflare_turnstile.php', 'pixel_cloudflare_turnstile.tpl', 'src', 'upgrade', 'views', 'translations', 'index.php', 'logo.png', 'LICENCE']
paths = inputs
if run('rev-parse', '--is-shallow-repository').strip() != b'false':
    raise SystemExit('Full Git history is required for a reproducible release timestamp.')
if run('diff', '--name-only', 'HEAD', '--', *paths).strip():
    raise SystemExit('Commit the complete release source before building the qualification ZIP.')
untracked = run('ls-files', '--others', '--exclude-standard', '--', *paths).strip()
if untracked:
    raise SystemExit('Untracked production files must be reviewed and committed before packaging.')
epoch = int(run('log', '-1', '--format=%ct', '--', *paths))
date = datetime.fromtimestamp(epoch, timezone.utc).timetuple()[:6]
archive = run('-c', 'core.autocrlf=false', 'archive', '--format=tar', 'HEAD', '--', *paths)
output = Path(sys.argv[1]) if len(sys.argv) > 1 else root / 'dist'
output.mkdir(parents=True, exist_ok=True)
target = output / identity['artifact']
assert target.name == module['name'] + '-' + module['version'] + '.zip'
manifest = {}
with tarfile.open(fileobj=io.BytesIO(archive)) as tar, zipfile.ZipFile(target, 'w') as package:
    for member in sorted(tar.getmembers(), key=lambda item: item.name):
        if not member.isfile():
            continue
        relative = member.name
        assert '..' not in Path(relative).parts
        assert not relative.startswith(('tests/', 'scripts/'))
        entry = module['name'] + '/' + relative
        data = tar.extractfile(member).read()
        info = zipfile.ZipInfo(entry, date)
        info.create_system = 3
        info.external_attr = 0o100644 << 16
        info.compress_type = zipfile.ZIP_STORED
        package.writestr(info, data)
        manifest[relative] = hashlib.sha256(data).hexdigest()
record = {'artifact': target.name, 'sha256': hashlib.sha256(target.read_bytes()).hexdigest(),
          'source_commit': run('rev-parse', 'HEAD').decode().strip(), 'epoch': epoch,
          'files': manifest}
(output / (target.stem + '.manifest.json')).write_text(json.dumps(record, indent=2)+'\n', encoding='utf-8')
print(json.dumps({key: value for key, value in record.items() if key != 'files'}), 'files', len(manifest))

target.with_suffix('.zip.sha256').write_text(record['sha256'] + '  ' + target.name + '\n', encoding='ascii')
