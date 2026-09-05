# Kaiyo Web - Quy trình và chức năng hiện tại

**Cập nhật:** 2026-09-05  
**Mục đích:** tài liệu để giới thiệu rõ website hiện có gì, hoạt động theo quy trình nào và đâu là phần chưa được phép coi là hoàn tất production.

> Đây là trạng thái theo code, route, test và Execution Master Plan hiện tại. Không khẳng định website đã production-ready, đã có provider thanh toán/vận chuyển thật hoặc đã có AI.

## 1. Website này dùng để làm gì?

Kaiyo Web là website B2B/B2C cho sản phẩm HVAC, ống gió, miệng gió, van gió và các sản phẩm liên quan. Hệ thống có bốn khu vực chính:

1. **Website công khai:** khách xem sản phẩm, nội dung, dự án, tìm kiếm, giỏ hàng, checkout, yêu cầu báo giá và liên hệ.
2. **Tài khoản khách hàng:** khách quản lý hồ sơ, địa chỉ, đơn hàng, báo giá, yêu thích, đánh giá, bảo mật và thông báo.
3. **Sales CRM:** nhân viên Sales xử lý khách hàng, lead, công ty, báo giá, đơn hàng và yêu cầu hủy đơn theo quyền được cấp.
4. **Admin:** quản trị catalog, media, nội dung/CMS, review, merchant, analytics, audit và outbox theo quyền/2FA.

## 2. Tóm tắt để giới thiệu nhanh

> Kaiyo Web hiện là nền tảng bán hàng và báo giá HVAC có catalog sản phẩm/biến thể, quản lý tồn kho, giỏ hàng, checkout, đơn hàng, báo giá B2B, CRM Sales, CMS/SEO và quản trị nội bộ. Hệ thống kiểm soát quyền phía server, bắt buộc 2FA cho staff, lưu audit, chống tạo trùng đơn/thanh toán/webhook, và có Redis queue/outbox cho các tác vụ không đồng bộ. Thanh toán online, vận chuyển/carrier, Merchant/Analytics cụ thể và AI chưa được bật vì còn chờ provider và môi trường production được phê duyệt.

## 3. Luồng khách hàng công khai

### 3.1 Khám phá sản phẩm

Khách chưa cần đăng nhập có thể:

- Vào trang chủ.
- Xem danh mục, thương hiệu, dự án và nội dung giới thiệu.
- Tìm kiếm sản phẩm.
- Mở trang chi tiết sản phẩm theo slug.
- Xem media, ảnh/video được quản trị gắn với sản phẩm.
- Xem thông số kỹ thuật, biến thể, trạng thái bán và thông tin phù hợp với website công khai.
- Truy cập URL cũ; hệ thống có cơ chế chuyển hướng slug cũ sang URL hiện hành.

Các URL công khai hiện có gồm: `/`, `/tim-kiem`, `/du-an`, `/danh-muc/{slug}`, `/thuong-hieu/{slug}`, `/san-pham/{slug}`, `/bai-viet/{slug}`, `/cau-hoi-thuong-gap`.

### 3.2 Giỏ hàng

Khách có thể thêm/xóa dòng hàng và làm mới giỏ. Hệ thống hỗ trợ:

- Nhận diện giỏ khách vãng lai bằng định danh được bảo vệ.
- Gộp giỏ xác định khi khách đăng nhập.
- Kiểm tra lại giá và tồn kho trước khi tạo cam kết thương mại.
- Không tin giá hoặc tổng tiền do trình duyệt gửi lên.

### 3.3 Đặt hàng/checkout

Checkout hiện có phần lõi nghiệp vụ:

1. Khách đăng nhập, chọn địa chỉ và các dữ liệu checkout hợp lệ.
2. Server tính lại giá, kiểm tra tồn kho và áp dụng chính sách có hiệu lực.
3. Hệ thống tạo order, snapshot giá/dòng hàng/địa chỉ và reservation tồn kho trong transaction.
4. Gửi lại cùng idempotency key không tạo order thứ hai.
5. Payment và Shipping được tạo theo cổng nghiệp vụ trung lập provider.

**Lưu ý:** chưa bật cổng thanh toán online hoặc carrier cụ thể. Các provider này chỉ được cấu hình khi có hợp đồng/provider binding được phê duyệt.

### 3.4 Yêu cầu báo giá B2B

