(() => {
  'use strict';
  const upload = document.getElementById('shop-image-upload');
  const input = upload?.querySelector('input[type="file"]');
  let check = () => true;
  if (upload) {
    const feedback = document.getElementById('shop-image-upload-feedback');
    check = () => {
      const files = [...input.files];
      let message = '';
      if (files.length + Number(upload.dataset.imageCount) > 20) message = '상품 이미지는 기존 이미지를 포함해 최대 20장까지 등록할 수 있습니다.';
      else if (files.length > Number(upload.dataset.maxFiles)) message = '한 번에 업로드할 수 있는 개수를 초과했습니다. 나누어 업로드해 주세요.';
      else if (files.some(file => file.size > 5242880 || (Number(upload.dataset.fileLimit) > 0 && file.size > Number(upload.dataset.fileLimit)))) message = '파일 크기 한도를 초과한 이미지가 있습니다. 더 작은 이미지를 선택해 주세요.';
      else if (Number(upload.dataset.postLimit) > 0 && files.reduce((sum, file) => sum + file.size, 0) + 65536 > Number(upload.dataset.postLimit)) message = '선택한 이미지의 합계 용량이 한 번의 업로드 한도를 초과합니다. 나누어 업로드해 주세요.';
      input.setCustomValidity(message);
      feedback.textContent = message || (files.length ? files.length + '장의 이미지를 선택했습니다.' : '');
      return !message;
    };
    upload.closest('form').addEventListener('submit', event => {
      if (!check()) { event.preventDefault(); input.reportValidity(); }
    }, {capture: true});
  }
  const list = document.getElementById('shop-image-list');
  if (!list) return;
  const feedback = document.getElementById('shop-image-order-feedback');
  let dragged = null, original = [], pointer = null;
  const items = () => [...list.children];
  const update = (announce = false) => {
    const transfer = new DataTransfer();
    items().forEach((item, index, all) => {
      if (item.imageFile) {
        item.dataset.imageId = 'new:' + transfer.files.length;
        item.querySelector('input').value = item.dataset.imageId;
        transfer.items.add(item.imageFile);
      }
      item.draggable = true;
      item.querySelector('[data-image-position]').textContent = index === 0 ? '대표 이미지' : (index + 1) + '번 이미지';
      item.querySelector('[data-image-drag]').setAttribute('aria-label', '이미지 ' + (index + 1) + ' 순서 이동');
      for (const button of item.querySelectorAll('button')) button.hidden = false;
      for (const button of item.querySelectorAll('[data-image-move]')) button.disabled = Number(button.dataset.imageMove) < 0 ? index === 0 : index === all.length - 1;
    });
    input.files = transfer.files;
    check();
    if (announce) feedback.textContent = '이미지 순서를 변경했습니다. 첫 번째 이미지가 대표 이미지입니다. 상품 저장을 눌러 반영해 주세요.';
  };
  const move = (item, direction) => {
    const sibling = direction < 0 ? item.previousElementSibling : item.nextElementSibling;
    if (!sibling) return;
    direction < 0 ? sibling.before(item) : sibling.after(item);
    update(true);
  };
  const start = item => {
    dragged = item; original = items();
    item.classList.add('is-dragging');
  };
  const over = target => {
    const item = target?.closest('.shop-image-item');
    if (!dragged || !item || item === dragged || item.parentElement !== list) return;
    const order = items();
    order.indexOf(dragged) < order.indexOf(item) ? item.after(dragged) : item.before(dragged);
    update();
  };
  const finish = (cancel = false) => {
    if (!dragged) return;
    if (cancel) for (const item of original) list.append(item);
    dragged.classList.remove('is-dragging');
    const changed = original.some((item, index) => item !== list.children[index]);
    dragged = null; pointer = null; original = [];
    update(changed);
  };
  input.addEventListener('change', () => {
    for (const file of [...input.files]) {
      const item = document.getElementById('shop-new-image-template').content.firstElementChild.cloneNode(true);
      item.imageFile = file;
      item.querySelector('img').src = URL.createObjectURL(file);
      item.querySelector('img').alt = file.name;
      list.append(item);
    }
    update();
  });
  list.addEventListener('click', event => {
    const remove = event.target.closest('[data-image-remove]');
    if (remove) {
      const item = remove.closest('.shop-image-item');
      URL.revokeObjectURL(item.querySelector('img').src);
      item.remove(); update(true); return;
    }
    const button = event.target.closest('[data-image-move]');
    if (!button) return;
    const item = button.closest('.shop-image-item');
    move(item, Number(button.dataset.imageMove));
    item.querySelector('[data-image-drag]').focus({preventScroll: true});
  });
  list.addEventListener('keydown', event => {
    if (event.key === 'Escape') { finish(true); return; }
    const handle = event.target.closest('[data-image-drag]');
    if (!handle || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;
    event.preventDefault();
    move(handle.closest('.shop-image-item'), ['ArrowLeft', 'ArrowUp'].includes(event.key) ? -1 : 1);
    handle.focus({preventScroll: true});
  });
  list.addEventListener('dragstart', event => {
    if (pointer) { event.preventDefault(); return; }
    const item = event.target.closest('.shop-image-item');
    if (!item) return;
    start(item); event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', item.dataset.imageId);
  });
  list.addEventListener('dragover', event => {
    if (!dragged || pointer) return;
    event.preventDefault(); event.dataTransfer.dropEffect = 'move'; over(event.target);
  });
  list.addEventListener('drop', event => { if (dragged && !pointer) { event.preventDefault(); finish(); } });
  list.addEventListener('dragend', () => finish(true));
  list.addEventListener('pointerdown', event => {
    const handle = event.target.closest('[data-image-drag]');
    if (!handle || !event.isPrimary || event.button !== 0) return;
    event.preventDefault();
    start(handle.closest('.shop-image-item'));
    pointer = {id: event.pointerId, handle};
    handle.focus({preventScroll: true}); handle.setPointerCapture(event.pointerId);
  });
  document.addEventListener('pointermove', event => {
    if (!pointer || event.pointerId !== pointer.id) return;
    event.preventDefault();
    over(document.elementFromPoint(event.clientX, event.clientY));
    // DOM 순서 변경 후에도 터치 포인터를 계속 추적한다.
    if (!pointer.handle.hasPointerCapture(pointer.id)) pointer.handle.setPointerCapture(pointer.id);
    if (event.clientY < 70) window.scrollBy(0, -18);
    else if (event.clientY > window.innerHeight - 50) window.scrollBy(0, 18);
  }, {passive: false});
  document.addEventListener('pointerup', event => { if (pointer?.id === event.pointerId) finish(); });
  document.addEventListener('pointercancel', event => { if (pointer?.id === event.pointerId) finish(true); });
  window.addEventListener('blur', () => finish(true));
  update();
})();
