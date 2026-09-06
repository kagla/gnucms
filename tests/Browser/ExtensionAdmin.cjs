// 기존 관리자 화면과 확장 화면을 실제 CSS로 비교한다. 외부 요청은 모두 차단한다.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const puppeteer = require(process.env.PUPPETEER_MODULE || 'puppeteer-core');
const root = path.resolve(__dirname, '../..');
const render = (scenario, base) => execFileSync('php', [path.join(__dirname, 'ExtensionAdminFixture.php'), scenario, base], {cwd: root, encoding: 'utf8'});
const route = scenario => scenario === 'toss-live' ? '/plugins/payment-toss/settings?environment=live'
  : ['inicis', 'kcp', 'kspay', 'toss'].includes(scenario) ? '/plugins/payment-' + scenario + '/settings'
  : scenario === 'bizppurio' ? '/plugins/bizppurio/settings'
  : scenario.startsWith('alimtalk-') ? '/modules/alimtalk/' + scenario.slice(9)
  : scenario.startsWith('core') ? '/admin/settings' : '/' + (scenario === 'demo-message' ? 'plugins' : 'modules') + '/' + scenario + '/preview';

(async () => {
  const browser = await puppeteer.launch({executablePath: process.env.CHROME_BIN || '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage']});
  let count = 0;
  try {
    for (const base of ['', '/cms']) {
      const page = await browser.newPage(); let html = '', posts = [], errors = [];
      await page.setRequestInterception(true);
      page.on('pageerror', error => errors.push(error.message));
      page.on('request', request => {
        if (new URL(request.url()).pathname.endsWith('/extensions.css')) return request.respond({status: 200, contentType: 'text/css', body: fs.readFileSync(path.join(root, 'www/themes/default/extensions.css'), 'utf8')});
        const url = new URL(request.url());
        if (url.pathname.endsWith('/daisyui.css')) return request.respond({status: 200, contentType: 'text/css', body: fs.readFileSync(path.join(root, 'www/vendor/daisyui/daisyui.css'), 'utf8')});
        if (url.pathname.endsWith('/theme.css')) return request.respond({status: 200, contentType: 'text/css', body: fs.readFileSync(path.join(root, 'www/themes/default/theme.css'), 'utf8')});
        if (request.method() === 'POST') { posts.push({url: request.url(), data: new URLSearchParams(request.postData())}); return request.respond({status: 200, body: 'submitted'}); }
        return request.respond({status: 200, contentType: 'text/html', body: html});
      });
      const open = async scenario => { html = render(scenario, base); errors = []; await page.goto('https://gnucms.test' + base + route(scenario)); };
      const style = (selector, props) => page.$eval(selector, (el, props) => Object.fromEntries(props.map(prop => [prop, getComputedStyle(el)[prop]])), props);
      await page.setViewport({width: 1280, height: 960});
      await open('core');
      const body = await style('body', ['fontFamily', 'fontSize', 'color', 'backgroundColor']);
      const input = await style('#main input[type=text]', ['fontFamily', 'fontSize', 'fontWeight', 'height', 'borderRadius']);
      const button = await style('#main button[type=submit]', ['fontFamily', 'fontSize', 'fontWeight', 'height', 'borderRadius']);
      await open('core-list');
      const heading = await style('h1', ['fontSize', 'fontWeight']);
      for (const scenario of ['inicis', 'kcp', 'kspay', 'toss', 'toss-live', 'bizppurio', 'demo-message', 'demo-reservation', 'alimtalk-home', 'alimtalk-templates', 'alimtalk-send', 'alimtalk-history', 'alimtalk-detail']) {
        await page.setViewport({width: 1280, height: 960});
        await open(scenario);
        assert.equal(await page.$$eval('.admin-shell', els => els.length), 1, scenario);
        assert.deepEqual(await style('body', Object.keys(body)), body, scenario + ' body');
        assert.deepEqual(await style('h1', Object.keys(heading)), heading, scenario + ' heading');
        const field = '#main input.input';
        if (await page.$(field)) assert.deepEqual(await style(field, Object.keys(input)), input, scenario + ' input');
        if (await page.$('#main button.btn-primary')) assert.deepEqual(await style('#main button.btn-primary', Object.keys(button)), button, scenario + ' button');
        if (scenario.startsWith('toss')) {
          assert.deepEqual(await style('#payment-client_key', Object.keys(input)), input, scenario + ' client key');
          assert.deepEqual(await style('#payment-secret_key', Object.keys(input)), input, scenario + ' secret key');
          assert.equal(await page.$eval('.settings-tabs a[aria-current=page]', el => new URL(el.href).searchParams.get('environment')), scenario === 'toss-live' ? 'live' : 'test');
          assert.equal(await page.$eval('button[value=enable]', el => el.disabled), false);
        }
        const section = scenario.startsWith('alimtalk-') || scenario === 'demo-reservation' ? 'modules' : 'plugins';
        assert.equal(await page.$eval('.admin-sidebar a[aria-current=page]', el => new URL(el.href).pathname), base + '/admin/' + section);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), scenario + ' desktop width');
        if (base === '/cms' && scenario === 'inicis') await page.screenshot({path: '/tmp/gnucms-extension-admin-desktop.png', fullPage: true});
        if (base === '/cms' && scenario === 'toss') await page.screenshot({path: '/tmp/gnucms-toss-admin-desktop.png', fullPage: true});
        await page.click('[data-theme-toggle]');
        assert.equal(await page.$eval('html', el => el.dataset.themeMode), 'dark');
        const dark = await style('body', ['backgroundColor']);
        assert.notDeepEqual(dark.backgroundColor, body.backgroundColor);
        assert.equal(await page.$$eval('style', els => els.filter(el => /:root|body\s*\{/.test(el.textContent)).length), 0, scenario + ' must not override admin theme');
        await page.setViewport({width: 390, height: 844});
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), scenario + ' mobile width');
        for (const label of await page.$$eval('.table td:not([colspan])', els => els.map(el => el.dataset.label))) assert.ok(label, scenario + ' mobile table label');
        await page.click('.admin-menu-btn');
        assert.equal(await page.$eval('#admin-drawer', el => el.checked), true);
        await page.keyboard.press('Escape');
        assert.equal(await page.$eval('#admin-drawer', el => el.checked), false);
        await page.waitForFunction(() => document.querySelector('.admin-sidebar').getBoundingClientRect().right <= 1);
        if (base === '/cms' && scenario === 'alimtalk-history') await page.screenshot({path: '/tmp/gnucms-extension-admin-mobile.png', fullPage: true});
        if (base === '/cms' && scenario === 'toss') await page.screenshot({path: '/tmp/gnucms-toss-admin-mobile.png', fullPage: true});
        await page.click('[data-theme-toggle]');
        assert.deepEqual(errors, [], scenario);
        count++;
      }
      await open('inicis');
      await page.$eval('button[value=save]', button => button.form.requestSubmit(button));
      await page.waitForFunction(() => document.body.textContent.includes('submitted'));
      assert.equal(posts.at(-1).url, 'https://gnucms.test' + base + '/plugins/payment-inicis/settings');
      assert.equal(posts.at(-1).data.get('action'), 'save');
      assert.equal(posts.at(-1).data.get('merchant_id'), '2999900000');
      assert.equal(posts.at(-1).data.get('csrf_token'), 'browser-test-csrf');
      await open('toss-live');
      await page.$eval('button[value=save]', button => button.form.requestSubmit(button));
      await page.waitForFunction(() => document.body.textContent.includes('submitted'));
      assert.equal(posts.at(-1).url, 'https://gnucms.test' + base + '/plugins/payment-toss/settings');
      assert.equal(posts.at(-1).data.get('action'), 'save');
      assert.equal(posts.at(-1).data.get('environment'), 'live');
      assert.equal(posts.at(-1).data.get('csrf_token'), 'browser-test-csrf');
      assert.equal(posts.at(-1).data.get('secret_key'), '');
      await page.close();
    }
    console.log(`Extension admin browser checks passed: ${count} pages, core typography/controls, light/dark themes, mobile navigation, tables and settings submission.`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
