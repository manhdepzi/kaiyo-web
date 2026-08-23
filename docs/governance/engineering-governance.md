# AI Engineering Governance

**Status:** Active governance

**Revalidated against:** AI Enterprise Full-Stack Engineering Promptbook v2.0 on 2026-08-23

## 1. Mục đích và phạm vi

Tài liệu này quy định cách AI coding agent làm việc trong repository. Tài liệu không cấp cho agent quyền tự tạo business rule, dữ liệu, permission, public contract hoặc workflow. Mọi thay đổi phải có căn cứ, có phạm vi rõ ràng và có bằng chứng kiểm chứng.

Governance này áp dụng cho mọi task do AI thực hiện, gồm phân tích, thiết kế, code, migration, test, tài liệu, hạ tầng và AI Platform.

## 2. Source of truth

Khi các nguồn có nội dung khác nhau, áp dụng theo thứ tự ưu tiên từ cao xuống thấp:

1. Approved Business Rules / Product Requirements
2. Architecture Decision Records (ADRs)
3. Database / API / Event contracts
4. Architecture Specification
5. Existing production code
6. Current task prompt

Agent phải ghi nhận và báo cáo xung đột giữa các nguồn. Nguồn có ưu tiên thấp hơn không được dùng để âm thầm ghi đè nguồn có ưu tiên cao hơn. Production code hiện hữu không tự động trở thành business rule nếu mâu thuẫn với yêu cầu đã được phê duyệt.

## 3. Non-invention và STOP / REPORT GAP

Agent không được tự ý tạo hoặc suy diễn:

- Business rule hoặc acceptance criterion.
- Database table, field, relation, constraint, state hoặc state transition.
- Role, permission, authorization behavior hoặc security exception.
- Public API, event, webhook hoặc compatibility contract.
- Destructive workflow, retention policy hoặc data-recovery behavior.
- Prompt, model ID, provider key, threshold hoặc business setting được hard-code.

Khi thiếu hoặc xung đột thông tin cần thiết, agent phải:

1. **STOP**: không triển khai phần phụ thuộc vào quyết định chưa được phê duyệt.
2. **REPORT GAP**: mô tả chính xác thông tin còn thiếu, nguồn đã kiểm tra và ảnh hưởng đến task.
3. Nêu các lựa chọn khả thi cùng trade-off, rủi ro và ảnh hưởng tương thích/dữ liệu.
4. Chờ người có thẩm quyền quyết định trước khi tiếp tục phần bị chặn.

Agent vẫn có thể hoàn thành các phần độc lập, an toàn và không phụ thuộc vào gap, nhưng phải tách boundary rõ ràng và không thể hiện phần bị chặn là đã hoàn thành.

## 3.1 Approval evidence

Một nội dung chỉ được coi là **approved** khi có record xác định được phạm vi quyết định, người/nhóm có thẩm quyền, ngày quyết định và revision/artifact bị ảnh hưởng. Yêu cầu “tiếp tục làm” hoặc sự tồn tại của một draft không tự động phê duyệt business rule, schema, permission, contract hay destructive workflow.

Approval có phạm vi: quyết định cho một rule/operation không được suy rộng sang rule/operation khác. Khi approval thay đổi, các artifact phụ thuộc phải quay lại trạng thái revalidate trước khi tiếp tục.

## 3.2 Artifact dependency gate

- Mỗi Promptbook step chỉ được bắt đầu implementation khi artifact dependency của step trước đã đạt DoD áp dụng và các approval bắt buộc đã có evidence.
- Artifact ở trạng thái proposed, unapproved, failed hoặc pending không mở gate cho code/migration/public contract phụ thuộc.
- Cross-cutting test, security, observability, CI/CD và disaster-recovery controls phải được bootstrap sớm, cập nhật cùng từng module và chỉ đóng gate bằng release evidence.
- Nếu thứ tự đánh số task mâu thuẫn dependency kỹ thuật, giữ Step ID để truy vết nhưng thực thi theo dependency; ghi rõ lý do và gate chưa đóng.

## 4. Operating loop bắt buộc

### OBSERVE

- Đọc repository, tài liệu, ADR, contracts, tests và implementation hiện tại.
- Xác định working-tree state và bảo toàn thay đổi không liên quan của người dùng.
- Truy vết requirement đến source of truth có ưu tiên cao nhất.

### PLAN

- Ghi rõ scope, out of scope, files, dependencies và rules áp dụng.
- Xác định data/schema/API/event changes, authorization boundary và compatibility impact.
- Đánh giá rủi ro, migration/rollback, concurrency/idempotency, security, performance và test plan.
- Dừng theo STOP / REPORT GAP nếu kế hoạch cần quyết định chưa được phê duyệt.

### IMPLEMENT

