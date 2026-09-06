# KG이니시스 결제 플러그인

PortOne V2의 KG이니시스 채널로 KRW 일반 과세 카드결제와 전체·부분 취소를 제공한다.

- 설정: `/plugins/payment-inicis/settings`
- 서버 계약: `gateway.v1` (`GnuCms\Payment\Gateway`)
- PortOne 상점 ID, 해당 PG의 테스트/운영 채널 키, V2 API Secret, 웹훅 서명 시크릿이 필요하다.
- 관리자에서 데이터 설치 → 설정 저장 → API 실행 허용 순으로 진행한다.
- 설정은 암호화하며 과거 주문의 조회·환불에 필요한 설정 판을 보관한다.
- 복원 후에는 API 실행이 정지된다. 거래를 대조한 뒤 다시 허용한다.

[쇼핑몰 운영 문서](../../docs/shop.md) · [공식 채널 문서](https://developers.portone.io/opi/ko/integration/pg/v2/inicis-v2)
