// 개발 환경 전용: PUPPETEER_MODULE과 CHROME_BIN으로 설치된 puppeteer-core/Chrome 경로를 지정할 수 있다.
// 실제 계정이나 서버에 접근하지 않고, 렌더링된 설정 HTML의 클릭과 요청을 브라우저에서 검증한다.
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const puppeteer = require(process.env.PUPPETEER_MODULE || 'puppeteer-core');
const root = path.resolve(__dirname, '../..');

function html(base, environment) {
  return execFileSync('php', ['-r', String.raw`
    require 'vendor/autoload.php';
    $base = $argv[1]; $environment = $argv[2];
    $slim = \Slim\Factory\AppFactory::create();
    $view = new \GnuCms\View\PhpView(['plugins/bizppurio/templates'], $slim->getRouteCollector()->getRouteParser(), $base, static fn ($p) => '', static fn ($s) => $s);
    echo $view->fetch('settings', ['base' => $base, 'environment' => $environment, 'ready' => true,
      'settings' => ['configured' => true, 'enabled' => false, 'account' => 'browser-test', 'revision' => str_repeat('a', 32), 'senderkey' => bin2hex(random_bytes(20))],
      'notice' => '', 'errors' => [], 'webhook' => null, 'csrf_token' => 'browser-test-csrf']);
  `, base, environment], {cwd: root, encoding: 'utf8'});
}

(async () => {
  const browser = await puppeteer.launch({executablePath: process.env.CHROME_BIN || '/usr/bin/google-chrome', headless: true,
    args: ['--no-sandbox', '--disable-dev-shm-usage']});
  try {
    for (const [base, environment] of [['', 'test'], ['/cms', 'live']]) {
      const page = await browser.newPage();
      const url = 'https://gnucms.test' + base + '/plugins/bizppurio/settings';
      const content = html(base, environment), requests = [], errors = [];
      const password = require('node:crypto').randomUUID();
      page.on('pageerror', error => errors.push(error.message));
      await page.setRequestInterception(true);
      page.on('request', request => {
        if (request.method() === 'POST') {
          requests.push({url: request.url(), body: new URLSearchParams(request.postData())});
          return request.respond(request.url() === url
            ? {status: 200, contentType: 'application/json', body: JSON.stringify({password})}
            : {status: 404, body: 'not found'});
        }
        return request.respond({status: 200, contentType: 'text/html', body: content});
      });
      await page.goto(url + '?environment=' + environment);
      const senderKey = await page.$eval('#senderkey', element => element.value);
      assert.equal(await page.$eval('#senderkey', element => element.type), 'password');
      await page.click('#senderkey-toggle');
      assert.equal(await page.$eval('#senderkey', element => element.type), 'text');
      assert.equal(await page.$eval('#senderkey', element => element.value), senderKey);
      assert.equal(await page.$eval('#senderkey-toggle', element => element.getAttribute('aria-label')), '발신프로필 키 숨기기');
      await page.click('#senderkey-toggle');
      assert.equal(await page.$eval('#senderkey', element => element.type), 'password');
      assert.equal(await page.$eval('#senderkey', element => new FormData(element.form).get('senderkey')), senderKey);
      assert.equal(requests.length, 0, '발신프로필 키 표시는 저장이나 서버 요청을 실행하지 않는다');
      assert.equal(await page.$eval('#password', element => element.value), '');
      await page.click('#password-toggle');
      await page.waitForFunction(() => !document.getElementById('password-toggle').disabled);
      assert.equal(requests.length, 1);
      assert.equal(requests[0].url, url, '이름이 action인 저장 버튼과 관계없이 폼 URL로 요청해야 한다');
      for (const [key, value] of Object.entries({action: 'reveal-password', environment, account: 'browser-test', revision: 'a'.repeat(32), csrf_token: 'browser-test-csrf'})) {
        assert.equal(requests[0].body.get(key), value);
      }
      assert.equal(await page.$eval('#password', element => element.type), 'text');
      assert.equal(await page.$eval('#password', element => element.value), password);
      await page.click('#password-toggle');
      assert.equal(await page.$eval('#password', element => element.type), 'password');
      assert.equal(await page.$eval('#password', element => element.value), '');
      await page.type('#password', 'newly-typed-value');
      await page.click('#password-toggle');
      assert.equal(await page.$eval('#password', element => element.type), 'text');
      await page.click('#password-toggle');
      assert.equal(await page.$eval('#password', element => element.value), 'newly-typed-value');
      assert.equal(requests.length, 1, '새로 입력한 값은 서버에서 다시 읽지 않는다');
      assert.deepEqual(errors, []);
      await page.close();
    }
    console.log('Alimtalk password toggle browser regression passed (root/test, subdirectory/live).');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
