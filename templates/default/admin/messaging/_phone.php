<?php
$label = $phone === null ? '번호 확인 불가'
    : ($phone === '' ? '보관 만료' : preg_replace('/^(\d{3})(\d{3,4})(\d{4})$/D', '$1-$2-$3', $phone));
?><?= $this->e($label) ?>
