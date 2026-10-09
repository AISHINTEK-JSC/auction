# Chợ Đấu Giá (auction-fe)

Giao diện và kho dữ liệu người dùng của sàn đấu giá. App này giữ **user, tin đăng, ảnh, danh mục, bình luận**; mọi nghiệp vụ đấu giá (giá, lượt đặt, thời gian, người thắng) do **auction engine** ở `../auction/sourcecode` xử lý và được gọi qua HTTP từ phía server.

Thay thế cho `../auction_laravel` (Laravel 5.8 + Vue 2).

## Công nghệ

| Lớp      | Dùng                                                                                         |
| -------- | -------------------------------------------------------------------------------------------- |
| Backend  | PHP 8.3+, Laravel 13, Fortify (đăng nhập, đăng ký, xác thực email, 2FA, passkey)             |
| Frontend | Inertia 3 + Vue 3 + TypeScript, Tailwind 4, shadcn-vue, Wayfinder (route TS tự sinh), Tiptap |
| Quản trị | Filament 5 tại `/admin`                                                                      |
| Dữ liệu  | MySQL 8.4 khi chạy Docker, SQLite khi chạy trực tiếp trên máy                                |
| Kiểm thử | PHPUnit, PHPStan (Larastan), Pint, vite-plus lint                                            |

## Chạy bằng Docker

Yêu cầu: Docker Desktop, và engine đang chạy ở cổng 8801 (`docker compose up -d` trong `../auction/sourcecode`).

```bash
cp .env.example .env      # rồi điền AUCTION_ENGINE_TOKEN và AUCTION_ENGINE_PUSH_KEY (xem bên dưới)
make setup                # build image, composer/npm install, migrate + seed, build asset
make dev                  # (tuỳ chọn) Vite dev server có HMR ở cổng 5173
```

| Địa chỉ                     |                                           |
| --------------------------- | ----------------------------------------- |
| http://localhost:8802       | Ứng dụng                                  |
| http://localhost:8802/admin | Trang quản trị                            |
| http://localhost:8027       | Mailpit: email xác thực, đặt lại mật khẩu |
| `localhost:33062`           | MySQL (`auction_fe` / `secret`)           |

Đổi cổng bằng `APP_PORT`, `VITE_PORT`, `MAILPIT_PORT`, `FORWARD_DB_PORT`.

Service: `app` (php-fpm) · `nginx` · `scheduler` (chạy `auctions:sync` mỗi phút) · `queue` (gửi email trong hàng đợi) · `mysql` · `mailpit` · `vite` (chỉ khi `make dev`).

### Tài khoản seed (mật khẩu `password`)

| Email              | Vai trò   |
| ------------------ | --------- |
| admin@example.com  | Quản trị  |
| seller@example.com | Người bán |
| buyer@example.com  | Người mua |

Seeder không tạo tin đăng: mỗi tin cần một phiên đấu giá thật trong engine.

## Chạy trực tiếp trên máy (không Docker)

Chọn một trong hai cách chạy cho mỗi bản checkout: `node_modules` chứa binary theo nền tảng, nên `npm install` trong container và trên macOS sẽ ghi đè lẫn nhau.

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed && php artisan storage:link
composer run dev          # php artisan serve + queue + vite
php artisan schedule:work # ở terminal khác, để đồng bộ phiên đấu giá
```

Khi chạy kiểu này, `APP_URL` và `php artisan serve --port` phải khớp nhau, và engine phải gọi được webhook của app (xem dưới).

## Kết nối với auction engine

Engine là hệ thống đa tenant: mỗi app tiêu thụ là một **client** có API token riêng.

1. Đăng nhập trang quản trị của engine (http://localhost:8801), tạo client mới với:
    - `push_url`: `http://host.docker.internal:8802/webhooks/auction`
    - `push_api_key`: một chuỗi bí mật tự đặt
2. Chép `api_token` của client vào `AUCTION_ENGINE_TOKEN`, và `push_api_key` vào `AUCTION_ENGINE_PUSH_KEY`.

| Biến                            | Ý nghĩa                                                                                                                                                                           |
| ------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `AUCTION_ENGINE_URL`            | Gốc API của engine, ví dụ `http://localhost:8801/api/v1`. Trong Docker, compose tự đổi thành `http://host.docker.internal:8801/api/v1` (ghi đè bằng `AUCTION_ENGINE_DOCKER_URL`). |
| `AUCTION_ENGINE_TOKEN`          | API token của client. Là bí mật của cả tenant: chỉ dùng phía server, không bao giờ đưa xuống trình duyệt.                                                                         |
| `AUCTION_ENGINE_PUSH_KEY`       | Khoá engine gửi kèm webhook (`Authorization: Bearer …`).                                                                                                                          |
| `AUCTION_ENGINE_CALCULATE_TYPE` | Cách tính giá hiển thị của đấu giá xuôi: `2` = bằng giá đặt cao nhất (mặc định), `1` = giá cao thứ nhì + bước giá.                                                                |

