const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const fs = require('node:fs');
const os = require('node:os');
const puppeteer = require(process.env.PUPPETEER_MODULE || 'puppeteer-core');
const root = path.resolve(__dirname, '../..');
const editorAsset = require('./EditorAssets.cjs');
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aGZkAAAAASUVORK5CYII=', 'base64');

(async () => {
  const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'gnucms-image-browser-'));
  const files = ['first.png', 'second.png'].map(name => { const file = path.join(directory, name); fs.writeFileSync(file, png); return file; });
  const browser = await puppeteer.launch({executablePath: process.env.CHROME_BIN || '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage']});
  try {
    for (const base of ['', '/cms']) for (const mobile of [false, true]) for (const scenario of ['images', 'products/new']) {
      const html = execFileSync('php', [path.join(__dirname, 'ShopFixture.php'), scenario, base], {cwd: root, encoding: 'utf8'});
      const page = await browser.newPage(); const errors = [], posts = [];
      await page.setViewport({width: mobile ? 390 : 1280, height: mobile ? 844 : 1000, isMobile: mobile, hasTouch: mobile});
      page.on('pageerror', error => errors.push(error.message));
      await page.setRequestInterception(true);
      page.on('request', request => {
        if (editorAsset(request)) return;
        const pathname = new URL(request.url()).pathname;
        const asset = path.basename(pathname);
        if (['shop-admin.js', 'shop-images.js', 'theme.js', 'shop-admin.css', 'theme.css', 'extensions.css'].includes(asset)) return request.respond({status: 200, contentType: asset.endsWith('.js') ? 'text/javascript' : 'text/css', body: fs.readFileSync(path.join(root, 'www/themes/default', asset), 'utf8')});
        if (asset === 'daisyui.css') return request.respond({status: 200, contentType: 'text/css', body: fs.readFileSync(path.join(root, 'www/vendor/daisyui/daisyui.css'), 'utf8')});
        if (pathname.endsWith('/shop/image')) return request.respond({status: 200, contentType: 'image/png', body: png});
        if (request.method() === 'POST') { posts.push({url: request.url(), body: request.postData(), type: request.headers()['content-type']}); return request.respond({status: 200, contentType: 'text/html', body: html}); }
        return request.respond({status: 200, contentType: 'text/html', body: html});
      });
      const url = 'https://shop.example.test' + base + '/admin/shop/' + (scenario === 'images' ? 'products/edit' : 'products/new');
      await page.goto(url);
      await page.waitForFunction(() => window.CKEDITOR?.instances['shop-description']?.status === 'ready');
      const order = () => page.$$eval('#shop-image-list input', inputs => inputs.map(input => input.value));
      const chooser = await page.$('#shop-image-upload input[type="file"]');
      if (scenario === 'products/new') {
        await page.$eval('input[name="name"]', el => { el.value = '사진 포함 등록'; });
        await chooser.uploadFile(...files);
        await chooser.uploadFile(files[0]);
        assert.equal(await page.$$eval('#shop-image-list li', items => items.length), 3, 'repeated selection appends previews');
      }
      const names = () => page.$eval('#shop-product-form', el => new FormData(el).getAll('images[]').map(file => file.name));
      const original = await order(); assert.equal(original.length, 3);
      // 새 파일의 제출 번호는 정렬에 맞춰 다시 부여된다. DOM 파일 이름으로 이동을 확인한다.
      const identity = () => page.$$eval('#shop-image-list li', items => items.map(item => item.imageFile ? item.imageFile.name : item.dataset.imageId));
      const initial = await identity();
      const select = '[data-image-id="' + original[0] + '"]';
      await page.$eval('#shop-image-list', el => el.scrollIntoView({block: 'center', behavior: 'instant'}));
      if (mobile) await page.waitForFunction(() => document.querySelector('.admin-sidebar').getBoundingClientRect().right <= 1);
      const bounds = await page.$$eval('#shop-image-list li', items => items.map(item => {
        const r = item.getBoundingClientRect(), h = item.querySelector('[data-image-drag]').getBoundingClientRect();
        return {x: r.x + r.width / 2, y: r.y + r.height / 3, hx: h.x + h.width / 2, hy: h.y + h.height / 2};
      }));
      // PC의 마우스와 모바일의 실제 터치 포인터로 첫 이미지를 두 번째 뒤로 옮긴다.
      if (mobile) {
        const client = await page.createCDPSession();
        await client.send('Input.dispatchTouchEvent', {type: 'touchStart', touchPoints: [{x: bounds[0].hx, y: bounds[0].hy}]});
        await client.send('Input.dispatchTouchEvent', {type: 'touchMove', touchPoints: [{x: bounds[1].x, y: bounds[1].y}]});
        await client.send('Input.dispatchTouchEvent', {type: 'touchEnd', touchPoints: []});
        await client.detach();
      } else {
        await page.mouse.move(bounds[0].hx, bounds[0].hy); await page.mouse.down();
        await page.mouse.move(bounds[1].x, bounds[1].y, {steps: 4}); await page.mouse.up();
      }
      assert.deepEqual(await identity(), [initial[1], initial[0], initial[2]], 'drag changes form order');
      assert.equal(posts.length, 0, 'drag waits for explicit order save');
      await page.click('#shop-image-list li:nth-child(2) [data-image-move="1"]');
      assert.deepEqual(await identity(), [initial[1], initial[2], initial[0]]);
      await page.focus('#shop-image-list li:last-child [data-image-drag]'); await page.keyboard.press('ArrowLeft');
      assert.deepEqual(await identity(), [initial[1], initial[0], initial[2]], 'keyboard moves image');
      assert.equal(await page.$eval('#shop-image-list li:first-child [data-image-position]', el => el.textContent), '대표 이미지');
      assert.ok(await page.$eval('#shop-image-list li:first-child [data-image-move="-1"]', el => el.disabled));
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
      await page.emulateMediaFeatures([{name: 'prefers-color-scheme', value: 'dark'}]);
      const colors = await page.$eval('.shop-image-item', el => ({color: getComputedStyle(el).color, background: getComputedStyle(el).backgroundColor}));
      assert.notEqual(colors.color, colors.background);
      await page.emulateMediaFeatures([{name: 'prefers-color-scheme', value: 'light'}]);
      await page.$eval('#product-images', el => el.scrollIntoView({block: 'start', behavior: 'instant'}));
      await page.screenshot({path: '/tmp/gnucms-shop-images-' + (mobile ? 'mobile' : 'desktop') + '.png'});
      if (scenario === 'images') {
        await chooser.uploadFile(...files);
        assert.deepEqual(await names(), ['first.png', 'second.png']);
        await page.focus('#shop-image-list li:last-child [data-image-drag]');
        for (let i = 0; i < 4; i++) await page.keyboard.press('ArrowLeft');
        assert.deepEqual(await names(), ['second.png', 'first.png'], 'new cover order also controls multipart file order');
        assert.deepEqual(await order(), ['new:0', original[1], original[0], original[2], 'new:1']);
        await page.click('#shop-image-list li:last-child [data-image-remove]');
        assert.deepEqual(await names(), ['second.png']);
      } else {
        assert.deepEqual(await names(), ['second.png', 'first.png', 'first.png']);
        await page.click('#shop-image-list li:last-child [data-image-remove]');
        assert.deepEqual(await names(), ['second.png', 'first.png']);
      }
      assert.ok(await page.$eval('#shop-product-form', el => el.checkValidity()), await page.$eval('#shop-image-upload-feedback', el => el.textContent));
      const expectedOrder = await order();
      let submitted;
      await page.exposeFunction('captureProduct', data => { submitted = data; });
      await page.evaluate(() => document.getElementById('shop-product-form').addEventListener('formdata', event => {
        window.captureProduct([...event.formData.entries()].map(([key, value]) => [key, typeof value === 'string' ? value : value.name]));
      }));
      await Promise.all([page.waitForNavigation(), page.click('#shop-product-form button[value="save"]')]);
      assert.equal(posts.length, 1, 'one product submission includes images and order');
      assert.ok(posts[0].type.startsWith('multipart/form-data; boundary='));
      assert.equal(posts[0].url, url);
      const data = new URLSearchParams(submitted);
      assert.equal(data.get('action'), 'save');
      assert.deepEqual(data.getAll('image_order[]'), expectedOrder);
      assert.deepEqual(data.getAll('images[]'), scenario === 'images' ? ['second.png'] : ['second.png', 'first.png']);
      assert.ok(data.get('csrf_token')); assert.ok(data.get('name'));
      assert.deepEqual(errors, []);
      await page.close();
    }
    console.log('Shop images browser checks passed: desktop mouse and mobile touch sorting, keyboard/buttons, multi-upload, unified registration/edit submission, previews, dark mode and subdirectory paths.');
  } finally { await browser.close(); fs.rmSync(directory, {recursive: true, force: true}); }
})().catch(error => { console.error(error); process.exitCode = 1; });
