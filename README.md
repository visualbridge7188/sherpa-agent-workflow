# 🕯️ Duo Obituary KBoard Skin (Duo 부고알림 스킨)

[![Version](https://img.shields.io/badge/version-1.4.1-blue.svg)](https://github.com/visualbridge7188/duo-obituary-kboard)
[![WordPress](https://img.shields.io/badge/WordPress-5.0+-0073aa.svg)](https://wordpress.org)

**Duo 부고알림 스킨**은 워드프레스 KBoard 플러그인을 위한 프리미엄 부고 관리 솔루션입니다. 현대적인 디자인 감각과 고성능 애니메이션 기술을 결합하여, 경건하고 품격 있는 부고 알림 서비스를 제공합니다.

---

## 🌟 주요 특징 (Key Features)

### 1. 고성능 심리스 무한 롤링 (Infinite Rolling Widget)
- **Web Animations API 도입**: CPU 점유율을 최소화하면서 부드러운 수직 롤링을 구현했습니다.
- **스마트 일시정지**: 마우스를 올리거나 검색 시 애니메이션이 즉시 중단되어 가독성을 확보합니다.
- **끊김 없는 루프**: 데이터 개수에 상관없이 마지막 항목과 첫 항목이 완벽하게 연결됩니다.

### 2. 스마트 검색 및 필터링
- **실시간 상주/고인 검색**: 대규모 부고 현황에서도 이름만으로 즉시 조문 대상을 찾을 수 있습니다.
- **자동 노출 관리**: 발인 일시가 지난 부고는 목록에서 자동으로 제외되어 항상 최신 정보만 유지합니다.

### 3. 반응형 및 현대적 디자인
- **모바일 최적화**: 화면이 작은 모바일에서도 제목(헤더)이 고정되어 정보 확인이 용이합니다.
- **프리미엄 UI**: 부드러운 그림자(Soft Shadow), 둥근 모서리, 절제된 색상을 사용하여 경건한 분위기를 조성했습니다.

---

## 🛠️ 설치 및 설정 가이드 (Getting Started)

### 1. 설치 방법
1. 본 레퍼지토리의 `dist/duo-obituary-kboard.zip` 파일을 다운로드합니다.
2. 워드프레스 관리자 > 플러그인 > 새로 추가 > **플러그인 업로드** 메뉴에서 설치 및 활성화합니다.

### 2. KBoard 스킨 설정
1. KBoard 게시판 관리 > 게시판 선택 > **기본설정** 탭으로 이동합니다.
2. **스킨 선택** 항목에서 `Duo 부고알림`을 선택하고 저장합니다.

### 3. 필수 옵션 필드 설정
게시판의 **게시글 표시 옵션**에서 다음 필드들이 활성화되어 있는지 확인하세요:
- `deceased_name` (고인명)
- `chief_mourner` (상주명)
- `funeral_date` (발인일시)
- `affiliation` (소속/호실)

---

## 📊 숏코드 활용 (Usage)

메인 페이지나 사이드바에 부고 현황 롤링 위젯을 표시하려면 다음 숏코드를 사용하세요:

```php
[kboard_latest id="1" url="부고게시판주소" rpp="100"]
```
*   `id`: 해당 게시판의 ID
*   `rpp`: 롤링에 포함할 최대 개수 (안정적인 롤링을 위해 100 권장)

---

## 📂 프로젝트 구조 (Structure)

- `/plugins/duo-obituary-kboard`: 플러그인 전체 소스 코드
  - `/skins/duo-obituary-kboard`: KBoard 스킨 파일 (PHP, JS, CSS)
- `/docs`: 사용자 매뉴얼 및 가이드
- `/dist`: 배포용 Zip 파일

---

## 📄 라이선스 (License)
본 프로젝트는 커스텀 제작된 스킨으로, 무단 전재 및 재배포를 금합니다. 

---
**Developed with Antigravity Sherpa.**  
사용자의 사고를 확장하고 가치를 더하는 코딩 파트너와 함께 제작되었습니다.
