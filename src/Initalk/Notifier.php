<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;

/** 결제 요청을 알림톡 변수로 바꿔 MessagingService로 보낸다. 업무 저장이 끝난 뒤 호출한다. */
final class Notifier
{
    public function __construct(private App $app, private Settings $settings, private Requests $requests)
    {
    }

    /** 템플릿이 쓰는 변수만 돌려준다. 제공하지 않는 변수를 요구하면 거부한다. */
    public static function variables(array $request, array $config, array $wanted): array
    {
        $seoul = new \DateTimeZone('Asia/Seoul');
        $support = trim((string) ($config['support_phone'] ?? ''));
        $all = [
            '상점명' => (string) $config['store_name'],
            '구매자명' => (string) $request['buyer_name'],
            '요청일' => (new \DateTimeImmutable('@' . (int) $request['created_at']))->setTimezone($seoul)->format('n월 j일'),
            '상품명' => (string) $request['product_name'],
            '금액' => number_format((int) $request['amount']),
            '결제기한' => (new \DateTimeImmutable('@' . (int) $request['expires_at']))->setTimezone($seoul)->format('Y년 m월 d일 H:i'),
            '고객센터' => $support !== '' ? $support : '상점 문의',
            '결제토큰' => (string) $request['url_token'],
            '주문번호' => (string) $request['number'],
        ];
        $variables = [];
        foreach ($wanted as $name) {
            if (!array_key_exists($name, $all)) throw DomainError::validation(['template' => '이니톡 결제가 제공하지 않는 변수입니다: ' . $name]);
            $variables[$name] = $all[$name];
        }
        return $variables;
    }

    public function send(string $id, string $actor): array
    {
        $request = $this->requests->find($id);
        if (!Status::canSend($request['status'])) throw DomainError::validation(['status' => '이 상태에서는 알림톡을 보낼 수 없습니다.']);
        if ($request['phone'] === '') throw DomainError::validation(['phone' => '개인정보가 정리된 요청에는 발송할 수 없습니다.']);
        $config = $this->settings->read();
        $templateId = $config['template'][$request['environment']];
        if ($templateId === '') throw DomainError::validation(['template' => '이 환경의 알림톡 템플릿을 이니톡 결제 설정에서 선택해 주세요.']);
        $messaging = $this->app->messaging();
        if (empty($messaging->status($request['environment'])['enabled'])) {
            throw DomainError::validation(['messaging' => '알림톡 발송이 정지되어 있습니다. 설정 → 알림톡·문자에서 발송을 허용해 주세요.']);
        }
        if ($request['status'] === Status::EXPIRED || $request['expires_at'] <= Clock::timestamp()) {
            $request = $this->requests->extend($id, $config['expiry_hours'], $actor);
        }
        $template = $messaging->templateAction('get', ['id' => $templateId]);
        $variables = self::variables($request, $config, $template['variables']);
        $sequence = $request['dispatch_count'] + 1;
        $result = $messaging->send(['environment' => $request['environment'], 'template_id' => $templateId, 'revision' => $template['revision'],
            'idempotency_key' => 'initalk:' . $id . ':' . $sequence, 'phone' => $request['phone'], 'variables' => $variables, 'reference' => $request['number']]);
        $this->requests->recordDispatch($id, $result['id'], $sequence, (string) $result['submission'], $actor);
        return $result;
    }

    /** 통합조회의 선택 발송. 건별 오류는 메시지만 모은다(수신정보 없음). */
    public function sendMany(array $ids, string $actor): array
    {
        $summary = ['sent' => 0, 'failed' => 0, 'errors' => []];
        foreach (array_slice(array_values(array_unique(array_filter($ids, 'is_string'))), 0, 100) as $id) {
            try {
                $result = $this->send($id, $actor);
                if ($result['submission'] === 'accepted') $summary['sent']++;
                else { $summary['failed']++; $summary['errors'][$id] = '접수 실패(' . $result['submission'] . ')'; }
            } catch (DomainError $e) {
                $summary['failed']++;
                $summary['errors'][$id] = $e->status() >= 500 ? '발송 요청을 완료하지 못했습니다.' : implode(' ', $e->details() ?: [$e->getMessage()]);
            }
        }
        return $summary;
    }
}
