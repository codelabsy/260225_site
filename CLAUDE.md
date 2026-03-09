# Project Rules

## FTP 배포
- FTP 업로드 시 `data/crm.sqlite` 파일은 절대 업로드하지 않는다. 서버 DB를 덮어쓰면 데이터가 손실된다.
- DB 스키마 변경이 필요한 경우 마이그레이션 SQL을 별도로 실행한다.

## 커밋 메시지
- 형식: `<type>: <설명>` (feat, fix, refactor, style, docs, chore, perf, test)