Khách có thể gửi yêu cầu báo giá tại `/bao-gia`.

- Guest quote có chống spam/rate-limit và đường truy cập an toàn.
- Báo giá có revision, trạng thái và snapshot giá.
- Revision đã chấp nhận không được sửa.
- Quy trình discount/approval tôn trọng ngưỡng và separation of duties.
- Quote được chấp nhận chỉ có thể chuyển thành một order, không tạo trùng khi bấm/submit đồng thời.

### 3.5 Liên hệ, analytics consent và SEO

- Có form liên hệ với kiểm soát chống lạm dụng.
- Analytics chỉ được ghi nhận khi server có bằng chứng consent hợp lệ; không dùng consent do client tự tuyên bố.
- Có canonical URL, robots.txt, sitemap.xml, structured data cho product và redirect slug cũ.
- Nội dung quan trọng được render theo mô hình SSR để bot tìm kiếm đọc được.

## 4. Luồng tài khoản khách hàng

Sau đăng nhập và xác minh email, khách có khu vực `/account` để:

- Cập nhật hồ sơ.
- Thêm/sửa/xóa địa chỉ.
- Xem đơn hàng của chính mình và gửi yêu cầu hủy nếu đơn ở trạng thái phù hợp.
- Xem báo giá của chính mình.
- Thêm/xóa sản phẩm yêu thích.
- Gửi review cho sản phẩm đã mua theo điều kiện kiểm tra phía server.
- Xem thông báo trong hệ thống và đánh dấu đã đọc.
- Quản lý tùy chọn nhận thông báo.
- Xem thiết bị/session đăng nhập và thu hồi session của chính mình.
- Quản lý bảo mật và 2FA khi tài khoản là staff.

Khách không thể truy cập đơn hàng, báo giá, địa chỉ hoặc dữ liệu của người khác bằng cách sửa URL trực tiếp.

## 5. Luồng Sales CRM

Nhân viên Sales phải đăng nhập, xác minh email, bật 2FA và có permission phù hợp. Website không cấp quyền dựa vào việc ẩn/hiện nút giao diện.

### Sales có thể làm gì?

- Xem danh sách khách hàng theo phạm vi được cấp.
- Xem Customer 360 trong phạm vi được cấp.
- Xem/tạo/sửa lead; chuyển lead theo permission và quy trình nghiệp vụ.
- Xem/tạo công ty, xem thành viên và capability theo phạm vi được cấp.
- Xem danh sách báo giá và đơn hàng khi có `quotes.read`/`orders.read`.
- Xem và quyết định yêu cầu hủy đơn khi có quyền high-impact `orders.cancel_decide`.

### Các quyền Sales quan trọng

| Nghiệp vụ | Permission ví dụ |
| --- | --- |
| Xem khách hàng | `crm.customers.read` |
| Tạo/sửa khách hàng | `crm.customers.create`, `crm.customers.update` |
| Xem/tạo/sửa lead | `crm.leads.read`, `crm.leads.create`, `crm.leads.update` |
| Chuyển lead | `crm.leads.convert` |
| Công ty | `crm.companies.read`, `crm.companies.create`, `crm.companies.update` |
| Quản lý thành viên công ty | `crm.companies.manage_members` |
| Xem báo giá/đơn hàng | `quotes.read`, `orders.read` |
| Quyết định hủy đơn | `orders.cancel_decide` |

Permission gắn với tài khoản, không gắn với mật khẩu. Nhân viên Sales đổi mật khẩu vẫn giữ quyền; hiện cần tiếp tục bổ sung cơ chế thu hồi tất cả session cũ khi đổi/reset mật khẩu.

## 6. Luồng Admin

Admin cũng cần đăng nhập, xác minh email, bật 2FA và có permission phía server.

### 6.1 Catalog và sản phẩm

Admin có thể quản lý:

- Category và Brand.
- Product, Variant/SKU và trạng thái bán.
- Thông số kỹ thuật sản phẩm.
- Ảnh, video, tài liệu/media gắn với sản phẩm.
- Slug/redirect để giữ SEO khi URL thay đổi.

Catalog có ràng buộc SKU/slug và trạng thái để tránh dữ liệu công khai không hợp lệ. Khi sản phẩm/variant chưa active, website công khai không được coi đó là hàng đang bán.

### 6.2 CMS và nội dung

