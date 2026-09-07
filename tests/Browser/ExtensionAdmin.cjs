// 기존 관리자 화면과 확장 화면을 실제 CSS로 비교한다. 외부 요청은 모두 차단한다.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const puppeteer = require(process.env.PUPPETEER_MODULE || 'puppeteer-core');
const root = path.resolve(__dirname, '../..');
const render = (scenario, base) => execFileSync('php', [path.join(__dirname, 'ExtensionAdminFixture.php'), scenario, base], {cwd: root, encoding: 'utf8'});
const route = scenario => scenario === 'core-modules' ? '/admin/modules'
  : scenario === 'core-list' ? '/admin/plugins'
  : scenario === 'bizppurio' ? '/plugins/bizppurio/settings'
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
      for (const scenario of ['core-list', 'core-modules']) {
        await open(scenario);
        for (const width of [1280, 390]) {
          await page.setViewport({width, height: 960});
          const colors = [];
          for (const theme of ['light', 'dark']) {
            await page.mouse.move(0, 0);
            const rows = await page.$$eval('.extensions-table tbody tr', els => els.map(el => getComputedStyle(el).backgroundColor));
            assert.equal(rows.length, 2, scenario + ' populated rows');
            assert.notEqual(rows[0], rows[1], scenario + ' alternating row backgrounds in ' + theme);
            assert.ok(!['transparent', 'rgba(0, 0, 0, 0)'].includes(rows[1]), 'even rows have a visible tint');
            colors.push(rows[1]);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), scenario + ' table width');
            if (base === '/cms') await page.screenshot({path: '/tmp/gnucms-' + scenario + '-zebra-' + width + '-' + theme + '.png', fullPage: true});
            await page.click('[data-theme-toggle]');
          }
          assert.notEqual(colors[0], colors[1], 'row tint follows light and dark themes');
        }
        assert.deepEqual(errors, [], scenario + ' row striping');
        count++;
      }
      await open('core-modules');
      assert.equal(await page.$$eval('.extensions-table .btn-outline', buttons => buttons.length), 0, 'enabled modules use address links without shortcut buttons');
      for (const address of ['/modules/demo-reservation/preview', '/modules/example/preview']) {
        assert.equal(await page.$$eval('.extensions-table a', (links, address) => links.filter(link => link.getAttribute('href') === address).length, base + address), 2, 'module address and new-window icon remain available');
      }
      for (const width of [1280, 390]) {
        await page.setViewport({width, height: 960});
        if (width < 768) await page.waitForFunction(() => document.querySelector('.admin-sidebar').getBoundingClientRect().right <= 1);
        const entry = await page.$eval('.extension-public-entry', el => {
          const address = el.querySelector('.link'), button = el.querySelector('.btn');
          const linkBounds = address.getBoundingClientRect(), iconBounds = button.getBoundingClientRect();
          return {text: el.textContent, address: address.getAttribute('href'), button: button.getAttribute('href'),
            target: button.target, label: button.getAttribute('aria-label'), icon: !!button.querySelector('svg'),
            adjacent: iconBounds.left >= linkBounds.right && iconBounds.top < linkBounds.bottom && iconBounds.bottom > linkBounds.top,
            visible: el.getBoundingClientRect().width > 0};
        });
        assert.equal(entry.address, base + '/book');
        assert.equal(entry.button, base + '/book');
        assert.ok(entry.text.includes('사용자 화면:') && !entry.text.includes('사용자 화면 열기'));
        assert.equal(entry.target, '_blank');
        assert.ok(entry.label.includes('새 창으로 열기') && entry.icon && entry.adjacent, 'new-window icon must sit beside the public address');
        assert.ok(entry.visible);
        assert.ok(await page.$eval('.extension-details', el => el.getBoundingClientRect().width >= 180), 'module details must remain readable');
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        if (base === '/cms') await page.screenshot({path: '/tmp/gnucms-module-links-' + width + '.png', fullPage: true});
      }
      assert.deepEqual(errors, [], 'module user link');
      for (const scenario of ['bizppurio', 'demo-message', 'demo-reservation']) {
        await page.setViewport({width: 1280, height: 960});
        await open(scenario);
        assert.equal(await page.$$eval('.admin-shell', els => els.length), 1, scenario);
        assert.deepEqual(await style('body', Object.keys(body)), body, scenario + ' body');
        assert.deepEqual(await style('h1', Object.keys(heading)), heading, scenario + ' heading');
        const field = '#main input.input';
        if (await page.$(field)) assert.deepEqual(await style(field, Object.keys(input)), input, scenario + ' input');
        if (await page.$('#main button.btn-primary')) assert.deepEqual(await style('#main button.btn-primary', Object.keys(button)), button, scenario + ' button');
        const section = scenario === 'demo-reservation' ? 'modules' : 'plugins';
        assert.equal(await page.$eval('.admin-sidebar a[aria-current=page]', el => new URL(el.href).pathname), base + '/admin/' + section);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), scenario + ' desktop width');
        if (base === '/cms' && scenario === 'bizppurio') await page.screenshot({path: '/tmp/gnucms-extension-admin-desktop.png', fullPage: true});
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
        if (base === '/cms' && scenario === 'demo-reservation') await page.screenshot({path: '/tmp/gnucms-extension-admin-mobile.png', fullPage: true});
        await page.click('[data-theme-toggle]');
        assert.deepEqual(errors, [], scenario);
        count++;
      }
      await open('bizppurio');
      await page.$eval('button[value=save]', button => button.form.requestSubmit(button));
      await page.waitForFunction(() => document.body.textContent.includes('submitted'));
      assert.equal(posts.at(-1).url, 'https://gnucms.test' + base + '/plugins/bizppurio/settings');
      assert.equal(posts.at(-1).data.get('action'), 'save');
      assert.equal(posts.at(-1).data.get('account'), 'browser-test');
      assert.equal(posts.at(-1).data.get('csrf_token'), 'browser-test-csrf');
      assert.equal(posts.at(-1).data.get('environment'), 'test');
      assert.equal(posts.at(-1).data.get('password'), '');
      await page.close();
    }
    console.log(`Extension admin browser checks passed: ${count} pages, core typography/controls, light/dark themes, mobile navigation, tables and settings submission.`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
