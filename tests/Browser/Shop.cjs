const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const fs = require('node:fs');
const puppeteer = require(process.env.PUPPETEER_MODULE || 'puppeteer-core');
const root = path.resolve(__dirname, '../..');
const editorAsset = require('./EditorAssets.cjs');
const render = scenario => execFileSync('php', [path.join(__dirname, 'ShopFixture.php'), scenario, '/cms'], {cwd: root, encoding: 'utf8'});

(async () => {
  const browser = await puppeteer.launch({executablePath: process.env.CHROME_BIN || '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage']});
  try {
    for (const scenario of ['catalog', 'product', 'cart', 'checkout', 'orders', 'order', 'admin', 'products', 'products/new', 'products/edit', 'manage-order', 'settings', 'inventory', 'settlement', 'payment']) {
      const page = await browser.newPage(); const errors = [], posts = [];
      const html = render(scenario);
      page.on('pageerror', error => errors.push(error.message));
      await page.setRequestInterception(true);
      page.on('request', request => {
        if (editorAsset(request)) return;
        if (new URL(request.url()).pathname.endsWith('/shop-gallery.js')) return request.respond({status: 200, contentType: 'text/javascript', body: fs.readFileSync(path.join(root, 'www/themes/default/shop-gallery.js'), 'utf8')});
        if (new URL(request.url()).pathname.endsWith('/shop-images.js')) return request.respond({status: 200, contentType: 'text/javascript', body: fs.readFileSync(path.join(root, 'www/themes/default/shop-images.js'), 'utf8')});
        if (new URL(request.url()).pathname.endsWith('/shop-admin.css')) return request.respond({status: 200, contentType: 'text/css', body: fs.readFileSync(path.join(root, 'www/themes/default/shop-admin.css'), 'utf8')});
        if (new URL(request.url()).pathname.endsWith('/shop/image')) return request.respond({status: 200, contentType: 'image/png', body: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aGZkAAAAASUVORK5CYII=', 'base64')});
        if (new URL(request.url()).pathname.endsWith('/shop-admin.js')) return request.respond({status: 200, contentType: 'text/javascript', body: fs.readFileSync(path.join(root, 'www/themes/default/shop-admin.js'), 'utf8')});
        if (new URL(request.url()).pathname.endsWith('/shop.css')) return request.respond({status: 200, contentType: 'text/css', body: fs.readFileSync(path.join(root, 'www/themes/default/shop.css'), 'utf8')});
        if (new URL(request.url()).pathname.endsWith('/theme.js')) return request.respond({status: 200, contentType: 'text/javascript', body: fs.readFileSync(path.join(root, 'www/themes/default/theme.js'), 'utf8')});
        if (new URL(request.url()).pathname.endsWith('/extensions.css')) return request.respond({status: 200, contentType: 'text/css', body: fs.readFileSync(path.join(root, 'www/themes/default/extensions.css'), 'utf8')});
        if (request.url().includes('daisyui.css')) return request.respond({status: 200, contentType: 'text/css', body: fs.readFileSync(path.join(root, 'www/vendor/daisyui/daisyui.css'), 'utf8')});
        if (new URL(request.url()).pathname.endsWith('/theme.css')) return request.respond({status: 200, contentType: 'text/css', body: fs.readFileSync(path.join(root, 'www/themes/default/theme.css'), 'utf8')});
        if (request.url() === 'https://stgstdpay.inicis.com/stdjs/INIStdPay.js') return request.respond({status: 200, contentType: 'text/javascript', body: 'window.INIStdPay={pay:function(id){window.testPayment=Object.fromEntries(new FormData(document.getElementById(id)));}};'});
        if (request.method() === 'POST') { posts.push({url: request.url(), data: new URLSearchParams(request.postData()), type: request.headers()['content-type']}); return request.respond({status: 200, body: 'submitted'}); }
        return request.respond({status: 200, contentType: 'text/html', body: html});
      });
      await page.setViewport({width: 1280, height: 960});
      const adminScreen = ['admin', 'products', 'products/new', 'products/edit', 'manage-order', 'settings', 'inventory', 'settlement'].includes(scenario);
      await page.goto('https://shop.example.test/cms' + (adminScreen ? '/admin/shop' + (scenario === 'admin' ? '' : '/' + scenario) : '/shop/' + (scenario === 'payment' ? 'order' : scenario)));
      assert.ok((await page.$eval('h1', el => el.textContent)).length > 0, scenario);
      assert.deepEqual(errors, [], scenario);
      assert.equal(await page.$$eval('.admin-shell', els => els.length), adminScreen ? 1 : 0, scenario + ' admin layout');
      assert.equal(await page.$$eval('.shop-header', els => els.length), adminScreen ? 0 : 1, scenario + ' storefront layout');
      if (!adminScreen) assert.equal(await page.$$eval('.notice.alert, .banner.alert', els => els.length), 0, scenario + ' storefront notices keep their own style');
      if (adminScreen) {
        const weights = await page.$$eval('#main .extension-label > .input', els => els.map(el => getComputedStyle(el).fontWeight));
        assert.ok(weights.every(weight => weight === '400'), scenario + ' field text must match core forms');
      }
      if (scenario === 'products') {
        assert.equal(await page.$('#shop-product-form'), null);
        assert.ok(await page.$('a[href="/cms/admin/shop/products/new"]'));
        const options = await page.$$eval('.shop-product-options:first-of-type [title]', nodes => nodes.map(node => ({name: node.textContent, title: node.title})));
        assert.ok(options.some(option => option.name === '색상' && option.title.includes('그린') && option.title.includes('크림')));
        assert.ok(options.some(option => option.name === '사이즈' && option.title.includes('L') && option.title.includes('M')));
        await page.hover('.shop-product-options [title]');
        await page.screenshot({path: '/tmp/gnucms-shop-options-desktop.png', fullPage: true});
      }
      if (scenario === 'products/new' || scenario === 'products/edit') {
        await page.waitForFunction(() => window.CKEDITOR?.instances['shop-description']?.status === 'ready');
        const description = scenario === 'products/new' ? '' : '<h2>상품 안내</h2><p><strong>면 소재</strong></p><ul><li>세탁 안내</li></ul>';
        await page.evaluate(description => new Promise(resolve => CKEDITOR.instances['shop-description'].setData(description, resolve)), description);
        assert.ok(await page.$('#cke_shop-description .cke_button__gnucmsimages'), 'existing image upload toolbar is available');
        assert.equal(await page.$('input[name="q"]'), null);
        const lastDisabled = await page.$$eval('#shop-variant-rows tr:last-child button[data-fill-down]', buttons => buttons.every(button => button.disabled));
        assert.ok(lastDisabled, 'last option has no rows to fill');
        if (scenario === 'products/edit') {
          await page.$eval('input[name="variants[0][price]"]', el => { el.value = '18500'; });
          await page.click('#shop-variant-rows tr:first-child button[data-fill-down="price"]');
          assert.ok(await page.$$eval('#shop-variant-rows input[name$="[price]"]', inputs => inputs.every(input => input.value === '18500')), 'saved options support filling down');
        }
        await page.$eval('input[name="name"]', el => { el.value = '브라우저 옵션 상품'; });
        if (scenario === 'products/new') {
          await page.$eval('input[name="option1_name"]', el => { el.value = '색상'; });
          await page.$eval('input[name="option1_values"]', el => { el.value = '검정,흰색'; });
          await page.$eval('input[name="price"]', el => { el.value = '12000'; });
          await page.$eval('input[name="stock"]', el => { el.value = '3'; });
          await page.click('#shop-build-variants');
          assert.equal(await page.$$eval('#shop-variant-rows tr', els => els.length), 2);
        }
        await page.$eval('input[name="variants[0][cost_price]"]', el => { el.value = '7654'; });
        await page.click('#shop-variant-rows tr:first-child button[data-fill-down="cost_price"]');
        assert.ok(await page.$$eval('#shop-variant-rows input[name$="[cost_price]"]', inputs => inputs.every(input => input.value === '7654')), 'cost copy applies only to later costs');
        await page.$eval('input[name="variants[0][price]"]', el => { el.value = '21000'; });
        await page.$eval('input[name="variants[0][stock]"]', el => { el.value = '9'; });
        await page.$eval('input[name="option1_values"]', el => { el.value += ',새 색상'; });
        await page.click('#shop-build-variants');
        const firstPrice = await page.$eval('input[name="variants[0][price]"]', el => el.value);
        assert.equal(firstPrice, '21000', 'rebuilding must preserve entered prices');
        assert.equal(await page.$eval('input[name="variants[0][cost_price]"]', el => el.value), '7654', 'rebuilding preserves existing costs');
        assert.equal(await page.$eval('input[name="variants[0][stock]"]', el => el.value), '9');
        if (scenario === 'products/edit') assert.ok(await page.$eval('input[name="variants[0][id]"]', el => el.value));
        const stocksBefore = await page.$$eval('#shop-variant-rows input[name$="[stock]"]', inputs => inputs.map(input => input.value));
        await page.$eval('input[name="variants[1][price]"]', el => { el.value = '33000'; });
        await page.click('#shop-variant-rows tr:nth-child(2) button[data-fill-down="price"]');
        const prices = await page.$$eval('#shop-variant-rows input[name$="[price]"]', inputs => inputs.map(input => input.value));
        assert.equal(prices[0], '21000', 'filling from the middle preserves earlier options');
        assert.ok(prices.slice(1).every(price => price === '33000'));
        assert.deepEqual(await page.$$eval('#shop-variant-rows input[name$="[stock]"]', inputs => inputs.map(input => input.value)), stocksBefore, 'price fill preserves stocks');
        for (const invalid of ['', '0', '-1', '1.5', '100000001']) {
          await page.$eval('input[name="variants[1][price]"]', (el, value) => { el.value = value; }, invalid);
          await page.click('#shop-variant-rows tr:nth-child(2) button[data-fill-down="price"]');
          assert.ok(await page.$$eval('#shop-variant-rows input[name$="[price]"]', inputs => inputs.slice(2).every(input => input.value === '33000')), 'invalid prices must not propagate');
        }
        await page.$eval('input[name="variants[1][price]"]', el => { el.value = '33000'; });
        await page.$eval('input[name="variants[1][stock]"]', el => { el.value = '0'; });
        await page.click('#shop-variant-rows tr:nth-child(2) button[data-fill-down="stock"]');
        const stocks = await page.$$eval('#shop-variant-rows input[name$="[stock]"]', inputs => inputs.map(input => input.value));
        assert.equal(stocks[0], '9');
        assert.ok(stocks.slice(1).every(stock => stock === '0'), 'zero stock can be filled down');
        assert.deepEqual(await page.$$eval('#shop-variant-rows input[name$="[price]"]', inputs => inputs.map(input => input.value)), prices, 'stock fill preserves prices');
        await page.click('#shop-variant-rows tr:first-child button[data-fill-down="price"]');
        await page.click('#shop-build-variants');
        assert.ok(await page.$$eval('#shop-variant-rows input[name$="[price]"]', inputs => inputs.every(input => input.value === '21000')), 'filled prices survive regeneration');
        assert.deepEqual(await page.$$eval('#shop-variant-rows input[name$="[stock]"]', inputs => inputs.map(input => input.value)), stocks, 'filled stocks survive regeneration');
        assert.equal(posts.length, 0, 'building options must not save product');
        assert.equal(await page.evaluate(() => CKEDITOR.instances['shop-description'].getData().includes('<strong>면 소재</strong>')), scenario === 'products/edit', 'option generation preserves editor contents');
        await page.evaluate(() => window.scrollTo({top: 0, behavior: 'instant'}));
        await page.screenshot({path: '/tmp/gnucms-shop-product-desktop.png', fullPage: true});
      }
      if (scenario === 'settlement') {
        assert.ok(await page.$('[data-shop-profit]'));
        assert.ok((await page.$eval('[data-shop-profit]', el => el.textContent)).includes('상품 이익'));
        assert.ok((await page.$eval('[data-shop-profit]', el => el.textContent)).includes('24,000'));
        assert.equal(await page.$$eval('[data-shop-profit] tbody tr', rows => rows.length), 1);
        await page.screenshot({path: '/tmp/gnucms-shop-profit-desktop.png', fullPage: true});
      }
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
        assert.ok(actual.returnUrl.includes('/cms/shop/callback?id='));
        assert.equal(posts.length, 0);
      } else {
        if (!adminScreen) {
          assert.equal(await page.$$eval('.site-header', els => els.length), 1, scenario + ' site navigation');
          assert.equal(await page.$$eval('.site-header a[href$="/shop"]', els => els.length), 1);
          assert.equal(await page.$$eval('.shop-header a[href$="/admin"]', els => els.length), 0, 'customer has no admin link');
          await page.emulateMediaFeatures([{name: 'prefers-color-scheme', value: 'dark'}]);
          await page.evaluate(() => { document.documentElement.dataset.theme = 'dark'; document.documentElement.dataset.themeMode = 'dark'; });
          const colors = await page.$eval('.shop-storefront .panel, .shop-storefront .product-card', el => ({color: getComputedStyle(el).color, background: getComputedStyle(el).backgroundColor}));
          assert.notEqual(colors.color, colors.background, scenario + ' dark text visible');
          assert.notEqual(colors.background, 'rgb(255, 255, 255)', scenario + ' dark surface');
          await page.emulateMediaFeatures([{name: 'prefers-color-scheme', value: 'light'}]);
          await page.evaluate(() => { document.documentElement.dataset.theme = 'light'; document.documentElement.dataset.themeMode = 'light'; });
        }
        if (scenario === 'catalog') {
          await page.waitForFunction(() => getComputedStyle(document.body).backgroundColor === 'rgb(246, 247, 251)');
          await page.screenshot({path: '/tmp/gnucms-shop-desktop.png', fullPage: true});
        }
        await page.setViewport({width: 390, height: 844});
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), scenario + ' must fit mobile width');
        if (scenario === 'products') {
          await page.waitForFunction(() => document.querySelector('.admin-sidebar').getBoundingClientRect().right <= 1);
          await page.screenshot({path: '/tmp/gnucms-shop-options-mobile.png', fullPage: true});
        }
        if (scenario === 'products/new' || scenario === 'products/edit') {
          await page.waitForFunction(() => document.querySelector('.admin-sidebar').getBoundingClientRect().right <= 1);
          await page.click('#shop-variant-rows tr:first-child button[data-fill-down="stock"]');
          assert.ok(await page.$$eval('#shop-variant-rows input[name$="[stock]"]', inputs => inputs.every(input => input.value === '9')), 'fill down works on mobile');
          assert.equal(posts.length, 0, 'fill down waits for explicit product save');
          await page.evaluate(() => window.scrollTo({top: 0, behavior: 'instant'}));
          await page.screenshot({path: '/tmp/gnucms-shop-product-mobile.png', fullPage: true});
          let submitted;
          await page.exposeFunction('captureProduct', entries => { submitted = new URLSearchParams(entries); });
          await page.evaluate(() => document.getElementById('shop-product-form').addEventListener('formdata', event => {
            window.captureProduct([...event.formData.entries()].filter(([, value]) => typeof value === 'string'));
          }));
          await Promise.all([page.waitForNavigation(), page.click('#shop-product-form button[value="save"]')]);
          assert.ok(posts[0].type.startsWith('multipart/form-data; boundary='));
          posts[0].data = submitted;
          assert.equal(posts[0].url, 'https://shop.example.test/cms/admin/shop/' + scenario);
          assert.equal(posts[0].data.get('variants[0][cost_price]'), '7654');
          assert.equal(posts[0].data.get('variants[0][price]'), '21000');
          assert.equal(posts[0].data.get('variants[0][stock]'), '9');
          assert.equal(posts[0].data.get('variants[1][price]'), '21000');
          assert.equal(posts[0].data.get('variants[1][stock]'), '9');
          assert.ok(posts[0].data.get('csrf_token'));
          assert.ok(posts[0].data.get('image_key'));
          if (scenario === 'products/edit') assert.ok(posts[0].data.get('description').includes('<strong>면 소재</strong>'));
          else assert.equal(posts[0].data.get('description').trim(), '', 'description remains optional');
        }
        if (scenario === 'settlement') await page.screenshot({path: '/tmp/gnucms-shop-profit-mobile.png', fullPage: true});
        if (scenario === 'orders') await page.screenshot({path: '/tmp/gnucms-shop-customer-mobile.png', fullPage: true});
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
          const lastImage = await page.$eval('[data-product-thumb]:last-child', el => el.href);
          await page.click('[data-product-thumb]:last-child');
          assert.equal(await page.$eval('#shop-product-image', el => el.src), lastImage);
          assert.equal(await page.$eval('#shop-product-image-link', el => el.href), lastImage);
          await Promise.all([page.waitForNavigation(), page.click('.shop-storefront button')]);
          assert.equal(posts[0].data.get('action'), 'add');
          assert.ok(posts[0].data.get('variant_id'));
        }
      }
      await page.close();
    }
    console.log('Shop browser checks passed: 15 pages, site navigation, dark mode, mobile width, option generation and submission, cart submission, PG popup handoff and CSRF.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