Admin có thể quản lý Page, Article, FAQ, Banner và Email Template:

- Tạo nội dung và revision.
- Gắn/gỡ media được kiểm soát.
- Publish, unpublish hoặc schedule publish.
- Scheduler xử lý publication idempotent để retry không làm sai trạng thái.

### 6.3 Review, Merchant, Analytics và audit

- Moderation review.
- Theo dõi/khởi chạy Merchant batch theo cơ chế provider-neutral, retry có kiểm soát.
- Xem Analytics delivery đã được consent và giảm thiểu dữ liệu nhạy cảm.
- Xem audit authorization và outbox ở khu vực private; payload nhạy cảm không được hiển thị nguyên bản.

## 7. Các nghiệp vụ lõi được bảo vệ

### Giá và khuyến mại

- Pricing engine chạy phía server.
- Quy tắc ưu tiên xác định, V1 không cộng dồn tùy tiện.
- Rounding theo currency precision; snapshot giá trên order/quote không tự thay đổi theo giá mới.
- UI, controller hoặc AI không được tự tính giá authoritative.

### Tồn kho

- Có warehouse, stock balance, stock movement và reservation.
- Tồn khả dụng = tồn thực tế trừ reservation đang active.
- B2C reserve khi tạo order; B2B reserve khi quote-to-order.
- Commit khi dispatch; release theo cancellation/expiry hợp lệ.
- V1 không cho backorder và không cho available stock âm.
- Duplicate release/commit phải vô hại; parallel reservation không được oversell.

### Đơn hàng, thanh toán và giao hàng

- Order state machine chặn transition không hợp lệ.
- Order/address/line snapshot là immutable.
- Payment có attempt, transaction, event, refund và reconciliation model.
- Webhook được thiết kế để kiểm tra chữ ký, deduplicate và xử lý thứ tự đến không chắc chắn.
- Shipping có shipment/item/tracking abstraction và manual flow.
- Named gateway/carrier chưa tích hợp/bật chính thức.

### Sự kiện và tác vụ nền

- Các fact event chạy qua MySQL transactional outbox.
- Consumer có retry/idempotency/dead-letter state.
- Search, notification, Merchant/Analytics intent là consumer/đầu ra có kiểm soát.
- `commerce.order.placed` vẫn có consumer purpose chưa chốt, nên không được tự ý thêm consumer.

## 8. Bảo mật và phân quyền

- Authentication gồm register/login/verify/reset password, disabled-account check, throttle và staff 2FA.
- RBAC dùng permission + scope phía server; role chỉ là bundle, không phải bypass quyền.
- Direct HTTP request và cross-resource access bị kiểm tra ở server.
- High-impact action có separation of duties/approval khi chính sách yêu cầu.
- Có audit record, correlation ID và session registry.
- Response có security headers cơ bản, private area có `Cache-Control: no-store, private`.
- Source-secret scan, Composer audit, config audit và test security được đưa vào quality pipeline.

## 9. Vận hành kỹ thuật hiện có

| Thành phần | Trạng thái hiện tại |
| --- | --- |
| Database | MySQL là nguồn dữ liệu chính; schema qua Laravel migrations |
| Cache/session/queue | Redis; cần worker chạy để xử lý job nền |
| Health | `/up`, `/ready`, `operations:health` |
| Quan sát outbox/growth | `outbox:status`, `growth:delivery-status` |
| Baseline local | `performance:baseline` chỉ đo dependency local, không phải load test production |
| CI | GitHub Actions quality workflow, SQLite/MySQL/Redis test gates |
| DR | Có plan và fail-closed evidence gate; chưa có restore drill/provider-backed evidence |

Các lệnh vận hành chi tiết xem [README](./README.md).

## 10. Những gì chưa được coi là hoàn thành

Các hạng mục sau **không nên giới thiệu là đã production-ready**:

1. **Thanh toán online/cổng payment thật:** chưa có provider credential/contract được bật.
2. **Carrier/vận chuyển thật, thuế, invoice provider:** chưa có binding production cụ thể.
3. **Email/SMS/push provider thật:** local dùng log hoặc adapter/provider-neutral.
4. **Merchant/GA4/GTM destination thật:** chỉ có processing và consent-safe contract; provider/staging evidence còn thiếu.
5. **Load test production-like, CWV field data, browser E2E đầy đủ:** còn cần môi trường staging và dữ liệu đại diện.
6. **Dashboard/alert provider, rollout/rollback và DR restore drill:** chưa có account/provider/restore evidence production.
7. **AI Sales/Support/RAG/Agent:** là V2, hiện chưa triển khai và không là dependency của Commerce.
8. **KPI/system admin hoàn chỉnh, SEO landing pages và một số browser UI gate:** vẫn đang tiếp tục.

