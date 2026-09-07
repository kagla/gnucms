(() => {
  'use strict';
  const form = document.getElementById('shop-product-form');
  if (!form) return;
  const body = document.getElementById('shop-variant-rows');
  const feedback = document.getElementById('shop-variant-feedback');
  const value = name => form.elements.namedItem(name).value.trim();
  const key = row => JSON.stringify([row.option1, row.option2]);
  const known = new Map(JSON.parse(form.dataset.knownVariants).map(row => [key(row), row]));
  const costsReady = form.dataset.costsReady === '1';
  const labels = {price: '판매가', cost_price: '원가', stock: '재고'};
  const fillTemplate = document.getElementById('shop-fill-down-template');
  const addFillButtons = () => {
    if (!fillTemplate) return;
    for (const tr of body.rows) for (const field of ['price', 'cost_price', 'stock']) {
      const input = tr.querySelector(`input[name$="[${field}]"]`);
      if (!input || input.parentElement.querySelector('[data-fill-down]')) continue;
      const group = document.createElement('div'); group.className = 'row-actions';
      const button = fillTemplate.content.firstElementChild.cloneNode(true);
      const label = labels[field];
      button.dataset.fillDown = field;
      button.disabled = !tr.nextElementSibling;
      button.title = button.disabled ? '아래 옵션이 없습니다' : `현재 ${label}를 아래 모든 옵션에 복사`;
      button.setAttribute('aria-label', `${input.getAttribute('aria-label')}를 아래 모든 옵션에 복사`);
      input.replaceWith(group); group.append(input, button);
    }
  };
  body.addEventListener('click', event => {
    const button = event.target.closest('button[data-fill-down]');
    if (!button || button.disabled) return;
    const row = button.closest('tr');
    const selector = `input[name$="[${button.dataset.fillDown}]"]`;
    const source = row.querySelector(selector);
    if (!source.reportValidity()) return;
    let count = 0;
    for (let next = row.nextElementSibling; next; next = next.nextElementSibling) {
      const target = next.querySelector(selector);
      if (!target) continue;
      target.value = source.value;
      target.dispatchEvent(new Event('input', {bubbles: true}));
      target.dispatchEvent(new Event('change', {bubbles: true}));
      count++;
    }
    const status = document.getElementById('shop-fill-feedback');
    if (status) status.textContent = `${labels[button.dataset.fillDown]}를 아래 ${count}개 옵션에 복사했습니다. 상품 저장을 눌러 반영해 주세요.`;
  });
  const remember = () => {
    for (const tr of body.rows) {
      const row = {};
      for (const input of tr.querySelectorAll('input')) row[input.name.match(/\[([^\]]+)\]$/)[1]] = input.value;
      known.set(key(row), row);
    }
  };
  remember();
  addFillButtons();
  document.getElementById('shop-build-variants').addEventListener('click', event => {
    event.preventDefault();
    try {
      const names = [value('option1_name'), value('option2_name')];
      const values = [1, 2].map(n => {
        const raw = value('option' + n + '_values');
        const parts = raw === '' ? [''] : raw.split(',').map(part => part.trim());
        if (Boolean(names[n - 1]) !== Boolean(raw) || parts.length > 20 || new Set(parts).size !== parts.length || parts.some(part => (raw && !part) || [...part].length > 60)) {
          throw new Error('옵션 이름과 값을 함께 입력하고, 중복값과 빈 값을 제거해 주세요. 각 종류의 값은 20개까지 가능합니다.');
        }
        return parts;
      });
      if ((!names[0] && names[1]) || (names[0] && names[0] === names[1]) || values[0].length * values[1].length > 100) throw new Error('옵션 종류는 최대 두 개, 조합은 최대 100개입니다.');
      for (const name of (costsReady ? ['price', 'cost_price', 'stock'] : ['price', 'stock'])) if (!form.elements.namedItem(name).reportValidity()) return;
      remember();
      const fragment = document.createDocumentFragment();
      let index = 0;
      for (const one of values[0]) for (const two of values[1]) {
        const row = known.get(JSON.stringify([one, two])) || {id: '', version: '', price: value('price'), cost_price: costsReady ? value('cost_price') : '', stock: value('stock')};
        const label = [one, two].filter(part => part !== '').join(' / ') || '기본 상품';
        const tr = document.createElement('tr');
        const option = tr.insertCell(); option.dataset.label = '옵션 조합';
        const content = document.createElement('div'); content.textContent = label; option.append(content);
        for (const [name, val] of Object.entries({id: row.id, version: row.version, option1: one, option2: two})) {
          const input = document.createElement('input'); input.type = 'hidden'; input.name = `variants[${index}][${name}]`; input.value = val; content.append(input);
        }
        for (const [name, title, min, max] of [['price', '판매가 (원)', 1, 100000000], ...(costsReady ? [['cost_price', '원가 (원)', 0, 100000000]] : []), ['stock', '판매 가능 재고 (개)', 0, 1000000]]) {
          const td = tr.insertCell(); td.dataset.label = title;
          const input = document.createElement('input'); input.type = 'number'; input.name = `variants[${index}][${name}]`; input.value = row[name] ?? '';
          if (name === 'cost_price') input.placeholder = '미입력';
          input.min = min; input.max = max; input.required = name !== 'cost_price'; input.className = 'input input-bordered'; input.setAttribute('aria-label', label + ' ' + labels[name]); td.append(input);
        }
        fragment.append(tr); index++;
      }
      body.replaceChildren(fragment);
      addFillButtons();
      const status = document.getElementById('shop-fill-feedback');
      if (status) status.textContent = '';
      feedback.textContent = index + '개 조합을 적용했습니다. 판매가와 재고를 확인한 뒤 상품 저장을 눌러 주세요.';
    } catch (error) { feedback.textContent = error.message; }
  });
})();
