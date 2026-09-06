const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const puppeteer = require(process.env.PUPPETEER_MODULE || 'puppeteer-core');
const root = path.resolve(__dirname, '../..');
const render = scenario => execFileSync('php', [path.join(__dirname, 'ShopFixture.php'), scenario, '/cms'], {cwd: root, encoding: 'utf8'});

(async () => {
  const browser = await puppeteer.launch({executablePath: process.env.CHROME_BIN || '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage']});
  try {
    for (const scenario of ['catalog', 'product', 'cart', 'checkout', 'admin', 'products', 'manage-order', 'settings', 'inventory', 'settlement', 'payment']) {
      const page = await browser.newPage(); const errors = [], posts = [];
      const html = render(scenario);
      page.on('pageerror', error => errors.push(error.message));
      await page.setRequestInterception(true);
      page.on('request', request => {
        if (request.url() === 'https://stgstdpay.inicis.com/stdjs/INIStdPay.js') return request.respond({status: 200, contentType: 'text/javascript', body: 'window.INIStdPay={pay:function(id){window.testPayment=Object.fromEntries(new FormData(document.getElementById(id)));}};'});
        if (request.method() === 'POST') { posts.push({url: request.url(), data: new URLSearchParams(request.postData())}); return request.respond({status: 200, body: 'submitted'}); }
        return request.respond({status: 200, contentType: 'text/html', body: html});
      });
      await page.setViewport({width: 1280, height: 960});
      await page.goto('https://shop.example.test/cms/modules/shop/' + (scenario === 'payment' ? 'order' : scenario));
      assert.ok((await page.$eval('h1', el => el.textContent)).length > 0, scenario);
      assert.deepEqual(errors, [], scenario);
      if (scenario === 'manage-order') {
        const claims = await page.$$eval('article.claim', rows => rows.map(row => row.textContent));
        assert.equal(claims.length, 2);
        assert.ok(claims.every(row => row.includes('신청 상품') && row.includes('매일 입는 코튼 셔츠')));
        assert.ok(claims.some(row => row.includes('교환받을 옵션')));
      }
      if (scenario === 'payment') {
        const expected = await page.$eval('#shop-payment-form', el => Object.fromEntries(new FormData(el)));
        await page.click('#shop-pay-button');
        const actual = await page.evaluate(() => window.testPayment);
        assert.equal(actual.oid, expected.oid);
        assert.equal(actual.price, expected.price);
        assert.ok(actual.returnUrl.includes('/cms/modules/shop/callback?id='));
        assert.equal(posts.length, 0);
      } else {
        if (scenario === 'catalog') await page.screenshot({path: '/tmp/gnucms-shop-desktop.png', fullPage: true});
        await page.setViewport({width: 390, height: 844});
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), scenario + ' must fit mobile width');
        if (scenario === 'manage-order') {
          await page.screenshot({path: '/tmp/gnucms-shop-mobile.png', fullPage: true});
          await page.$eval('button[value="convert-return"]', button => {
            button.closest('details').open = true;
            button.form.querySelector('textarea[name="note"]').value = '고객과 반품 전환 확인';
          });
          await Promise.all([page.waitForNavigation(), page.click('button[value="convert-return"]')]);
          assert.equal(posts[0].data.get('action'), 'claim-action');
          assert.equal(posts[0].data.get('claim_action'), 'convert-return');
          assert.ok(posts[0].data.get('claim_id'));
          assert.ok(posts[0].data.get('csrf_token'));
        }
        if (scenario === 'product') {
          await Promise.all([page.waitForNavigation(), page.click('button')]);
          assert.equal(posts[0].data.get('action'), 'add');
          assert.ok(posts[0].data.get('variant_id'));
        }
      }
      await page.close();
    }
    console.log('Shop browser checks passed: 11 pages, mobile width, cart submission, PG popup handoff and CSRF.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