## 11. Quy trình demo đề xuất

Khi cần demo website hiện tại, có thể đi theo thứ tự:

1. Trang chủ → danh mục → sản phẩm → chọn biến thể/media/thông số.
2. Thêm giỏ → đăng nhập → checkout; giải thích giá/tồn kho được server xác nhận lại.
3. Tạo yêu cầu báo giá → xem revision/quote; giải thích approval và quote-to-order chống trùng.
4. Đăng nhập Customer → xem order, quote, địa chỉ, wishlist, notification và security session.
5. Đăng nhập Sales → khách hàng, lead, công ty, quote, order; giải thích scope/permission/2FA.
6. Đăng nhập Admin → catalog/product/variant/media, CMS, review, merchant/analytics/audit.
7. Mở `/ready` và chạy `php artisan operations:health --json` để minh họa health vận hành.
8. Nêu rõ các provider thực và production launch chỉ được bật sau các gate còn thiếu.

## 12. Trạng thái tổng quát

Phần domain core từ Foundation đến Cart/Checkout/Order/Payment provider-neutral/Shipping provider-neutral/Quotation/Quote-to-Order đã có kiểm thử tự động. Frontend public, customer, Sales, Admin, CMS, SEO và Growth đã có các lát cắt hoạt động nhưng còn browser/provider/staging gate. Operations/CI/DR hiện mới có baseline và fail-closed gate, chưa phải bằng chứng launch production.

Nguồn trạng thái chính thức: [Execution Master Plan](./planManh.md).

## 13. Bản đồ chi tiết các trang và dữ liệu nhập

Phần này mô tả các trường dữ liệu nghiệp vụ mà hệ thống đang nhận và kiểm tra ở server. Các `operation key`, `idempotency key`, phiên bản bản ghi và token truy cập là dữ liệu kỹ thuật do hệ thống/giao diện tạo để chống submit trùng hoặc ghi đè dữ liệu; người dùng không cần tự nhập.

### 13.1 Trang công khai

| Trang | Có gì để xem/thao tác | Dữ liệu người dùng nhập/chọn |
| --- | --- | --- |
| Trang chủ `/` | Banner, định hướng danh mục, sản phẩm/nội dung/dự án được public | Không bắt buộc nhập dữ liệu |
| Tìm kiếm `/tim-kiem` | Danh sách sản phẩm theo từ khóa/bộ lọc được hỗ trợ | Từ khóa tìm kiếm; điều kiện lọc/sắp xếp, nếu giao diện hiển thị |
| Danh mục `/danh-muc/{slug}` | Sản phẩm trong danh mục, điều hướng catalog | Chọn danh mục, trang/phân trang khi có |
| Thương hiệu `/thuong-hieu/{slug}` | Sản phẩm theo thương hiệu | Chọn thương hiệu |
| Chi tiết sản phẩm `/san-pham/{slug}` | Ảnh/video/tài liệu public, mô tả, thông số kỹ thuật, SKU/biến thể, trạng thái bán | Chọn biến thể và số lượng trước khi thêm giỏ/yêu cầu báo giá |
| Giỏ hàng `/gio-hang` | Các dòng hàng, số lượng và trạng thái cần xác nhận lại | Thêm dòng sản phẩm/biến thể, thay số lượng, xóa dòng, làm mới giỏ |
| Liên hệ `/lien-he` | Form gửi yêu cầu cho đội ngũ Kaiyo | **Bắt buộc:** họ tên, chủ đề (`product`, `quotation`, `project`, `support`, `other`), nội dung tối thiểu 20 ký tự, đồng ý privacy. **Một trong hai bắt buộc:** email hoặc số điện thoại. **Tùy chọn:** tên công ty. Trường honeypot chống bot không hiển thị cho người dùng thật. |
| Dự án, bài viết, FAQ, page | Nội dung CMS công khai | Không bắt buộc nhập dữ liệu |

### 13.2 Trang chi tiết sản phẩm và giỏ hàng

