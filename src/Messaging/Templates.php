<?php

declare(strict_types=1);

namespace GnuCms\Messaging;

use GnuCms\Error\DomainError;

final class Templates
{
    public function __construct(private Store $store, private Settings $settings)
    {
    }

    public function all(string $environment): array
    {
        Input::environment($environment);
        return array_map($this->decode(...), $this->store->db->select('SELECT * FROM ' . $this->store->db->table('bp_templates')
            . ' WHERE environment = ? ORDER BY code', [$environment]));
    }

    public function get(string $id): array
    {
        $row = $this->store->find('bp_templates', Input::id($id));
        if ($row === null) throw DomainError::notFound('템플릿을 찾을 수 없습니다.');
        return $this->decode($row);
    }

    public function save(string $environment, array $input): array
    {
        return $this->settings->mutate(fn (): array => $this->persist($environment, $input));
    }

    public function byCode(string $environment, string $code): ?array
    {
        $row = $this->store->db->selectOne('SELECT * FROM ' . $this->store->db->table('bp_templates')
            . ' WHERE environment = ? AND code = ?', [Input::environment($environment), $code]);
        return $row === null ? null : $this->decode($row);
    }

    /** 원격 응답만 저장한다. 조회 중 설정이나 로컬 본문이 바뀌면 덮어쓰지 않는다. */
    public function import(string $environment, array $detail, string $expectedRevision): array
    {
        return $this->settings->mutate(function () use ($environment, $detail, $expectedRevision): array {
            $settings = $this->settings->read($environment);
            if ($settings === null || $settings['revision'] !== $detail['config_revision']) {
                throw DomainError::validation(['settings' => '계정 설정이 변경되었습니다. 템플릿을 다시 조회해 주세요.']);
            }
            $before = $this->byCode($environment, $detail['code']);
            if (($before['revision'] ?? '') !== $expectedRevision) {
                throw DomainError::validation(['revision' => '로컬 템플릿이 변경되었습니다. 상세를 다시 조회해 주세요.']);
            }
            if (!$detail['remote']['sendable']) {
                if ($before === null) throw DomainError::validation(['template' => $detail['remote']['reason']]);
                // 승인 취소·차단·미지원 유형 전환 시 이전 본문은 보존하되 발송을 막는다.
                $data = array_intersect_key($before, array_flip(['name', 'message', 'buttons', 'variables', 'senderkey']));
                $data += ['source' => 'kapi', 'remote' => $detail['remote']];
                $this->store->update('bp_templates', $before['id'], ['enabled' => 0, 'revision' => bin2hex(random_bytes(16)),
                    'payload' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                return $this->get($before['id']);
            }
            return $this->persist($environment, $detail['content'] + ['id' => $before['id'] ?? '',
                'revision' => $before['revision'] ?? '', 'enabled' => $before !== null && !$before['enabled'] ? '0' : '1'], $detail['remote']);
        });
    }

    /** 수동 등록과 KAPI 가져오기에 같은 본문·버튼 검증을 적용한다. */
    public function normalize(array $input): array
    {
        $code = Input::text($input['code'] ?? null, '템플릿 코드', 30);
        if (!preg_match('/^[A-Za-z0-9_-]+$/D', $code)) throw DomainError::validation(['code' => '템플릿 코드는 영문·숫자·밑줄·하이픈만 사용할 수 있습니다.']);
        $name = Input::text($input['name'] ?? null, '템플릿 이름', 100);
        $message = Input::text($input['message'] ?? null, '본문', 1000);
        if (($input['type'] ?? 'at') !== 'at' || ($input['emphasize'] ?? 'NONE') !== 'NONE'
            || array_intersect(['quickreply', 'title', 'item', 'header', 'image'], array_keys($input)) !== []) {
            throw DomainError::validation(['type' => '기본 텍스트형과 웹링크 버튼만 지원합니다.']);
        }
        $buttons = $input['buttons'] ?? [];
        if (!is_array($buttons) || !array_is_list($buttons) || count($buttons) > 5) throw DomainError::validation(['buttons' => '버튼은 최대 5개입니다.']);
        $normalized = [];
        foreach ($buttons as $button) {
            if (!is_array($button) || ($button['type'] ?? 'WL') !== 'WL'
                || array_diff(array_keys($button), ['name', 'type', 'url_mobile', 'url_pc']) !== []) {
                throw DomainError::validation(['buttons' => '웹링크(WL) 버튼만 지원합니다.']);
            }
            $item = ['type' => 'WL', 'name' => Input::text($button['name'] ?? null, '버튼 이름', 28),
                'url_mobile' => Input::text($button['url_mobile'] ?? null, '모바일 URL', 500)];
            if (str_contains($item['name'], '#{')) throw DomainError::validation(['buttons' => '버튼 이름은 승인된 고정 문구를 입력해 주세요.']);
            if (($button['url_pc'] ?? '') !== '') $item['url_pc'] = Input::text($button['url_pc'], 'PC URL', 500);
            foreach (['url_mobile', 'url_pc'] as $field) {
                if (isset($item[$field])) $this->url($item[$field], []);
            }
            $normalized[] = $item;
        }
        $data = ['name' => $name, 'message' => $message, 'buttons' => $normalized, 'senderkey' => '', 'source' => 'manual'];
        $data['variables'] = $this->variables($data);
        return ['code' => $code] + $data;
    }

    private function persist(string $environment, array $input, ?array $remote = null): array
    {
        $settings = $this->settings->read(Input::environment($environment));
        if ($settings === null) throw DomainError::validation(['settings' => '플러그인 설정을 먼저 저장해 주세요.']);
        if ($settings['senderkey'] === '') throw DomainError::validation(['senderkey' => '알림톡을 사용하려면 플러그인 설정에 발신프로필 키를 입력해 주세요.']);
        $data = $this->normalize($input);
        $code = $data['code'];
        unset($data['code']);
        $data['senderkey'] = $settings['senderkey'];
        $id = ($input['id'] ?? '') === '' ? bin2hex(random_bytes(16)) : Input::id($input['id']);
        $before = ($input['id'] ?? '') === '' ? null : $this->get($id);
        if ($before !== null && ($before['environment'] !== $environment || ($input['revision'] ?? '') !== $before['revision'])) {
            throw DomainError::validation(['revision' => '템플릿이 변경되었습니다. 새로고침 후 다시 저장해 주세요.']);
        }
        if ($remote === null && ($before['source'] ?? '') === 'kapi') {
            // 브라우저 폼의 CRLF 직렬화는 내용 수정으로 취급하지 않는다. 저장은 원문을 보존한다.
            if (str_replace("\r\n", "\n", $data['message']) === str_replace("\r\n", "\n", $before['message'])) {
                $data['message'] = $before['message'];
            }
            foreach (['name', 'message', 'buttons', 'senderkey'] as $field) {
                if ($data[$field] !== $before[$field]) throw DomainError::validation(['template' => '가져온 본문과 버튼은 비즈뿌리오에서 수정한 뒤 다시 가져와 주세요.']);
            }
            if ($code !== $before['code']) throw DomainError::validation(['code' => '가져온 템플릿 코드는 변경할 수 없습니다.']);
            $remote = $before['remote'];
        }
        if ($remote !== null) {
            if (($input['enabled'] ?? '1') !== '0' && (!$remote['sendable'] || $remote['account'] !== $settings['account'])) {
                throw DomainError::validation(['template' => '현재 계정에서 사용할 수 없는 템플릿입니다. 비즈뿌리오에서 다시 조회해 주세요.']);
            }
            $data['source'] = 'kapi';
            $data['remote'] = $remote;
        }
        $row = ['environment' => $environment, 'code' => $code, 'revision' => bin2hex(random_bytes(16)),
            'payload' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'enabled' => ($input['enabled'] ?? '1') === '0' ? 0 : 1];
        try {
            if ($before === null) $this->store->insert('bp_templates', ['id' => $id] + $row);
            else $this->store->update('bp_templates', $id, $row);
        } catch (DomainError $e) {
            throw DomainError::validation(['code' => '템플릿을 저장하지 못했습니다. 같은 환경의 코드 중복과 DB 상태를 확인해 주세요.']);
        }
        return $this->get($id);
    }

    public function preview(string $id, array $values, ?string $revision = null): array
    {
        $template = $this->get($id);
        $settings = $this->settings->read($template['environment']);
        if (!$template['enabled'] || $settings === null || $settings['senderkey'] === '' || $settings['senderkey'] !== $template['senderkey']
            || (($template['source'] ?? '') === 'kapi' && (!$template['remote']['sendable'] || $template['remote']['account'] !== $settings['account']))) {
            throw DomainError::validation(['template' => '현재 발신프로필에서 사용할 수 없는 템플릿입니다.']);
        }
        if ($revision !== null && $revision !== $template['revision']) throw DomainError::validation(['revision' => '템플릿이 변경되었습니다. 미리보기를 다시 확인해 주세요.']);
        if (array_diff(array_keys($values), $template['variables']) !== [] || array_diff($template['variables'], array_keys($values)) !== []) {
            throw DomainError::validation(['variables' => '템플릿의 모든 변수만 입력해 주세요.']);
        }
        $replacements = [];
        foreach ($values as $key => $value) {
            $value = Input::text($value, (string) $key, 1000);
            if (str_contains($value, '#{')) throw DomainError::validation(['variables' => '변수 값에 다른 변수를 넣을 수 없습니다.']);
            $replacements['#{' . $key . '}'] = $value;
        }
        $message = Input::text(strtr($template['message'], $replacements), '치환 후 본문', 1000);
        $buttons = $template['buttons'];
        foreach ($buttons as &$button) {
            foreach (['url_mobile', 'url_pc'] as $field) {
                if (isset($button[$field])) $button[$field] = $this->url($button[$field], $values);
            }
        }
        unset($button);
        $at = ['senderkey' => $template['senderkey'], 'templatecode' => $template['code'], 'message' => $message];
        if ($buttons !== []) $at['button'] = $buttons;
        return ['template_id' => $id, 'revision' => $template['revision'], 'environment' => $template['environment'],
            'config_revision' => $settings['revision'],
            'name' => $template['name'], 'message' => $message, 'buttons' => $buttons, 'content' => ['at' => $at]];
    }

    private function variables(array $data): array
    {
        $strings = [$data['message']];
        foreach ($data['buttons'] as $button) {
            foreach (['url_mobile', 'url_pc'] as $field) if (isset($button[$field])) $strings[] = $button[$field];
        }
        $keys = [];
        foreach ($strings as $string) {
            preg_match_all('/#\{([\p{L}\p{N}_ -]{1,40})\}/u', $string, $matches);
            if (str_contains(str_replace($matches[0], '', $string), '#{')) throw DomainError::validation(['variables' => '변수는 #{이름} 형식으로 입력해 주세요.']);
            $keys = array_merge($keys, $matches[1]);
        }
        return array_values(array_unique($keys));
    }

    private function url(string $pattern, array $values): string
    {
        // 호스트·프로토콜에는 변수를 허용하지 않는다. 변수는 경로/쿼리 값으로만 치환한다.
        if (!preg_match('~^https?://([^/?#]+)(.*)$~us', $pattern, $parts) || str_contains($parts[1], '#{') || str_contains($parts[1], '@')) {
            throw DomainError::validation(['url' => '고정 도메인의 http/https URL을 입력해 주세요.']);
        }
        $url = preg_replace_callback('/#\{([\p{L}\p{N}_ -]{1,40})\}/u',
            static fn (array $match): string => rawurlencode((string) ($values[$match[1]] ?? 'preview')), $pattern);
        if (filter_var($url, FILTER_VALIDATE_URL) === false || strlen($url) > 500 || str_contains($url, '\\')
            || parse_url($url, PHP_URL_HOST) !== parse_url(preg_replace('/#\{[^}]+\}/u', 'preview', $pattern), PHP_URL_HOST)) {
            throw DomainError::validation(['url' => '치환 후 버튼 URL을 확인해 주세요.']);
        }
        return $url;
    }

    private function decode(array $row): array
    {
        $data = json_decode($row['payload'], true, 32, JSON_THROW_ON_ERROR);
        unset($row['payload']);
        $row['enabled'] = (bool) $row['enabled'];
        return $row + $data;
    }
}
