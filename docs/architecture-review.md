# Duo 부고알림 Architecture Review

이 문서는 `improve-codebase-architecture` 관점에서 발견한 deepening opportunities를 기록한다. 지금 변경에서는 큰 refactor를 하지 않고, 향후 작업자가 선택할 수 있는 후보를 남긴다.

## 1. 부고 검색 Query Module

**Files**

- `skin/duo-obituary-kboard/functions.php`
- `plugins/duo-obituary-kboard/skins/duo-obituary-kboard/functions.php`
- `tests/smoke.php`

**Problem**

검색 관련 **Implementation**이 KBoard hook 함수 안에 직접 들어 있다. `duo_obituary_add_query_joins()`, `duo_obituary_query_where()`, `duo_obituary_expand_keyword_where()`를 함께 알아야 검색 동작을 이해할 수 있다. 현재 **Interface**는 WordPress filter shape라서 테스트가 실제 도메인인 "표시 컬럼 검색"보다 SQL 문자열 조각에 가까워진다.

**Solution**

`부고 검색 Query Module`을 만들어 표시 컬럼 alias 목록, JOIN 생성, KBoard keyword WHERE 확장을 한 곳에 모은다. 외부 **Interface**는 "board/list/keyword를 받아 JOIN과 WHERE 확장을 반환한다" 정도로 작게 유지한다.

**Benefits**

**Locality**가 좋아진다. 검색 대상 컬럼을 바꿀 때 hook 함수 여러 곳을 뒤지지 않아도 된다. **Leverage**도 생긴다. 일반 목록과 최신글 위젯이 같은 **Module**을 사용하므로 테스트도 "표시 컬럼 전체 검색"이라는 도메인 행위를 직접 검증할 수 있다.

## 2. 원본 스킨과 패키지 스킨 동기화 Module

**Files**

- `scripts/build-plugin.sh`
- `skin/duo-obituary-kboard/*`
- `plugins/duo-obituary-kboard/skins/duo-obituary-kboard/*`
- `tests/smoke.sh`

**Problem**

원본 스킨과 패키지 스킨이 같은 파일을 중복 보관한다. 현재 `build-plugin.sh`가 복사 전 diff를 확인하지만, 변경 작업 중에는 두 디렉터리를 모두 직접 수정해야 해서 **Locality**가 낮다.

**Solution**

배포 전에는 원본 스킨만 수정하고, 패키지 스킨은 빌드 산출물처럼 갱신한다는 규칙을 더 강하게 만든다. `sync-skin` 같은 작은 **Module**을 두면 "비교", "복사", "패키지 문서 포함"이라는 **Implementation**을 한 **Interface** 뒤에 숨길 수 있다.

**Benefits**

변경 지점이 줄어든다. 테스트도 두 디렉터리 전체 diff가 아니라 "sync 후 동일하다"는 한 행위를 검증하면 된다. 패키징 실수도 줄어든다.

## 3. 최신글 Rolling Layout Module

**Files**

- `skin/duo-obituary-kboard/latest.php`
- `skin/duo-obituary-kboard/style.css`
- `skin/duo-obituary-kboard/script.js`
- packaged skin copies

**Problem**

최신글 위젯의 **Interface**가 PHP class, CSS state class, JavaScript DOM convention으로 흩어져 있다. `is-rolling`, `is-rolling-container`, `is-rolling-target`, 검색 상태가 함께 맞아야 하지만 이 invariant가 한 곳에 설명되어 있지 않다.

**Solution**

최신글 rolling 상태를 하나의 **Module**로 다룬다. PHP는 초기 상태와 row count만 제공하고, CSS/JS는 "rolling active일 때만 viewport clipping"이라는 invariant를 명확히 공유한다.

**Benefits**

줄바꿈, 검색, 5개 이하 항목 같은 UI bug의 **Locality**가 좋아진다. 테스트는 CSS 문자열보다 "rolling inactive이면 clipping height가 없다"는 **Interface**를 중심으로 바뀔 수 있다.

## 4. 관리자 문서 Module

**Files**

- `ADMIN_GUIDE.md`
- `README.md`
- `plugins/duo-obituary-kboard/ADMIN_GUIDE.md`
- `scripts/build-plugin.sh`

**Problem**

README는 개발/배포 소개와 관리자 사용법을 함께 담고 있다. 처음 사용하는 관리자는 설치, 부고 등록, 검색, 최신글 위젯, CSV 내보내기 흐름을 한 곳에서 봐야 한다.

**Solution**

`관리자 가이드`를 독립 문서로 유지하고, 패키징 **Module**이 ZIP에 반드시 포함하게 한다. README는 요약과 링크 중심으로 얕게 둔다.

**Benefits**

관리자 onboarding의 **Leverage**가 올라간다. 운영 질문이 생겨도 문서 **Interface**가 작고 명확해진다. smoke test가 ZIP 포함 여부를 검증해 배포 누락을 막는다.