Trên sản phẩm, người dùng nhìn thấy dữ liệu do Admin quản trị: tên sản phẩm, mô tả ngắn/chi tiết, category, brand nếu có, ảnh/video/tài liệu, thông số kỹ thuật, biến thể/SKU và trạng thái public.

Khi thêm giỏ, dữ liệu có ý nghĩa là **biến thể** và **số lượng**. Hệ thống không nhận giá do trình duyệt tự gửi. Khi đăng nhập, giỏ guest được gộp theo quy tắc xác định; trước checkout, hệ thống tính lại giá và tồn kho.

### 13.3 Trang checkout `/thanh-toan`

Checkout yêu cầu người dùng đã đăng nhập và có Customer profile active. Nếu đã có địa chỉ mặc định, form có thể lấy trước dữ liệu này.

| Nhóm thông tin | Trường |
| --- | --- |
| Người nhận | **Bắt buộc:** họ tên người nhận. **Tùy chọn:** số điện thoại. |
| Địa chỉ giao hàng | **Bắt buộc:** dòng địa chỉ 1, quốc gia hiện chỉ nhận `VN`. **Tùy chọn:** dòng địa chỉ 2, phường/xã hoặc locality, tỉnh/thành hoặc subdivision, mã bưu chính. |
| Công ty/hóa đơn | **Tùy chọn:** tên công ty, mã số thuế, yêu cầu xuất hóa đơn. Hiện billing address sử dụng cùng address đã xác nhận trong checkout. |
| Giao hàng | **Bắt buộc:** chọn phương thức giao hàng đang được cấu hình và active. |
| Thanh toán | **Bắt buộc:** chọn COD hoặc chuyển khoản ngân hàng. Tùy chọn thanh toán online chỉ xuất hiện khi gateway được cấu hình/cho phép. |
| Xác nhận | Hệ thống tự tạo idempotency key; khi submit, server reprice, kiểm tra stock, tạo reservation/order/payment/shipment snapshot. |

Sau khi đặt, khách được chuyển tới trang hoàn tất order. Nếu giá/tồn kho/phương thức giao hàng thay đổi hoặc dữ liệu không hợp lệ, server từ chối tạo order và trả lỗi để người dùng kiểm tra lại.

### 13.4 Trang yêu cầu báo giá `/bao-gia`

Khách guest hoặc Customer có thể tạo yêu cầu báo giá cho một biến thể.

| Nhóm thông tin | Trường |
| --- | --- |
| Sản phẩm | **Bắt buộc:** biến thể sản phẩm, số lượng. |
| Người nhận/địa chỉ | **Bắt buộc:** họ tên người nhận, dòng địa chỉ 1, quốc gia `VN`. **Tùy chọn:** số điện thoại, locality, subdivision. |
| Giao hàng | **Bắt buộc:** phương thức giao hàng đang active. |
| Nhu cầu thương mại | **Tùy chọn:** ghi chú/yêu cầu báo giá tối đa 2.000 ký tự, yêu cầu xuất hóa đơn. |

Guest nhận quyền xem quote qua session/token bảo vệ. Customer xem quote theo ownership. Người nhận có thể đánh dấu đã xem, chấp nhận hoặc từ chối quote; revision đã chấp nhận là bất biến.

### 13.5 Đăng ký, đăng nhập và tài khoản

| Trang/chức năng | Dữ liệu/thao tác |
| --- | --- |
| Đăng ký | Email, mật khẩu, xác nhận mật khẩu; hệ thống yêu cầu xác minh email. |
| Đăng nhập | Email và mật khẩu; có throttle chống thử mật khẩu liên tục. |
| Quên/đặt lại mật khẩu | Email và mật khẩu mới hợp lệ qua luồng reset an toàn. |
| Hồ sơ Customer `/account` | Tạo/cập nhật tên hiển thị. Hệ thống có version để tránh ghi đè thay đổi đồng thời. |
| Sổ địa chỉ | **Bắt buộc:** nhãn địa chỉ, họ tên người nhận, địa chỉ dòng 1, quốc gia `VN`. **Tùy chọn:** công ty, mã số thuế, dòng địa chỉ 2, locality, subdivision, mã bưu chính, điện thoại, đặt mặc định giao hàng/thanh toán. |
| Đơn hàng | Xem order của chính mình, xem chi tiết và gửi yêu cầu hủy nếu trạng thái cho phép. |
| Wishlist/review | Thêm/xóa sản phẩm yêu thích; review chỉ dành cho điều kiện mua hàng hợp lệ. |
| Bảo mật | Xem session/thiết bị, thu hồi session của mình, quản lý 2FA khi là staff. |
| Thông báo | Xem/đánh dấu đã đọc và cập nhật preference channel được hỗ trợ. |

