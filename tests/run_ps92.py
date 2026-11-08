"""Qualify exact ZIPs through native PS9 lifecycle and loopback HTTP."""
from pathlib import Path
import argparse
import os
import subprocess
import time
root=Path(__file__).resolve().parents[1]
p=argparse.ArgumentParser()
p.add_argument('--project',default='turnstile-ps92-qualification')
p.add_argument('--compose-override')
p.add_argument('--keep',action='store_true')
args=p.parse_args()
assert args.project.startswith('turnstile-ps92-')
docker=r'C:\Program Files\Docker\Docker\resources\bin\docker.exe' if os.name=='nt' else 'docker'
container=args.project+'-prestashop-1'
compose=[docker,'compose','-p',args.project,'-f',str(root/'tests/compose.ps92.yml')]
if args.compose_override: compose+=['-f',str(Path(args.compose_override).resolve())]
logs=root/'.qualification'; logs.mkdir(exist_ok=True); count=0
def run(name,command):
    global count
    count+=1
    result=subprocess.run(command,capture_output=True,text=True,encoding='utf-8',errors='replace')
    (logs/f'{count:02d}-{name}.log').write_text(result.stdout+result.stderr,encoding='utf-8')
    print(name,result.returncode,(result.stdout+result.stderr)[-1200:],flush=True)
    result.check_returncode()
def php(name,*params):
    run(name,[docker,'exec','-u','www-data','-e','TURNSTILE_TEST_SHOP=1','-w','/var/www/html',container,'php',*params])
def fixture(action,*params): php(action,'/module/tests/native/fixture.php',action,*params)
run('start',compose+['up','-d'])
try:
    for _ in range(120):
        ready=subprocess.run([docker,'exec',container,'sh','-c','test ! -e /var/www/html/install.lock && test -f /var/www/html/app/config/parameters.php && test -d /var/www/html/admin-dev && pgrep apache2'],capture_output=True)
        if ready.returncode==0: break
        time.sleep(3)
    else: raise RuntimeError('Synthetic shop did not finish installation')
    run('ownership',[docker,'exec',container,'chown','-R','www-data:www-data','/var/www/html/modules','/var/www/html/override','/var/www/html/var'])
    fixture('prepare')
    fixture('extract','/module/dist/pixel_cloudflare_turnstile-1.2.0.zip')
    fixture('install'); fixture('seed'); fixture('snapshot'); fixture('status')
    fixture('reset'); fixture('status'); fixture('preserved')
    fixture('uninstall'); fixture('uninstalled'); fixture('preserved')
    fixture('extract','/module/tests/fixtures/pixel_cloudflare_turnstile-1.1.4.zip')
    fixture('install'); fixture('status','1.1.4'); fixture('snapshot')
    fixture('extract','/module/dist/pixel_cloudflare_turnstile-1.2.0.zip')
    fixture('upgrade'); fixture('status'); fixture('hooks-preserved'); fixture('preserved')
    fixture('disable'); fixture('reset'); fixture('status'); fixture('preserved')
    fixture('render')
    php('native-atomic','/module/tests/native/atomic.php')
    php('native-scope','/module/tests/native/scope.php')
    php('mcp-setup','/module/tests/native/mcp-setup.php')
    run('mcp-http',[docker,'exec','-u','www-data','-e','TURNSTILE_TEST_SHOP=1','-e','TURNSTILE_MCP_HOST=localhost:19105','-w','/var/www/html',container,'php','/module/tests/native/mcp.php'])
    run('settings-http',[os.sys.executable,str(root/'tests/http_ps92.py')])
    fixture('status')
    if not args.keep:
        fixture('snapshot'); fixture('uninstall'); fixture('uninstalled'); fixture('preserved')

finally:
    if not args.keep: run('stop',compose+['stop'])