- Thực hiện thay đổi nhỏ, có boundary rõ và tránh scope expansion.
- Giữ controller mỏng; đặt business behavior trong domain/action phù hợp.
- Authorization phải được thực thi server-side.
- MySQL là source of truth cho order, payment và inventory; Redis không được giữ dữ liệu chuẩn duy nhất.
- Critical writes phải transaction-safe; retryable writes phải idempotent.
- Queue jobs phải retry-safe; external API phải có timeout và backoff.
- Không đưa AI/LLM vào critical checkout, payment hoặc inventory path.
- AI Platform là bounded context tùy chọn và độc lập. Catalog, cart, checkout, quotation thủ công, order, payment và inventory phải hoạt động khi toàn bộ AI feature flags tắt hoặc mọi LLM provider outage.
- AI tool write action phải qua policy, validation và idempotency; high-impact action cần human approval.
- Không hard-code prompts, model IDs, provider keys, thresholds hoặc business settings.
- Modular Monolith là mặc định; không tách microservice nếu chưa có ADR phê duyệt.

### TEST

- Chạy test phù hợp với rủi ro: unit, feature, permission, concurrency, idempotency, integration và E2E.
- Bổ sung regression test cho behavior được thay đổi hoặc lỗi được sửa.
- Không mô tả test là pass nếu chưa chạy; phải ghi rõ skipped, unavailable hoặc failed.

### VERIFY

- Chạy build, lint/static analysis và automated tests liên quan.
- Kiểm tra migration, constraints, query plan/index, N+1, security và performance.
- Kiểm tra accessibility, SEO/SSR/indexation/schema cho bề mặt public liên quan.
- Kiểm tra secrets/PII, monitoring/alerting, deployment, backup/restore và rollback theo phạm vi/rủi ro.
- Nếu thay đổi AI Platform, chạy AI evaluation và safety regression.

### REPORT

- Tóm tắt diff theo file/boundary và mapping với acceptance criteria.
- Liệt kê lệnh/test đã chạy cùng kết quả thực tế.
- Báo trạng thái từng Global DoD item: **PASS**, **FAIL**, **PENDING** hoặc **N/A** kèm bằng chứng/lý do.
- Nêu gaps, remaining risks, known limitations và rollback/deployment notes.
- Xác nhận không mở rộng scope; nếu có thay đổi scope đã được duyệt, dẫn lại quyết định.

## 5. Change-size policy

- Mỗi change phải nhỏ nhất có thể nhưng vẫn tạo thành một đơn vị đúng, kiểm thử được và rollback được.
- Không trộn refactor, formatting diện rộng, dependency upgrade hoặc cleanup không cần thiết vào feature/fix đang làm.
- Thay đổi lớn phải được chia theo boundary và thứ tự dependency; mỗi phần phải có test và trạng thái riêng.
- Khi cần sửa ngoài scope để hoàn thành task, agent phải báo dependency và xin phê duyệt nếu việc đó làm thay đổi requirement, contract, kiến trúc hoặc rủi ro đáng kể.
- Không sửa hoặc xóa thay đổi hiện có của người dùng nếu chúng không thuộc task.

Không đặt ngưỡng số dòng/file cố định khi chưa có policy được phê duyệt. Reviewer đánh giá kích thước dựa trên cohesion, blast radius, khả năng review, test và rollback.

## 6. Destructive-change approval

Destructive change gồm nhưng không giới hạn ở xóa/ghi đè dữ liệu, drop/rename schema, irreversible migration, purge queue/storage, thay đổi state hàng loạt, force push/reset hoặc phá vỡ public contract.

Trước destructive change, agent phải:

1. Xác định chính xác target và blast radius bằng kiểm tra read-only.
2. Báo tác động, khả năng phục hồi, backup requirement, rollback/restore plan và downtime nếu có.
3. Nhận phê duyệt rõ ràng cho đúng operation và target.
4. Thực hiện guard/check và xác minh kết quả sau thay đổi.

Task prompt chung không được hiểu là phê duyệt cho destructive operation chưa được nêu rõ. Không có phê duyệt thì dừng và REPORT GAP.

## 7. Migration policy

- Mọi schema/data migration phải xuất phát từ contract hoặc requirement đã được phê duyệt; agent không tự tạo table/field/state.
- Ưu tiên migration backward-compatible, deploy theo nhiều bước khi ứng dụng cũ và mới có thể chạy đồng thời.
- Migration phải đánh giá lock time, table size, index cost, default/backfill behavior và production execution plan.
- Constraint và index phải phản ánh integrity rule/access path đã được phê duyệt; không suy diễn nghiệp vụ từ tên field.
- Data backfill phải có thể resume/retry an toàn, có batching phù hợp và kiểm chứng count/integrity.
- Phải có rollback plan. Nếu rollback không an toàn hoặc migration irreversible, phải báo rõ và nhận phê duyệt trước.
- Critical migration yêu cầu backup/restore verification và rehearsal theo môi trường/quy trình được phê duyệt.

