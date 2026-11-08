"""Real HTTP settings and storefront checks, confined to the disposable shop."""
from html.parser import HTMLParser
import http.cookiejar
import json
from pathlib import Path
import re
import urllib.error
import urllib.parse
import urllib.request

BASE='http://localhost:19105'
OUT=Path(__file__).resolve().parents[1]/'.qualification'
OUT.mkdir(exist_ok=True)
checks=0

def check(condition,message):
    global checks
    checks+=1
    if not condition: raise AssertionError(message)

class Forms(HTMLParser):
    def __init__(self,html):
        super().__init__(convert_charrefs=True)
        self.forms=[]; self.links=[]; self.form=None; self.select=None; self.textarea=None; self.base=None
        self.labels=set(); self.inputs={}; self.language_selects=[]
        self.feed(html)
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag=='label' and a.get('for'): self.labels.add(a['for'])
        if tag in ('input','select') and a.get('name','').startswith('CLOUDFLARE_TURNSTILE_'): self.inputs[a['name']]=a
        if tag=='select' and '_language_' in a.get('id',''): self.language_selects.append(a)
        if tag=='base' and 'href' in a: self.base=a['href']
        if tag=='a' and 'href' in a: self.links.append(a['href'])
        if tag=='form':
            self.form={'attrs':a,'fields':{}}; self.forms.append(self.form)
        elif self.form is not None:
            name=a.get('name')
            if tag=='input' and name and a.get('type') not in {'submit','button'}:
                if a.get('type') not in {'checkbox','radio'} or 'checked' in a:
                    self.form['fields'][name]=a.get('value','')
            elif tag=='select':
                self.select=name
                if 'multiple' in a: self.form['fields'][name]=[]
            elif tag=='option' and self.select:
                if isinstance(self.form['fields'].get(self.select),list):
                    if 'selected' in a: self.form['fields'][self.select].append(a.get('value',''))
                elif self.select not in self.form['fields'] or 'selected' in a:
                    self.form['fields'][self.select]=a.get('value','')
            elif tag=='textarea':
                self.textarea=name; self.form['fields'][name]=''
    def handle_data(self,data):
        if self.form is not None and self.textarea:
            self.form['fields'][self.textarea]+=data
    def handle_endtag(self,tag):
        if tag=='form': self.form=None
        elif tag=='select': self.select=None
        elif tag=='textarea': self.textarea=None

class NativeRedirects(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,request,response,code,message,headers,new_url):
        assert urllib.parse.urlsplit(new_url).netloc=='localhost:19105', 'Redirect left disposable shop'
        # urllib refuses POST 307/308 by default. Browsers preserve the form body.
        if code in (307,308) and request.get_method()=='POST':
            return urllib.request.Request(new_url,request.data,dict(request.headers),method='POST')
        return super().redirect_request(request,response,code,message,headers,new_url)

class Client:
    def __init__(self):
        self.jar=http.cookiejar.CookieJar()
        self.opener=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(self.jar),NativeRedirects())
    def request(self,url,data=None):
        url=urllib.parse.urljoin(BASE,url)
        assert urllib.parse.urlsplit(url).netloc=='localhost:19105'
        try:
            response=self.opener.open(urllib.request.Request(url,None if data is None else urllib.parse.urlencode(data,doseq=True).encode()),timeout=60)
        except urllib.error.HTTPError as error: response=error
        body=response.read().decode('utf-8','replace')
        return response.status,body,response.headers,response.url

def login(client):
    code,page,_,_=client.request('/admin-dev/')
    form=next(f for f in Forms(page).forms if f['attrs'].get('id')=='login_form')
    code,page,_,_=client.request(form['attrs']['action'],form['fields']|{'email':'demo@prestashop.local','passwd':'PrestaShop92!'})
    check(code==200 and 'id="login_form"' not in page,'native employee login')
    manager=next(link for link in Forms(page).links if '/improve/modules/manage' in link)
    code,page,_,_=client.request(manager)
    configure=next(link for link in Forms(page).links if '/configure/pixel_cloudflare_turnstile' in link)
    code,page,_,url=client.request(configure)
    check(code==200 and '(1.2.0)' in page,'settings shows installed version')
    return page,url

def settings(page):
    return next(form for form in Forms(page).forms if 'CLOUDFLARE_TURNSTILE_SITEKEY' in form['fields'])