### 13.6 Sales CRM

| Trang Sales | Có gì | Dữ liệu tạo/cập nhật |
| --- | --- | --- |
| Khách hàng | Danh sách và Customer 360 trong scope | Tìm kiếm theo từ khóa/trạng thái; dữ liệu nhạy cảm chỉ được đọc khi có permission/scope. |
| Lead | Danh sách theo từ khóa/trạng thái `new`, `qualified`, `disqualified`, `converted`; trang chi tiết Lead | **Tạo Lead:** nguồn, tên hiển thị; tùy chọn công ty, email, điện thoại, mã số thuế. **Cập nhật:** trạng thái và nguồn. **Chuyển đổi:** server dùng idempotency key để không tạo Customer/Company trùng. |
| Công ty | Danh sách và chi tiết công ty | **Tạo:** tên pháp lý; tùy chọn tên hiển thị, mã số thuế, nguồn. Mã số thuế không được tự khẳng định là đã xác minh. |
| Thành viên công ty | Xem membership/capability trong scope | Chọn user thành viên và danh sách capability được cấp/thu hồi; cần quyền phù hợp. |
| Báo giá/đơn hàng | Danh sách báo giá và đơn hàng trong phạm vi Sales | Tìm kiếm/lọc theo contract hiện có; chỉ có quyền xem nếu được cấp. |
| Hủy đơn | Danh sách request hủy, quyết định accept/reject theo state machine | Quyết định có quyền `orders.cancel_decide`, xác nhận server-side và audit. |

### 13.7 Admin Catalog và chi tiết sản phẩm

| Nhóm | Dữ liệu Admin nhập/quản lý |
| --- | --- |
| Category | Tên, slug tùy chọn, category cha tùy chọn, thứ tự hiển thị; khi cập nhật có trạng thái active/inactive. |
| Product khi tạo | **Bắt buộc:** tên sản phẩm, category, tên biến thể đầu tiên, SKU biến thể đầu tiên, quantity scale. **Tùy chọn:** slug, brand, mô tả ngắn, mô tả chi tiết, SEO title, SEO description. |
| Product khi cập nhật | Tên, slug, category, brand, trạng thái `draft/active/inactive`, mô tả ngắn/chi tiết, SEO title (tối đa 70 ký tự), SEO description (tối đa 180 ký tự). |
| Variant | **Bắt buộc:** SKU, tên variant, quantity scale. Khi cập nhật chọn trạng thái active/inactive. |
| Specification | Nhãn thông số và giá trị thông số. |
| Media sản phẩm | File JPEG/PNG/WebP/MP4/WebM/PDF, purpose `primary/gallery/video/document`, thứ tự hiển thị. File tối đa 50 MB ở lớp request; media service còn kiểm tra bảo mật/nội dung. |

Product/variant chỉ được public theo trạng thái hợp lệ. Thay đổi slug giữ redirect để hạn chế mất SEO.

### 13.8 Admin CMS

Admin quản lý Page, Article, FAQ, Banner và Email Template theo revision/lifecycle. Các thao tác chính là tạo nội dung, tạo revision, gắn media, publish, unpublish và schedule publish. Nội dung CMS/public media có kiểm soát quyền, trạng thái và lịch để tránh nội dung nháp xuất hiện công khai ngoài ý muốn.

### 13.9 Các trường hệ thống không phải người dùng tự nhập

- `operation_key` / `idempotency_key`: chống double click, retry hoặc request lặp tạo thêm order/quote/lead conversion.
- `expected_version` / `lock_version`: phát hiện người khác đã sửa cùng dữ liệu trước khi bạn bấm lưu.
- `public_id`, token quote, session/cookie: định danh an toàn, không dùng số ID nội bộ.
- Giá cuối, tổng tiền, trạng thái order/quote, stock availability: do server tính/kiểm tra, không tin giá trị client gửi lên.
- Analytics consent ID: chỉ được lấy khi server xác minh evidence consent hợp lệ.
