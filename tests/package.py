from pathlib import Path
import hashlib,json,zipfile
root=Path(__file__).resolve().parents[1]
record=json.loads((root/'dist/pixel_cloudflare_turnstile-1.2.0.manifest.json').read_text())
path=root/'dist'/record['artifact']
assert hashlib.sha256(path.read_bytes()).hexdigest()==record['sha256']
with zipfile.ZipFile(path) as z:
    assert len(z.namelist())==len(set(z.namelist()))==len(record['files'])
    for name,digest in record['files'].items():
        assert hashlib.sha256(z.read('pixel_cloudflare_turnstile/'+name)).hexdigest()==digest
        assert not any(s in name.split('/') for s in ('tests','tools','.git','.qualification'))
    assert {'src/Mcp/StatusResource.php','upgrade/upgrade-1.2.0.php','pixel_cloudflare_turnstile.tpl','LICENCE'}<=record['files'].keys()
print('Exact package manifest:',len(record['files']),'files verified.')