## 8. Secrets và PII policy

- Không hard-code, commit, log, chụp màn hình hoặc đưa vào prompt/output: secret, credential, token, private key, connection string hoặc dữ liệu PII không cần thiết.
- Chỉ dùng secret thông qua cơ chế cấu hình/secret manager đã được phê duyệt; repository chỉ chứa placeholder an toàn.
- Log, fixture, test artifact và error report phải redact dữ liệu nhạy cảm.
- Dùng dữ liệu synthetic hoặc đã anonymize cho test khi có thể.
- Nếu phát hiện secret/PII bị lộ: dừng lan truyền, không lặp lại giá trị, báo vị trí/phạm vi và yêu cầu quy trình revoke/rotate/incident response phù hợp.

## 9. Review gates

Một thay đổi chỉ sẵn sàng để merge/deploy khi vượt qua các gate áp dụng:

1. **Requirements gate**: scope và acceptance criteria truy vết được; không còn gap làm thay đổi behavior.
2. **Architecture/contracts gate**: phù hợp ADR, architecture và DB/API/Event contracts; thay đổi contract đã được phê duyệt và tài liệu hóa.
3. **Code review gate**: boundary rõ, controller mỏng, authorization server-side, không scope expansion hoặc N+1 đã biết.
4. **Data/critical-flow gate**: integrity, transaction, concurrency, race condition, retry và idempotency được kiểm thử theo rủi ro.
5. **Quality gate**: automated tests/E2E, accessibility, SEO và performance checks áp dụng đã đạt.
6. **Security/privacy gate**: không còn Critical/High unresolved; không lộ secrets/PII.
7. **Operations gate**: monitoring/alerting, backup/restore, deployment và rollback/runbook đã được xác minh khi áp dụng.
8. **AI safety gate**: AI evaluation/safety regression đạt nếu thay đổi AI Platform; high-impact writes có human approval.
9. **Documentation gate**: ADR, API/Event catalog và tài liệu vận hành được cập nhật khi bị ảnh hưởng.

Gate không áp dụng phải được đánh dấu **N/A** cùng lý do. Gate chưa có bằng chứng là **PENDING**, không phải **PASS**. Không được merge/deploy khi có gate bắt buộc đang FAIL hoặc PENDING nếu chưa có quyết định chấp nhận rủi ro từ người có thẩm quyền.

## 10. Global Definition of Done

Global Definition of Done là review gate cuối và phải được tham chiếu trong task template cũng như báo cáo bàn giao. Không được đánh dấu hoàn thành nếu thiếu bằng chứng.

- [ ] Functional requirements/acceptance criteria pass
- [ ] Authorization & permission tests pass
- [ ] Database constraints/integrity pass
- [ ] Race condition & idempotency tests pass cho critical flows
- [ ] Automated tests + E2E critical flows pass
- [ ] Accessibility critical checks pass
- [ ] SEO/indexation/schema checks pass
- [ ] Performance budget & load test pass
- [ ] Security review: không Critical/High unresolved
- [ ] Secrets/PII không bị lộ
- [ ] Backup và restore đã test
- [ ] Monitoring + alerting active
- [ ] Rollback/deployment runbook verified
- [ ] Documentation/ADR/API/Event catalog cập nhật
- [ ] Không có known data corruption issue
- [ ] AI evaluation/safety regression pass nếu thay đổi AI Platform

Mỗi mục phải được báo cáo là **PASS**, **FAIL**, **PENDING** hoặc **N/A**, kèm bằng chứng hoặc lý do. Việc một mục không thuộc phạm vi thay đổi không mặc nhiên là PASS.

## 11. Tài liệu task chuẩn

Mọi AI task phải được khởi tạo và bàn giao bằng [AI Task Template](./ai-task-template.md). Template là record tối thiểu; team có thể bổ sung thông tin nhưng không được bỏ các mục bắt buộc Scope, Files, Dependencies, Rules, Risks, Test, Verification và Diff.

## 12. Promptbook v2.0 compliance record

Revalidation ngày 2026-08-23 xác nhận tài liệu này và task template bao phủ:

- Source-of-truth precedence và non-invention.
- STOP → REPORT GAP → options/trade-offs → approval wait.
- OBSERVE → PLAN → IMPLEMENT → TEST → VERIFY → REPORT.
- Change-size, destructive-change, migration, secrets/PII và review gates.
- Artifact dependency gate giữa các phase.
- Modular Monolith, MySQL truth, transaction/idempotency, retry-safe queue/integration, SSR SEO, server authorization và query/index controls.
- AI bounded-context/outage isolation, governed writes và configuration policy.
- Global Definition of Done và evidence-based reporting.

Không có quyền implementation mới được tạo bởi lần revalidation này; mọi product/data/contract decision vẫn theo source-of-truth và approval gate.