### Dữ liệu đi như thế nào

```
Trình duyệt ──Inertia──▶ auction-fe ──HTTP + Bearer token──▶ engine /api/v1
                             ▲                                   │
                             └──── webhook GET /webhooks/auction ┘
```

- **Đăng tin**: lưu listing + ảnh + thẻ trong một transaction, gọi `POST /auction` với `seller_id` = id user, `item_id` = id listing, rồi lưu `auction_id`. Engine từ chối thì không giữ lại gì.
- **Đặt giá**: `POST /bid` với `bidder_id` = id user đang đăng nhập.
- **Snapshot**: bảng `listings` có các cột `auction_status`, `current_price`, `bid_count`, `ends_at`, `winner_id`, `winning_price` là bản sao trạng thái của engine. Trang danh sách đọc snapshot nên lọc, sắp xếp, phân trang bằng SQL. Snapshot được cập nhật khi đăng tin, đặt giá, huỷ phiên, nhận webhook, mở trang chi tiết, và mỗi phút bởi `php artisan auctions:sync`.
- **Trang chi tiết**: đọc engine trực tiếp qua cache 3 giây và tự cập nhật bằng polling 5 giây. Engine giới hạn 1000 request/phút theo IP và mọi người dùng đi chung IP của server này, nên đừng bỏ lớp cache.
- **Webhook**: chỉ dùng để biết phiên nào vừa thay đổi; trạng thái luôn được đọc lại từ engine, không lấy từ payload.

### Những điều cần biết về engine

- Engine luôn trả HTTP 200; thành công hay không nằm ở trường `success`. `App\Services\AuctionEngine\AuctionEngineClient` xử lý việc này và dịch mã lỗi `E_xxx` sang tiếng Việt (`lang/vi/engine.php`).
- Scheduler của engine chạy mỗi phút, nên bắt đầu, kết thúc và giảm giá có thể trễ tới khoảng 60 giây.
- Một lượt đặt giá bị từ chối vẫn có thể gia hạn phiên hoặc nâng giá hiển thị, nên app luôn đồng bộ lại sau mỗi lần đặt giá, kể cả khi lỗi.
- `end_time` của engine không đổi khi phiên được gia hạn; đồng hồ đếm ngược dùng `remaining_time`.
- Hai lỗi của engine đã được vá trong `../auction/sourcecode`, nhưng app vẫn giữ cách xử lý phòng thủ để chạy được với engine chưa vá:
    - Engine cũ gửi webhook trước khi commit trạng thái mới. Nếu phiên đã hết giờ mà engine vẫn báo đang mở, webhook đọc lại engine thêm một lần sau khi đã trả lời (cần php-fpm; với `php artisan serve` thì `auctions:sync` sẽ cập nhật trong vòng một phút).
    - Engine cũ lỗi khi lượt đặt giá không kèm giá (đặt nhanh, mua ngay) thiếu trường `bid_price`. Client luôn gửi `bid_price = 0`, engine tự tính lại giá.

## Tính năng

**Tin đăng**

- Đăng tin đấu giá xuôi / ngược; **sửa** nội dung, ảnh và thẻ sau khi đăng (giá và thời gian thuộc về engine, không sửa được).
- **Đăng lại** phiên kết thúc không có người thắng hoặc tin bị từ chối: tạo tin mới, ảnh được chép từ tin cũ.
- Trang theo danh mục `/c/{slug}`, theo thẻ `/t/{slug}`, tìm kiếm `/search`, và trang công khai của thành viên `/u/{id}`.

**Đấu giá**

- Đặt giá, đặt nhanh, mua ngay; **huỷ lượt đặt** của mình khi phiên còn chạy.
- **Bước giá** do người bán đặt cho đấu giá xuôi (tuỳ chọn). Engine không có khái niệm này nên app tự kiểm tra: giá gõ tay phải đạt "giá hiện tại + bước giá", nút đặt nhanh đặt đúng mức đó; lượt mở đầu được phép bằng giá khởi điểm. Tin không đặt bước giá thì chỉ cần cao hơn giá hiện tại.
- **Theo dõi** phiên; trang "Phiên đấu giá của tôi" có các tab Đang bán, Đang tham gia (kèm đang dẫn đầu / bị vượt giá), Theo dõi, Đã thắng, Đã kết thúc, Chờ duyệt.
- Khi phiên kết thúc, người bán và người thắng thấy **thông tin liên hệ** của nhau, **đánh giá** nhau (1–5 sao), và một trong hai có thể ghi nhận **người thắng từ bỏ**: engine chuyển kết quả cho người đặt giá cao kế tiếp, không còn ai thì huỷ phiên.

