(() => {
  'use strict';
  const units = ['', '만', '억'];
  const digits = ['', '일', '이', '삼', '사', '오', '육', '칠', '팔', '구'];
  const smallUnits = ['', '십', '백', '천'];
  function koreanWords(value) {
    const n = Number(String(value).replace(/[^0-9]/g, ''));
    if (!n) return '';
    let result = '';
    let group = 0;
    let rest = n;
    while (rest > 0) {
      const part = rest % 10000;
      if (part > 0) {
        let text = '';
        String(part).split('').reverse().forEach((d, i) => { if (d !== '0') text = (d === '1' && i > 0 ? '' : digits[Number(d)]) + smallUnits[i] + text; });
        result = text + units[group] + result;
      }
      rest = Math.floor(rest / 10000);
      group++;
    }
    return result + '원';
  }
  const amount = document.getElementById('amount');
  const words = document.getElementById('initalk-amount-words');
  if (amount && words) {
    const update = () => { words.textContent = koreanWords(amount.value); };
    amount.addEventListener('input', update);
    update();
  }
  const phone = document.getElementById('phone');
  const customer = document.getElementById('initalk-customer');
  const form = document.querySelector('form[data-initalk-new]');
  if (phone && customer && form && phone.dataset.customerUrl) {
    phone.addEventListener('change', async () => {
      const value = phone.value.replace(/[^0-9]/g, '');
      if (value.length < 10) { customer.textContent = ''; return; }
      try {
        const body = new URLSearchParams({ phone: value, csrf_token: form.elements.csrf_token.value });
        const response = await fetch(phone.dataset.customerUrl, { method: 'POST', credentials: 'same-origin', cache: 'no-store',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'Accept': 'application/json' }, body: body.toString() });
        if (!response.ok) throw new Error('request failed');
        const data = await response.json();
        customer.textContent = data.count > 0
          ? '거래횟수 ' + data.count + '회 · 총 거래금액 ' + Number(data.total).toLocaleString('ko-KR') + '원 · 최근거래일 ' + data.last_paid_at
          : '이 번호의 결제 이력이 없습니다.';
      } catch (e) { customer.textContent = '고객 이력을 확인하지 못했습니다.'; }
    });
  }
  document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
      const field = document.getElementById(button.dataset.copy);
      if (!field) return;
      try { await navigator.clipboard.writeText(field.value); button.textContent = '복사됨'; }
      catch (e) { field.select(); button.textContent = '선택됨'; }
    });
  });
  const bulk = document.querySelector('form[data-initalk-bulk]');
  if (bulk) {
    const all = bulk.querySelector('[data-initalk-check-all]');
    if (all) all.addEventListener('change', () => { bulk.querySelectorAll('input[name="ids[]"]').forEach((box) => { box.checked = all.checked; }); });
    bulk.addEventListener('submit', (event) => {
      if (!bulk.querySelector('input[name="ids[]"]:checked')) { event.preventDefault(); window.alert('결제 요청을 먼저 선택해 주세요.'); return; }
      const action = event.submitter && event.submitter.value;
      if (action === 'cancel' && !window.confirm('선택한 결제 요청을 결제 전 취소할까요?')) event.preventDefault();
    });
  }
  document.querySelectorAll('form[data-confirm]').forEach((f) => {
    f.addEventListener('submit', (event) => {
      const message = f.dataset.confirm === 'refund' ? '입력한 금액을 결제사에 환불 요청합니다. 계속할까요?'
        : (f.dataset.confirm === 'refund-close' ? '결제사에 취소 내역이 없음을 확인했습니까? 보류 중인 환불 신청을 종료합니다.' : '이 결제 요청을 결제 전 취소할까요?');
      if (!window.confirm(message)) event.preventDefault();
    });
  });
})();
