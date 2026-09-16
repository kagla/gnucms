<?php

declare(strict_types=1);

namespace GnuCms\Messaging;

use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;

/** KAPI 읽기 전용 연동. 원격 등록·검수 신청·메시지 발송은 수행하지 않는다. */
final class KapiTemplates
{
    public function __construct(private HttpTransport $http, private Settings $settings, private Templates $templates)
    {
    }

    public function listing(string $environment, array $input): array
    {
        $settings = $this->configuration($environment);
        $page = filter_var($input['remote_page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
        if ($page === false) throw DomainError::validation(['page' => '조회 페이지를 확인해 주세요.']);
        $result = $this->request($settings, 'list', ['page' => $page, 'count' => 20]);
        $rows = $result['data']['list'] ?? null;
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 20) throw $this->invalidResponse();
        foreach (['totalCount', 'totalPage', 'currentPage'] as $field) {
            if (!is_int($result[$field] ?? null) || $result[$field] < 0) throw $this->invalidResponse();
        }
        if ($rows !== [] && $result['currentPage'] !== $page) throw $this->invalidResponse();
        $items = [];
        foreach ($rows as $row) {
            $this->assertProfile($row, $settings);
            $items[] = ['code' => $this->code($row['templateCode'] ?? null),
                'name' => Input::text($row['templateName'] ?? null, '템플릿 이름', 200),
                'status' => self::statusLabel($row['serviceStatus'] ?? '')];
        }
        return ['items' => $items, 'page' => $page, 'pages' => $result['totalPage'], 'total' => $result['totalCount']];
    }

    public function detail(string $environment, array $input): array
    {
        $settings = $this->configuration($environment);
        if (isset($input['config_revision']) && $input['config_revision'] !== $settings['revision']) {
            throw DomainError::validation(['settings' => '계정 설정이 변경되었습니다. 상세를 다시 조회해 주세요.']);
        }
        $code = $this->code($input['remote_code'] ?? null);
        $result = $this->request($settings, 'detail', ['templateCode' => $code]);
        $row = $result['data'] ?? null;
        $this->assertProfile($row, $settings);
        if (($row['templateCode'] ?? null) !== $code) throw $this->invalidResponse();
        $name = Input::text($row['templateName'] ?? null, '템플릿 이름', 200);
        $message = Input::text($row['templateContent'] ?? null, '본문', 20000, true);
        // 지원하지 않는 구성은 사유를 모아 관리자가 무엇을 바꿔야 하는지 알 수 있게 한다.
        $issues = [];
        $type = (string) ($row['templateMessageType'] ?? '');
        $emphasize = (string) ($row['templateEmphasizeType'] ?? '');
        if ($type !== 'BA') $issues[] = '부가정보형·채널추가형·복합형(' . $type . ') 템플릿은 지원하지 않습니다. 기본형(BA)만 가져올 수 있습니다.';
        if ($emphasize !== 'NONE') $issues[] = '강조표기형·이미지형·아이템리스트형(' . $emphasize . ')은 지원하지 않습니다.';
        // templatePreviewMessage는 알림 미리보기 문구(최대 40자)일 뿐 발송 필드가 아니므로 지원 여부와 무관하다(공식 기본형 예시에도 포함).
        foreach (['quickReplies' => '바로연결 버튼', 'templateExtra' => '부가정보', 'templateImageUrl' => '이미지', 'templateImageName' => '이미지', 'templateTitle' => '강조 표기',
            'templateSubtitle' => '강조 표기', 'templateHeader' => '아이템리스트 헤더', 'templateItem' => '아이템리스트', 'templateItemHighlight' => '아이템 하이라이트'] as $field => $label) {
            if (!empty($row[$field])) $issues[] = $label . '이(가) 있는 템플릿은 지원하지 않습니다.';
        }
        // securityFlag(보안 템플릿)는 단말 알림 미리보기만 가리는 표시 옵션이라 발송·가져오기에 영향을 주지 않는다.
        // 대표 링크(templateRepresentLink)는 발송 API의 link(url_mobile/url_pc)로 함께 보내야 한다(결과 코드 7342). 앱 스킴은 지원하지 않는다.
        $link = [];
        $represent = $row['templateRepresentLink'] ?? null;
        if (is_array($represent)) {
            if (!empty($represent['linkAnd']) || !empty($represent['linkIos'])) $issues[] = '앱 스킴 대표 링크는 지원하지 않습니다. 웹 URL 대표 링크만 가져올 수 있습니다.';
            foreach (['linkMo' => 'url_mobile', 'linkPc' => 'url_pc'] as $source => $target) {
                if (!empty($represent[$source])) $link[$target] = Input::text($represent[$source], '대표 링크 URL', 2000);
            }
        } elseif ($represent !== null) {
            throw $this->invalidResponse();
        }
        $buttons = $row['buttons'] ?? [];
        if (!is_array($buttons) || !array_is_list($buttons) || count($buttons) > 10) throw $this->invalidResponse();
        if (count($buttons) > 5) $issues[] = '버튼이 5개를 넘습니다(' . count($buttons) . '개).';
        // KAPI의 ordering 순서를 발송 API의 배열 순서로 옮긴다.
        $orders = [];
        foreach ($buttons as $button) {
            if (!is_array($button)) throw $this->invalidResponse();
            if (isset($button['ordering'])) {
                if (!is_int($button['ordering']) || $button['ordering'] < 0 || isset($orders[$button['ordering']])) throw $this->invalidResponse();
                $orders[$button['ordering']] = true;
            }
        }
        if ($orders !== []) {
            if (count($orders) !== count($buttons)) throw $this->invalidResponse();
            usort($buttons, static fn (array $a, array $b): int => $a['ordering'] <=> $b['ordering']);
        }
        $mapped = [];
        foreach ($buttons as $button) {
            $buttonType = Input::text($button['linkType'] ?? null, '버튼 유형', 20);
            $buttonName = Input::text($button['name'] ?? null, '버튼 이름', 100);
            if ($buttonType !== 'WL' || !empty($button['linkAnd']) || !empty($button['linkIos'])
                || !empty($button['pluginId']) || !empty($button['telNumber'])) {
                $issues[] = '웹링크(WL) 버튼만 지원합니다. 지원하지 않는 버튼: ' . $buttonName . '(' . ($buttonType !== 'WL' ? $buttonType : '앱 스킴·플러그인·전화 포함') . ').';
            }
            $mapped[] = ['type' => $buttonType, 'name' => $buttonName,
                'url_mobile' => Input::text($button['linkMo'] ?? '', '모바일 URL', 2000, true),
                'url_pc' => Input::text($button['linkPc'] ?? '', 'PC URL', 2000, true)];
        }
        $content = ['code' => $code, 'name' => $name, 'message' => $message, 'buttons' => $mapped];
        if ($link !== []) $content['link'] = $link;
        if (mb_strlen($message) > 1300) $issues[] = '본문이 1,300자를 넘습니다(' . mb_strlen($message) . '자).';
        elseif ($issues === []) {
            try { $this->templates->normalize($content); }
            catch (DomainError $e) { $issues[] = '버튼 URL·본문 형식을 지원하지 않습니다: ' . implode(' ', $e->details() ?: [$e->getMessage()]); }
        }
        $supported = $issues === [];
        $inspection = (string) ($row['inspectionStatus'] ?? '');
        $approved = $inspection === 'APR';
        $serviceStatus = (string) ($row['serviceStatus'] ?? '');
        $active = in_array($row['status'] ?? '', ['A', 'R'], true)
            && ($row['block'] ?? null) === false && ($row['dormant'] ?? null) === false
            && ($serviceStatus === '' || in_array($serviceStatus, ['RDY', 'ACT'], true));
        $reason = match (true) {
            $inspection === 'REG' => '카카오 검수를 아직 신청하지 않았습니다(등록 상태). 비즈뿌리오에서 검수 요청 후 승인되면 가져올 수 있습니다.',
            $inspection === 'REQ' => '카카오 검수 중입니다. 승인되면 가져올 수 있습니다.',
            $inspection === 'REJ' => '카카오 검수에서 반려되었습니다. 비즈뿌리오에서 반려 사유를 확인하고 수정한 뒤 다시 검수 요청해 주세요.',
            !$approved => '카카오 승인이 완료되지 않았습니다(검수 상태: ' . ($inspection !== '' ? $inspection : '없음') . ').',
            ($row['block'] ?? null) === true || in_array($serviceStatus, ['STP', 'BLK'], true) => '차단된 템플릿입니다. 비즈뿌리오에서 차단 사유와 상태를 확인해 주세요.',
            ($row['dormant'] ?? null) === true || $serviceStatus === 'DMT' => '장기 미사용으로 휴면 상태입니다. 비즈뿌리오에서 휴면 해제 후 가져올 수 있습니다.',
            ($row['status'] ?? '') === 'S' => '비즈뿌리오에서 사용 중지된 템플릿입니다. 사용 상태로 바꾼 뒤 가져올 수 있습니다.',
            !$active => '사용 상태를 확인할 수 없습니다(상태: ' . json_encode($row['status'] ?? null) . ', 차단: ' . json_encode($row['block'] ?? null) . ', 휴면: ' . json_encode($row['dormant'] ?? null) . '). 비즈뿌리오에서 상태를 확인해 주세요.',
            !$supported => 'GNUCMS에서 지원하지 않는 구성입니다. ' . implode(' ', $issues),
            default => '',
        };
        $remote = ['account' => $settings['account'], 'checked_at' => Clock::timestamp(),
            'inspection' => self::inspectionLabel($row['inspectionStatus'] ?? ''),
            'status' => match ($row['status'] ?? '') { 'A' => '정상', 'R' => '대기/발송 전', 'S' => '중지', default => '확인 불가' },
            'blocked' => is_bool($row['block'] ?? null) ? $row['block'] : null,
            'dormant' => is_bool($row['dormant'] ?? null) ? $row['dormant'] : null,
            'supported' => $supported, 'sendable' => $reason === '', 'reason' => $reason];
        $local = $this->templates->byCode($environment, $code);
        return ['code' => $code, 'content' => $content, 'remote' => $remote,
            'config_revision' => $settings['revision'], 'local_revision' => $local['revision'] ?? '',
            'local_id' => $local['id'] ?? null];
    }

    /**
     * 승인·발송 가능 템플릿을 모두 가져오거나 갱신한다. remote_code가 있으면 그 하나만 처리한다.
     * 승인 취소된 기존 사본은 사용을 중지하고, 검수 전·미지원 템플릿은 사유와 함께 건너뛴다.
     * @return array{total:int,imported:int,refreshed:int,disabled:list<array>,skipped:list<array>}
     */
    public function importAll(string $environment, array $input): array
    {
        $this->configuration($environment);
        $entries = [];
        if (($input['remote_code'] ?? '') !== '') {
            $entries[] = ['code' => $this->code($input['remote_code']), 'name' => ''];
        } else {
            for ($page = 1; $page <= 10; $page++) {
                $listing = $this->listing($environment, ['remote_page' => $page]);
                foreach ($listing['items'] as $item) $entries[] = ['code' => $item['code'], 'name' => $item['name']];
                if ($listing['items'] === [] || $page >= $listing['pages']) break;
            }
        }
        $summary = ['total' => count($entries), 'imported' => 0, 'refreshed' => 0, 'disabled' => [], 'skipped' => []];
        foreach ($entries as $entry) {
            try {
                $detail = $this->detail($environment, ['remote_code' => $entry['code']]);
                $row = ['code' => $entry['code'], 'name' => $detail['content']['name'], 'reason' => $detail['remote']['reason']];
                if ($detail['remote']['sendable']) {
                    $this->templates->import($environment, $detail, $detail['local_revision']);
                    $summary[$detail['local_id'] === null ? 'imported' : 'refreshed']++;
                } elseif ($detail['local_id'] !== null) {
                    $this->templates->import($environment, $detail, $detail['local_revision']);
                    $summary['disabled'][] = $row;
                } else {
                    $summary['skipped'][] = $row;
                }
            } catch (DomainError $e) {
                $summary['skipped'][] = ['code' => $entry['code'], 'name' => $entry['name'],
                    'reason' => $e->code() === 'BIZPPURIO_KAPI' ? $e->getMessage() : implode(' ', $e->details() ?: [$e->getMessage()])];
            }
        }
        return $summary;
    }

    public function import(string $environment, array $input): array
    {
        // 화면이 제출한 본문·버튼·승인 상태는 사용하지 않고 최신 상세를 다시 읽는다.
        Input::id($input['config_revision'] ?? null);
        $revision = $input['local_revision'] ?? null;
        if ($revision !== '') Input::id($revision);
        $detail = $this->detail($environment, $input);
        return $this->templates->import($environment, $detail, $revision);
    }

    private function configuration(string $environment): array
    {
        $settings = $this->settings->read(Input::environment($environment));
        if ($settings === null || ($settings['kapi_key'] ?? '') === '') {
            throw DomainError::validation(['kapi' => '설정 → 알림톡·문자에 이 환경의 API 키를 먼저 저장해 주세요.']);
        }
        if ($settings['senderkey'] === '') throw DomainError::validation(['senderkey' => '알림톡 템플릿을 조회하려면 발신프로필키를 먼저 저장해 주세요.']);
        return $settings;
    }

    private function request(array $settings, string $operation, array $body): array
    {
        try {
            $result = $this->http->post($settings['environment'], '/v3/kakao/template/' . $operation, [],
                ['bizId' => $settings['account'], 'apiKey' => $settings['kapi_key'],
                    'senderKey' => $settings['senderkey'], 'senderKeyType' => 'S'] + $body);
        } catch (TransportFailure $e) {
            throw $this->invalidResponse();
        }
        if ($result['status'] !== 200 || ($result['body']['code'] ?? null) !== '200') {
            $code = $result['body']['code'] ?? null;
            $code = is_scalar($code) && preg_match('/^[0-9]{1,8}$/D', (string) $code) ? (string) $code : '확인 불가';
            // 업체 message는 잘못된 키를 그대로 돌려주는 등 내부 진단문이므로 화면에 내보내지 않는다.
            throw new DomainError('BIZPPURIO_KAPI', '템플릿 조회 실패 (HTTP ' . (int) $result['status'] . ' · 코드 ' . $code . '). ' . self::reason($code), 503);
        }
        return $result['body'];
    }

    /** 공식 코드 정의(bizppurio.github.io/response-codes)에 따른 안내. 403은 실제 응답에서 "잘못된 apiKey 입니다."로 온다. */
    private static function reason(string $code): string
    {
        return match ($code) {
            '101' => '비즈뿌리오에 이 아이디(bizId)가 없습니다. 설정의 아이디를 확인해 주세요.',
            '102', '403' => 'API 키가 맞지 않거나 권한이 없습니다. 비즈뿌리오 내 정보 화면의 [API 키] 값을 설정에 저장해 주세요. 없으면 고객센터에 아이디를 알려 발급받습니다. 모듈 비밀번호와는 다른 값입니다.',
            '103' => '중지된 계정입니다. 비즈뿌리오에 계정 상태를 문의해 주세요.',
            '405', '621' => '요청 파라미터 오류입니다. 아이디·발신프로필키 형식을 확인해 주세요.',
            '507' => '발신프로필키가 유효하지 않습니다. 비즈뿌리오 카카오 비즈니스 채널의 발신프로필키를 설정에 저장해 주세요.',
            default => 'API 키·아이디·발신프로필키를 확인해 주세요.',
        };
    }

    private function assertProfile(mixed $row, array $settings): void
    {
        if (!is_array($row) || ($row['senderKey'] ?? null) !== $settings['senderkey'] || ($row['senderKeyType'] ?? 'S') !== 'S') {
            throw $this->invalidResponse();
        }
    }

    private function code(mixed $value): string
    {
        $code = Input::text($value, '템플릿 코드', 30);
        if (!preg_match('/^[A-Za-z0-9_-]+$/D', $code)) throw DomainError::validation(['code' => '템플릿 코드를 확인해 주세요.']);
        return $code;
    }

    private function invalidResponse(): DomainError
    {
        return new DomainError('BIZPPURIO_KAPI', '비즈뿌리오 템플릿 응답을 확인하지 못했습니다. 연결·응답 형식과 발신프로필을 확인한 뒤 다시 조회해 주세요.', 503);
    }

    private static function statusLabel(mixed $status): string
    {
        return match ($status) { 'REG' => '등록', 'REQ' => '검수 중', 'REJ' => '반려', 'STP', 'BLK' => '차단',
            'RDY' => '발송 전', 'ACT' => '정상', 'DMT' => '휴면', default => '확인 불가' };
    }

    private static function inspectionLabel(mixed $status): string
    {
        return match ($status) { 'REG' => '등록', 'REQ' => '검수 중', 'REJ' => '반려', 'APR' => '승인', default => '확인 불가' };
    }
}
