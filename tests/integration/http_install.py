"""Run against a disposable clone and test DB, never an existing configuration."""
from pathlib import Path
import subprocess,json,shutil,socket,time,os,urllib.request,urllib.parse,urllib.error,http.cookiejar,re,html
from html.parser import HTMLParser

class Forms(HTMLParser):
 def __init__(self,body):
  super().__init__();self.forms=[];self.form=None;self.area=None;self.select=None;self.option=None;self.feed(body)
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='form':self.form={'action':a.get('action',''),'data':{}};self.forms.append(self.form)
  if self.form is None:return
  name=a.get('name')
  if tag=='input' and name and 'disabled' not in a:
   typ=a.get('type','text')
   if typ in ['file','submit','button']:return
   if typ in ['checkbox','radio'] and 'checked' not in a:return
   self.form['data'][name]=a.get('value','')
  if tag=='textarea' and name:self.area=name;self.form['data'][name]=''
  if tag=='select' and name:self.select={'name':name,'chosen':False,'count':0}
  if tag=='option' and self.select:
   if 'selected' in a or self.select['count']==0:self.form['data'][self.select['name']]=a.get('value','')
   self.select['count']+=1
 def handle_endtag(self,tag):
  if tag=='form':self.form=None
  elif tag=='textarea':self.area=None
  elif tag=='select':self.select=None
 def handle_data(self,data):
  if self.form is not None and self.area:self.form['data'][self.area]+=data

