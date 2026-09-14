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
        $supported = ($row['templateMessageType'] ?? '') === 'BA' && ($row['templateEmphasizeType'] ?? '') === 'NONE';
        foreach (['quickReplies', 'templateExtra', 'templateImageUrl', 'templateImageName', 'templateTitle',
            'templateSubtitle', 'templateHeader', 'templateItem', 'templateItemHighlight', 'templateRepresentLink', 'templatePreviewMessage'] as $field) {
            if (!empty($row[$field])) $supported = false;
        }
        if (($row['securityFlag'] ?? false) !== false) $supported = false;
        $buttons = $row['buttons'] ?? [];
        if (!is_array($buttons) || !array_is_list($buttons) || count($buttons) > 10) throw $this->invalidResponse();
        if (count($buttons) > 5) $supported = false;
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
            $type = Input::text($button['linkType'] ?? null, '버튼 유형', 20);
            if ($type !== 'WL' || !empty($button['linkAnd']) || !empty($button['linkIos'])
                || !empty($button['pluginId']) || !empty($button['telNumber'])) $supported = false;
            $mapped[] = ['type' => $type, 'name' => Input::text($button['name'] ?? null, '버튼 이름', 100),
                'url_mobile' => Input::text($button['linkMo'] ?? '', '모바일 URL', 2000, true),
                'url_pc' => Input::text($button['linkPc'] ?? '', 'PC URL', 2000, true)];
        }
        $content = ['code' => $code, 'name' => $name, 'message' => $message, 'buttons' => $mapped];
        try { $this->templates->normalize($content); }
        catch (DomainError $e) { $supported = false; }
        $approved = ($row['inspectionStatus'] ?? '') === 'APR';
        $active = in_array($row['status'] ?? '', ['A', 'R'], true)
            && ($row['block'] ?? null) === false && ($row['dormant'] ?? null) === false
            && (!isset($row['serviceStatus']) || in_array($row['serviceStatus'], ['RDY', 'ACT'], true));
        $reason = !$approved ? '카카오 승인이 완료되지 않았습니다.'
            : (!$active ? '중지·차단·휴면 상태이거나 사용 상태를 확인할 수 없습니다.'
                : (!$supported ? 'GNUCMS에서 지원하는 기본 텍스트형·웹링크 버튼 구성이 아닙니다.' : ''));
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
            throw DomainError::validation(['kapi' => '플러그인 설정에 이 환경의 KAPI API Key를 먼저 저장해 주세요.']);
        }
        if ($settings['senderkey'] === '') throw DomainError::validation(['senderkey' => '알림톡 템플릿을 조회하려면 발신프로필 키를 먼저 저장해 주세요.']);
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
            throw new DomainError('BIZPPURIO_KAPI', '템플릿 조회 실패 (HTTP ' . (int) $result['status'] . ' · 코드 ' . $code
                . '). KAPI 사용 권한·API Key와 계정·발신프로필을 확인해 주세요.', 503);
        }
        return $result['body'];
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
