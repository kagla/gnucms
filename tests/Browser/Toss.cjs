const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const puppeteer = require(process.env.PUPPETEER_MODULE || 'puppeteer-core');
const root = path.resolve(__dirname, '../..');
const render = scenario => execFileSync('php', [path.join(__dirname, 'ShopFixture.php'), scenario, '/cms'], {cwd: root, encoding: 'utf8'});

(async () => {
  const browser = await puppeteer.launch({executablePath: process.env.CHROME_BIN || '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage']});
  try {
    for (const width of [1280, 390]) {
      const page = await browser.newPage(), errors = [];
      const html = render('toss-payment');
      assert.ok(!html.includes('live_sk_'));
      page.on('pageerror', error => errors.push(error.message));
      await page.setViewport({width, height: 844});
      await page.setRequestInterception(true);
      page.on('request', request => {
        if (request.url() === 'https://js.tosspayments.com/v2/standard') return request.respond({status: 200, contentType: 'text/javascript', body: `window.tossCalls=[];window.TossPayments=clientKey=>({payment:({customerKey})=>({requestPayment:async request=>{window.tossCalls.push({clientKey,customerKey,request});throw {code:'USER_CANCEL'};}})});`});
        return request.respond({status: 200, contentType: 'text/html', body: html});
      });
      await page.goto('https://shop.example.test/cms/modules/shop/order');
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
      await page.click('#shop-pay-button');
      await page.waitForFunction(() => document.getElementById('shop-payment-message').textContent.includes('결제를 취소했습니다'));
      const call = await page.evaluate(() => window.tossCalls[0]);
      assert.match(call.clientKey, /^live_ck_/);
      assert.match(call.customerKey, /^[a-f0-9]{50}$/);
      assert.equal(call.request.method, 'CARD'); assert.deepEqual(call.request.amount, {currency: 'KRW', value: 58000});
      assert.equal(new URL(call.request.successUrl).pathname, '/cms/modules/shop/toss-return');
      assert.equal(new URL(call.request.successUrl).searchParams.get('id'), call.request.orderId);
      assert.match(new URL(call.request.successUrl).searchParams.get('state'), /^[a-f0-9]{64}$/);
      await page.click('#shop-pay-button');
      await page.waitForFunction(() => window.tossCalls.length === 2);
      assert.deepEqual(errors, []); await page.close();
    }
    for (const javascript of [true, false]) {
      const page = await browser.newPage(), posts = [], errors = [];
      let onPost; const posted = new Promise(resolve => { onPost = resolve; });
      const html = render('toss-return');
      await page.setJavaScriptEnabled(javascript);
      page.on('pageerror', error => errors.push(error.message));
      await page.setRequestInterception(true);
      page.on('request', request => {
        if (request.method() === 'POST') {
          posts.push({url: new URL(request.url()), fields: new URLSearchParams(request.postData())});
          onPost();
          return request.respond({status: 200, contentType: 'text/html', body: '<p>결과 확인 완료</p>'});
        }
        return request.respond({status: 200, contentType: 'text/html', body: html});
      });
      await page.goto('https://shop.example.test/cms/modules/shop/toss-return');
      if (!javascript) await Promise.all([page.waitForNavigation(), page.click('button')]);
      await posted;
      assert.equal(posts.length, 1);
      assert.equal(posts[0].url.pathname, '/cms/modules/shop/callback');
      assert.equal(posts[0].fields.get('orderId'), posts[0].url.searchParams.get('id'));
      assert.equal(posts[0].fields.get('amount'), '58000');
      assert.equal(posts[0].fields.get('paymentKey').length, 200);
      assert.match(posts[0].url.searchParams.get('state'), /^[a-f0-9]{64}$/);
      assert.deepEqual(errors, []); await page.close();
    }
    console.log('Toss browser checks passed: desktop/mobile SDK handoff, cancelled popup retry, automatic/manual approval POST.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
