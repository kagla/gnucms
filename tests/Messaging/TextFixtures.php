<?php

declare(strict_types=1);

namespace GnuCms\Tests\Messaging;

final class TextFixtures
{
    // 결합 문자와 보이지 않는 U+FEFF도 사용자가 입력한 그대로 유지해야 한다.
    public const ART = "문자 잘 가나요?\n테스트 ㅎㅎ\n\nʕ ᵔᆺᵔ ʔ\n乁( ●•̄ .̱ •̄ ●乁)\n𓏸\u{FEFF}╹.╹𓏸\u{FEFF}\n༼ つ -'o'- ༽つ";
}