client=Client()
page,canonical=login(client)
form=settings(page)
legacy=urllib.parse.urljoin(Forms(page).base or canonical,form['attrs']['action'])
original={k:v for k,v in form['fields'].items() if k.startswith('CLOUDFLARE_TURNSTILE_')}
check(len(original)==6,'six settings fields')
check(original['CLOUDFLARE_TURNSTILE_SECRET_KEY']=='','stored secret is never rendered')
check('synthetic-secret-retain' not in page,'no stored secret in HTML')
check('turnstile_form_token' in form['fields'],'independent form body token')
parsed=Forms(page)
check(len(parsed.inputs)==6 and all(a.get('id') in parsed.labels for a in parsed.inputs.values()),'six controls have associated labels')
check(urllib.parse.urlsplit(legacy).netloc=='localhost:19105','native absolute form action')
other=Client(); other_page,_=login(other)
other_token=settings(other_page)['fields']['turnstile_form_token']
check(other_token!=form['fields']['turnstile_form_token'],'independent employee sessions')

def current():
    code,page,_,_=client.request(canonical)
    check(code==200,'settings reload')
    check('synthetic-secret-retain' not in page,'stored secret stays masked')
    return settings(page)

def unchanged():
    fields=current()['fields']
    check({k:fields[k] for k in original}==original,'all settings preserved')

submit={'submitpixel_cloudflare_turnstile':'1'}
for target in [canonical,legacy]:
    for invalid in [None,'forged',other_token,['array']]:
        fields=current()['fields']|submit|{'CLOUDFLARE_TURNSTILE_THEME':'dark'}
        if invalid is None: fields.pop('turnstile_form_token')
        elif isinstance(invalid,list):
            fields.pop('turnstile_form_token'); fields['turnstile_form_token[]']='array'
        else: fields['turnstile_form_token']=invalid
        code,body,_,_=client.request(target,fields)
        check(code==200 and 'You cannot save these settings.' in body,'invalid body token refused through native route')
        unchanged()
    fields=current()['fields']|submit|{'CLOUDFLARE_TURNSTILE_THEME':'dark'}
    code,body,_,_=client.request(target,fields)
    if 'Settings updated' not in body: (OUT/'http-failure.html').write_text(body,encoding='utf-8')
    check(code==200 and 'Settings updated' in body,'valid native save with blank secret')
    check(current()['fields']['CLOUDFLARE_TURNSTILE_THEME']=='dark','saved field survives reload')
    code,body,_,_=client.request(target,current()['fields']|original|submit)
    check(code==200 and 'Settings updated' in body,'restore synthetic settings')
    unchanged()
fields=current()['fields']|submit|{'CLOUDFLARE_TURNSTILE_THEME':'dark'}
code,body,_,_=client.request(legacy+'&'+urllib.parse.urlencode(fields,doseq=True))
check(code==200 and 'You cannot save these settings.' in body,'GET cannot save with valid tokens')
unchanged()
for field,value in [('CLOUDFLARE_TURNSTILE_THEME','invalid'),('CLOUDFLARE_TURNSTILE_SITEKEY[]','array'),('CLOUDFLARE_TURNSTILE_SECRET_KEY',' '),('CLOUDFLARE_TURNSTILE_TEST_MODE','unknown')]:
    fields=current()['fields']|submit|{'CLOUDFLARE_TURNSTILE_SITEKEY':'Must not partially save'}
    if field.endswith('[]'): fields.pop(field[:-2])
    fields[field]=value
    code,body,_,_=client.request(legacy,fields)
    check(code==200 and 'Settings updated' not in body,'malformed input refused')
    unchanged()
anonymous=Client()
code,body,_,_=anonymous.request(canonical,current()['fields']|submit)
check('id="login_form"' in body or code in (401,403),'anonymous write refused')
unchanged()
# The real core supplies registration and newsletter hooks; other forms require theme placement.
for path in ['/index.php?controller=registration','/']:
    code,body,_,_=anonymous.request(path)
    check(code==200 and 'cf-turnstile' in body,'native storefront widget renders: '+path)
    check('challenges.cloudflare.com/turnstile/v0/api.js' in body,'provider script registered')
(OUT/'http-settings.json').write_text(json.dumps({'checks':checks,'native_routes':2,'sessions':2,'secret_in_html':False},indent=2),encoding='utf-8')
print(f'{checks} native HTTP settings/storefront checks passed.')