**Thông báo** (chuông trên header + email): bị vượt giá, thắng / thua, phiên của mình có người thắng hoặc không, người thắng từ bỏ, tin được duyệt / từ chối / ẩn, trúng quà tặng. Email được gửi ngay trong request, trừ thông báo quà tặng mới gửi cho toàn bộ thành viên: loại này đi qua hàng đợi nên cần service `queue` (hoặc `php artisan queue:work` khi chạy trực tiếp). Khi chạy Docker xem email ở Mailpit.

**Kiểm duyệt**

- Thành viên **báo cáo** tin vi phạm; kiểm duyệt viên ẩn tin hoặc bỏ qua.
- Tuỳ chọn **duyệt tin trước khi hiển thị** (nút trên trang Tin đấu giá của admin, mặc định tắt). Khi bật, tin mới ở trạng thái "Chờ duyệt" và **chưa có phiên trong engine**; phiên chỉ được mở lúc duyệt. Nếu giờ bắt đầu đã qua thì phiên bắt đầu ngay; nếu giờ kết thúc đã qua thì không duyệt được, cần từ chối để người bán đăng lại.
- Ẩn tin không dừng phiên trong engine; dùng "Huỷ phiên" nếu cần.

**Quà tặng** (`/giveaways`)

- Admin tạo chương trình (quà, số người trúng, hạn đăng ký) trong trang quản trị. Ngay khi tạo, mọi thành viên đã xác thực và không bị khoá nhận thông báo "Quà tặng mới": bản trong app hiện ngay trên chuông, còn email đi qua hàng đợi.
- Mỗi thành viên có một **mã số 6 chữ số cấp một lần khi đăng ký và không đổi**; bấm "Đăng ký tham gia" là vào danh sách, mỗi người một lượt cho mỗi chương trình.
- "Quay thưởng" chọn ngẫu nhiên, chỉ quay một lần; tài khoản bị khoá không trúng. Người trúng được công bố bằng tên và mã số, và nhận thông báo.

**Vai trò**: Thành viên, Kiểm duyệt viên (tin đấu giá, báo cáo, bình luận, yêu cầu mở khoá), Quản trị viên (thêm thành viên, danh mục, chặn IP / email, quà tặng, cài đặt). Đổi vai trò ở trang Thành viên.

## Cấu trúc code

```
app/Services/AuctionEngine/   AuctionEngineClient, EngineException
app/Services/                 ListingSyncService (snapshot + cache trạng thái trực tiếp)
app/Actions/Listings/         CreateListing (đăng tin + mở phiên trong engine)
app/Console/Commands/         auctions:sync
app/Http/Controllers/         Home, Listing, ListingBid, Comment, Dashboard, UnlockRequest, AuctionWebhook
app/Support/                  ListingPresenter, BidPresenter (dữ liệu cho trang Inertia)
app/Filament/Resources/       Trang quản trị
resources/js/pages/           Home, listings/{Index,Show,Create,Bids}, Dashboard, auth/*, settings/*
resources/js/components/marketplace/   SiteHeader, AuctionCard, BidPanel, CommentThread, …
```

## Quản trị

`/admin`, dành cho quản trị viên và kiểm duyệt viên:

- **Bảng điều khiển**: số phiên, phiên thành công, doanh số (tổng giá chốt), người dùng mới, tin chờ duyệt, báo cáo chưa xử lý.
- **Thành viên**: khoá / mở khoá, đổi vai trò, và nút **Chính sách mật khẩu** (độ dài, chữ hoa/thường, chữ số, ký tự đặc biệt) áp dụng cho đăng ký và đổi mật khẩu.
- **Tin đấu giá**: duyệt, từ chối, ẩn, đồng bộ lại với engine, huỷ phiên; bật / tắt duyệt tin trước khi hiển thị.
- **Báo cáo vi phạm**, **Bình luận**, **Yêu cầu mở khoá**, **Danh mục**, **Tặng quà**.
- **IP bị chặn**, **Tên miền email bị chặn**: chặn đăng ký.

Người dùng bị khoá sẽ bị đăng xuất ở request kế tiếp và được chuyển tới `/blocked` để gửi yêu cầu mở khoá.

## Kiểm thử

```bash
composer test         # pint --test, phpstan, phpunit
npm run check         # lint + format frontend
npm run types:check   # vue-tsc
```

Test giả lập engine bằng `Http::fake` (`tests/Feature/Marketplace/EngineFake.php`), không cần engine thật.
