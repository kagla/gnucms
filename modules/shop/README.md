# 작은 쇼핑몰

상품 옵션 두 종류와 조합별 가격·재고, 회원 주문·배송, 취소·반품·환불·교환, 수량·금액 정산을 제공하는 1인 운영 모듈이다.

관리: `/modules/shop/admin` · 상점: `/modules/shop/catalog`

결제는 `payment-inicis`, `payment-kcp`, `payment-kspay` 중 활성화한 PortOne V2 플러그인을 선택한다. API 실행 전 관리자에서 각 패키지 데이터를 설치하고 테스트/운영 설정을 저장해야 한다.

[설치·운영·오류 복구·정산 기준](../../docs/shop.md)을 참고한다.
