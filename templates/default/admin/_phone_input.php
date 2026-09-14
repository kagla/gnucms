<script>
(() => {
  document.querySelectorAll('[data-phone-format], [data-sms-phone]').forEach(field => {
    const format = (inputType = '') => {
      const raw = field.value;
      // 잘못된 입력이나 긴 번호를 유효한 다른 번호로 바꾸지 않는다.
      if (!/^[0-9 -]*$/.test(raw)) return;
      const digits = raw.replace(/[ -]/g, '');
      const domestic = field.dataset.phoneFormat === 'domestic';
      let prefix = 3, middle = digits.length === 10 && !digits.startsWith('010') ? 3 : 4;
      if (domestic && digits.startsWith('1')) {
        if (digits.length > 8) return;
        prefix = 4; middle = 4;
      } else if (domestic && digits.startsWith('02')) {
        if (digits.length > 10) return;
        prefix = 2; middle = digits.length === 9 ? 3 : 4;
      } else {
        if (digits.length > 11) return;
        if (domestic && digits.startsWith('070')) middle = 4;
      }
      const formatted = [digits.slice(0, prefix), digits.slice(prefix, prefix + middle), digits.slice(prefix + middle)].filter(Boolean).join('-');
      if (raw === formatted) return;
      const position = offset => {
        const count = raw.slice(0, offset).replace(/[^0-9]/g, '').length;
        let index = 0, seen = 0;
        while (index < formatted.length && seen < count) {
          if (formatted[index] !== '-') seen++;
          index++;
        }
        // 하이픈을 Delete로 지웠다면 다시 생긴 구분자 뒤로 이동한다.
        if (inputType === 'deleteContentForward' && formatted[index] === '-') index++;
        return index;
      };
      const start = position(field.selectionStart ?? raw.length);
      const end = position(field.selectionEnd ?? raw.length);
      const direction = field.selectionDirection;
      field.value = formatted;
      if (document.activeElement === field) field.setSelectionRange(start, end, direction);
    };
    field.addEventListener('input', event => { if (!event.isComposing) format(event.inputType); });
    field.addEventListener('compositionend', () => format());
    field.addEventListener('change', () => format());
    format();
  });
})();
</script>
