(() => {
  'use strict';
  const main = document.getElementById('shop-product-image');
  const link = document.getElementById('shop-product-image-link');
  if (!main || !link) return;
  const thumbnails = document.querySelectorAll('[data-product-thumb]');
  for (const thumbnail of thumbnails) thumbnail.addEventListener('click', event => {
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    main.src = thumbnail.href; main.alt = thumbnail.querySelector('img').alt; link.href = thumbnail.href;
    for (const item of thumbnails) item.removeAttribute('aria-current');
    thumbnail.setAttribute('aria-current', 'true');
  });
})();