def run(base,kind):
 client_binary=shutil.which('mariadb') or shutil.which('mysql')
 assert client_binary,'MySQL/MariaDB client is required'
 s=json.loads((base/'setup.json').read_text());db=json.loads(Path(s['dbConfig']).read_text());root=base/('web-'+kind)
 if not root.exists():
  source=Path(s['repos'][kind]['path'])
  if (source/'.git').exists():
   subprocess.run(['git','clone','--quiet','--shared','--no-hardlinks',str(source),str(root)],check=True)
   shutil.copytree(source/'vendor',root/'vendor')
  else:shutil.copytree(source,root)
 assert not (root/'config/config.php').exists(),'Disposable fresh clone required'
 router=root/'router.php';router.write_text('''<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if ($path !== '/' && is_file(__DIR__.'/www'.$path)) return false;
$_SERVER['SCRIPT_NAME']='/index.php';require __DIR__.'/www/index.php';
''')
 sessions=base/('sessions-'+kind);sessions.mkdir(exist_ok=True)
 with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
 origin='http://127.0.0.1:'+str(port)
 serverlog=open(base/(kind+'-http-server.log'),'wb')
 process=subprocess.Popen(['php','-d','sendmail_path=/bin/false','-d','session.save_path='+str(sessions),'-S','127.0.0.1:'+str(port),'-t',str(root/'www'),str(router)],cwd=root,stdout=serverlog,stderr=subprocess.STDOUT)
 checks=[];last=''
 jar=http.cookiejar.CookieJar();client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
 def request(path,data=None,opener=None):
  nonlocal last
  req=urllib.request.Request(urllib.parse.urljoin(origin,path),data=urllib.parse.urlencode(data).encode() if data is not None else None)
  try:response=(opener or client).open(req,timeout=45)
  except urllib.error.HTTPError as e:response=e
  raw=response.read();last=raw.decode(errors='replace');return response.status,response.url,last,raw
 def record(name,condition):
  assert condition,name
  checks.append(name);(base/(kind+'-http-progress.json')).write_text(json.dumps(checks,ensure_ascii=False))
 def submit(body,match,values):
  forms=Forms(body).forms
  f=next(f for f in forms if match(f))
  return request(f['action'],f['data']|values)
 try:
  for i in range(50):
   try:r=request('/install.php');break
   except urllib.error.URLError:time.sleep(.1)
  labels=('8.2' in r[2] and 'MySQL' in r[2]) if kind=='release' else ('PHP 8.2 이상 지원, PHP 8.4 권장' in r[2] and 'MySQL, MariaDB' in r[2])
  record('install requirements: supported PHP and MySQL/MariaDB',r[0]==200 and labels)
  r=request('/install.php?step=1',{});record('installer advances to database', 'step=2' in r[1])
  r=request('/install.php?step=2',{'host':'127.0.0.1','port':str(db['port']),'name':db['databases']['web'],'user':db['username'],'password':db['password'],'prefix':kind+'_'});record('database connection and schema probe', 'step=3' in r[1])
  r=request('/install.php?step=3',{'site_name':'GNUCMS 기능 검증','app_url':origin,'mail_from':'audit@example.test'});record('site configuration', 'step=4' in r[1])
  r=request('/install.php?step=4',{'email':'owner@example.test','display_name':'검증관리자','password':'Audit-Only-20261021!','password_confirmation':'Audit-Only-20261021!'});record('administrator validation','step=5' in r[1])
  r=request('/install.php?step=5',{});record('real web install completes and deletes installer',r[0]==200 and '설치가 끝났습니다' in r[2] and (root/'config/config.php').exists() and not (root/'www/install.php').exists())
  r=request('/login');r=submit(r[2],lambda f:f['action'].endswith('/login'),{'email':'owner@example.test','password':'Audit-Only-20261021!','url':'/admin'})
  r=request('/admin');record('administrator login and dashboard',r[0]==200 and '/login' not in r[1] and '로그아웃' in r[2])
  r=request('/admin/settings/messaging');r=submit(r[2],lambda f:'/messaging/mail' in f['action'],{'mode':'disabled'})
  record('email disabled through settings',r[0]==200 and '메일 설정을 저장했습니다' in r[2])
  r=request('/admin/boards/new');r=submit(r[2],lambda f:f['action'].endswith('/admin/boards/new'),{'board_key':'audit','name':'기능 검증 게시판','description':'전용 테스트 데이터','perm_read':'guest','perm_write':'member','perm_comment':'member'})
  record('administrator creates board',r[0]==200 and '기능 검증 게시판' in r[2] and '/admin/boards' in r[1])
  r=request('/boards/audit/new');r=submit(r[2],lambda f:f['action'].endswith('/boards/audit/new'),{'title':'안내 동작 확인 글','content':'이 글은 실제 HTTP 설치와 작성 기능을 확인하는 전용 테스트입니다.'})
  record('post creation and public rendering',r[0]==200 and '안내 동작 확인 글' in r[2] and re.search(r'/posts/\d+',r[1]) is not None)
  post_url=r[1];post_id=re.search(r'/posts/(\d+)',post_url).group(1)
  r=submit(r[2],lambda f:f['action'].endswith('/comments'),{'content':'실제 댓글 저장 확인입니다.'})
  record('comment creation and rendering',r[0]==200 and '실제 댓글 저장 확인입니다' in r[2])
  guest=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
  r=request('/admin',opener=guest);record('anonymous administrator access blocked','/login' in r[1] or r[0] in [401,403])
  r=request('/shop/checkout',opener=guest);record('anonymous checkout requires membership','/login' in r[1] or r[0] in [401,403] or '로그인' in r[2])
  r=request('/admin/settings/messaging');record('SMTP provider and four phone modes shown',r[0]==200 and all(m in r[2] for m in ['Gmail','카카오','다음','phone_mode']))
  r=request('/admin/settings/maintenance');record('manual restoration guidance is visible',r[0]==200 and '자동 복원하지 않습니다' in r[2])
  f=next(f for f in Forms(r[2]).forms if f['action'].endswith('/admin/backups'))
  r=request(f['action'],f['data']|{'format':'zip'});record('real database and files ZIP backup creation',r[0]==200 and 'backup_created=' in r[1])
  name=urllib.parse.parse_qs(urllib.parse.urlparse(r[1]).query)['backup_created'][0]
  token=next(f['data']['csrf_token'] for f in Forms(r[2]).forms if 'csrf_token' in f['data'])
  r=request('/admin/backups/'+name+'/verify',{'csrf_token':token});record('backup checksum verification',r[0]==200 and 'backup_verified=' in r[1])
  r=request('/admin/backups/'+name);record('backup download',r[0]==200 and r[3].startswith(b'PK'))
  archive=base/(kind+'-'+name);archive.write_bytes(r[3])
  r=request('/admin/backups/'+name+'/restore',{'csrf_token':token,'confirmation':'복원'});record('automatic restore explicitly rejected',r[0] in [200,422] and ('자동 복원' in r[2] or '수동' in r[2]))
  import zipfile
  with zipfile.ZipFile(archive) as z:
   record('backup contains native MySQL SQL and excludes application sources','database/mysql.sql' in z.namelist() and not any(n.startswith('vendor/') for n in z.namelist()))
   sql=z.read('database/mysql.sql')
  defaults=base/('restore-'+kind+'.cnf');defaults.write_text('[client]\nhost=127.0.0.1\nport='+str(db['port'])+'\nuser='+db['username']+'\npassword='+db['password']+'\n');os.chmod(defaults,0o600)
  result=subprocess.run([client_binary,'--defaults-extra-file='+str(defaults),db['databases']['restore']],input=sql,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
  record('manual SQL restore into a separate disposable database',result.returncode==0)
  q="SELECT title FROM "+kind+"_posts WHERE id="+post_id
  result=subprocess.check_output([client_binary,'--defaults-extra-file='+str(defaults),'--batch','--skip-column-names',db['databases']['restore'],'-e',q]).decode()
  record('restored post data matches HTTP-created record','안내 동작 확인 글' in result)
  result={'kind':kind,'status':'passed','checks':checks,'root':str(root),'database':db['databases']['web'],'tablePrefix':kind+'_'}
  (base/(kind+'-http-result.json')).write_text(json.dumps(result,ensure_ascii=False,indent=2));print(json.dumps(result,ensure_ascii=False))
 except BaseException as e:
  (base/(kind+'-http-failure.html')).write_text(last)
  (base/(kind+'-http-result.json')).write_text(json.dumps({'kind':kind,'status':'failed','checks':checks,'error':str(e)},ensure_ascii=False,indent=2));raise
 finally:
  process.terminate();process.wait(timeout=10);serverlog.close()

if __name__=='__main__':
 import tempfile,secrets,sys
 source=Path(__file__).resolve().parents[2]
 dsn=dict(item.split('=',1) for item in os.environ['TEST_MYSQL_DSN'].removeprefix('mysql:').split(';') if '=' in item)
 assert dsn.get('host') in ['127.0.0.1','localhost'] and re.fullmatch(r'gnucms_test(?:_[a-z0-9]+)?',dsn.get('dbname','')),'Only a disposable local test database is allowed'
 client_binary=shutil.which('mariadb') or shutil.which('mysql')
 assert client_binary
 username=os.environ['TEST_MYSQL_USER'];password=os.environ['TEST_MYSQL_PASS']
 suffix=secrets.token_hex(4)
 dbs={'web':'gnucms_test_http_'+suffix,'restore':'gnucms_test_restore_'+suffix}
 with tempfile.TemporaryDirectory(prefix='gnucms-http-install-') as tmp:
  base=Path(tmp)
  defaults=base/'client.cnf';defaults.write_text('[client]\nhost='+dsn['host']+'\nport='+dsn.get('port','3306')+'\nuser='+username+'\npassword='+password+'\n');os.chmod(defaults,0o600)
  def sql(statement):
   subprocess.run([client_binary,'--defaults-extra-file='+str(defaults)],input=statement.encode(),check=True,stdout=subprocess.PIPE)
  try:
   for name in dbs.values():sql('CREATE DATABASE `'+name+'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
   private=base/'test-databases.json';private.write_text(json.dumps({'username':username,'password':password,'port':int(dsn.get('port','3306')),'databases':dbs}));os.chmod(private,0o600)
   (base/'setup.json').write_text(json.dumps({'repos':{'core':{'path':str(source)}},'dbConfig':str(private)}))
   run(base,'core')
  except BaseException:
   for name in ['core-http-result.json','core-http-server.log']:
    p=base/name
    if p.exists():print(p.read_text(errors='replace')[-8000:],file=sys.stderr)
   raise
  finally:
   for name in dbs.values():sql('DROP DATABASE IF EXISTS `'+name+'`;')
