<?php

declare(strict_types=1);

namespace GnuCms\Plugins\Bizppurio;

use GnuCms\Error\DomainError;
use GnuCms\Mail\SecretCipher;
use GnuCms\Support\Clock;

final class Api
{
    public function __construct(private HttpTransport $http, private SecretCipher $cipher, private string $storageDir)
    {
    }

    public function token(array $settings, bool $force = false): string
    {
        $generation = (new \GnuCms\Extension\RuntimePermit($this->storageDir))->generation();
        $file = $this->storageDir . '/extensions-runtime/bizppurio/token-' . hash('sha256', $settings['environment'] . $settings['revision'] . $generation);
        return Locks::run($file . '.lock', function () use ($settings, $file, $force): string {
            if (!$force && is_file($file)) {
                try {
                    $cache = json_decode($this->cipher->decrypt((string) file_get_contents($file)), true, 16, JSON_THROW_ON_ERROR);
                    if ((int) $cache['expires'] > Clock::timestamp() + 60 && is_string($cache['token'])) return $cache['token'];
                } catch (\Throwable $e) {
                    // 손상된 캐시는 재발급한다. 비밀값을 기록하지 않는다.
                }
            }
            try {
                $result = $this->http->post($settings['environment'], '/v1/token',
                    ['Authorization' => 'Basic ' . base64_encode($settings['account'] . ':' . $settings['password'])], []);
            } catch (TransportFailure $e) {
                throw new DomainError('BIZPPURIO_AUTH', '비즈뿌리오 인증 서버의 응답을 확인하지 못했습니다. 서버의 HTTPS 연결·응답 지연·응답 형식을 확인해 주세요. IP 등록 오류로 확인된 것은 아닙니다.', 503);
            }
            $data = $result['body'];
            $expires = is_string($data['expired'] ?? null)
                ? \DateTimeImmutable::createFromFormat('!YmdHis', $data['expired'], new \DateTimeZone('Asia/Seoul')) : false;
            if ($result['status'] !== 200 || ($data['type'] ?? null) !== 'Bearer' || !is_string($data['accesstoken'] ?? null)
                || !preg_match('/^[A-Za-z0-9._~+\/-]+=*$/D', $data['accesstoken']) || strlen($data['accesstoken']) > 8192
                || $expires === false || $expires->format('YmdHis') !== $data['expired'] || $expires->getTimestamp() <= Clock::timestamp() + 60) {
                throw $this->authenticationError($settings['environment'], $result);
            }
            $encoded = $this->cipher->encrypt(json_encode(['token' => $data['accesstoken'], 'expires' => min($expires->getTimestamp(), Clock::timestamp() + 86400)], JSON_THROW_ON_ERROR));
            $tmp = tempnam(dirname($file), '.token-');
            try {
                if ($tmp === false || file_put_contents($tmp, $encoded) !== strlen($encoded) || !rename($tmp, $file)) throw new TransportFailure();
            } finally {
                if (is_string($tmp) && is_file($tmp)) unlink($tmp);
            }
            return $data['accesstoken'];
        });
    }

    public function post(array $settings, string $path, array $body, string $token): array
    {
        return $this->http->post($settings['environment'], $path, ['Authorization' => 'Bearer ' . $token], ['account' => $settings['account']] + $body);
    }

    /** 업체 description·토큰·계정 원문은 표시하지 않고 숫자 코드와 고정 안내만 전달한다. */
    private function authenticationError(string $environment, array $result): DomainError
    {
        $raw = $result['body']['code'] ?? null;
        $code = (is_int($raw) || is_string($raw)) && preg_match('/^[1-9][0-9]{3}$/D', (string) $raw) ? (string) $raw : '';
        $http = is_int($result['status'] ?? null) && $result['status'] >= 100 && $result['status'] <= 599 ? (string) $result['status'] : '확인 불가';
        $reason = match ($code) {
            '3000' => 'API 계정에 접속 허용 IP가 등록되어 있지 않습니다.',
            '3003' => 'API 서버가 접속 IP를 유효하지 않은 것으로 판단했습니다.',
            '3010' => 'API 요청의 접속 IP가 계정에 등록된 허용 IP와 일치하지 않습니다.',
            '3001' => 'Basic 인증 정보가 유효하지 않습니다. 계정 ID와 API 연동용 모듈 비밀번호를 확인해 주세요.',
            '3004', '3006' => '계정이 유효하지 않거나 존재하지 않습니다. 선택한 검수/운영 환경의 API 계정인지 확인해 주세요.',
            '3007' => 'API 연동용 모듈 비밀번호가 유효하지 않습니다. 홈페이지 로그인 비밀번호와 별도입니다. 비즈뿌리오의 모듈연동 환경설정에서 확인·변경한 뒤 GNUCMS에 저장하고 다시 인증해 주세요. 발신프로필 키는 토큰 인증에 사용하지 않습니다.',
            '3008' => '허용된 동시 접속 수를 초과했습니다. 잠시 후 다시 확인해 주세요.',
            '3009' => '계정이 중지 상태입니다. 비즈뿌리오에서 계정 상태를 확인해 주세요.',
            '5002' => 'API 호출 횟수 제한에 도달했습니다. 잠시 후 다시 확인해 주세요.',
            '3011', '5003', '5004', '5005', '9000' => '비즈뿌리오 서버가 처리 오류를 반환했습니다. 잠시 후 다시 확인해 주세요.',
            default => $result['status'] === 200
                ? '인증 응답 형식 또는 토큰 만료 시각을 확인하지 못했습니다. 서버 시각과 API 응답 규격을 확인해 주세요.'
                : '인증 요청이 실패했습니다. 표시된 HTTP 상태와 응답 코드로 비즈뿌리오에 확인해 주세요.',
        };
        if (in_array($code, ['3000', '3003', '3010'], true)) {
            $reason .= ' 웹발송 계정에서 IP 등록을 할 수 없다면 해당 계정의 REST API 사용 가능 여부와 IP 등록 절차를 비즈뿌리오에 문의해 주세요.';
        }
        return new DomainError('BIZPPURIO_AUTH', '비즈뿌리오 인증 실패 (' . ($environment === 'live' ? '운영' : '검수')
            . ' 환경 · HTTP ' . $http . ($code !== '' ? ' · 코드 ' . $code : '') . '). ' . $reason, 503);
    }
}
