# Roadmap — CDN Video Host (bản cho thuê)

Nguyên tắc: **làm đơn giản**. UI tối giản (Blade + Tailwind, ít Livewire/Alpine, không dashboard rườm rà), mỗi phase chạy được và test được trước khi sang phase sau.

## Quyết định mặc định (sửa nếu muốn khác)

| Câu hỏi | Mặc định |
|---|---|
| Video thuộc mấy folder | **Một folder** (`videos.folder_id`, nullable) — đơn giản hơn pivot |
| Cổng thanh toán | **SePay/VietQR chuyển khoản** (webhook idempotent). Giai đoạn đầu cho admin cấp gói thủ công |
| Cookie TikTok | **Pool của admin**, khách không nhập |
| Hết hạn gói | Về Free, khóa embed, giữ video 30 ngày rồi xóa (config) |
| Bản dự phòng trên R2 | Không giữ ở MVP; thêm cờ theo gói ở phase sau |
| CDN phụ | Chỉ TikTok + R2, nhưng qua interface `CdnUploader` |

## UI đơn giản — chỉ các trang này

1. Login / Register (+ nút Google)
2. **Videos**: bảng + nút Upload + chọn folder + copy link
3. **Folders**: cây folder ở sidebar trái, danh sách video bên phải
4. **Gói của tôi** + Trang giá
5. Admin: Users, Plans, Orders, TikTok accounts (mỗi cái 1 bảng + form)
6. Embed player (trang trắng + video)

---

## Phase 0 — Spike kiểm chứng (BLOCKER) — XONG, kết quả PASS
Chạy bằng `php artisan spike:pixel-png --sizes=1,3,8,32` (dùng cookie TikTok đang bật trong `/admin/upload-configs`).
- [x] Tạo PNG RGB hợp lệ chứa dữ liệu ngẫu nhiên, upload lên TikTok
- [x] Tải lại URL, so hash → **byte giống hệt 100%** (1, 3, 8, 32 MB), content-type `image/png`
- [x] Giới hạn: ảnh 1024x10923 px / 32 MB vẫn được nhận. 1 lần 16 MB bị timeout 45s (lỗi mạng thoáng qua, 32 MB sau đó vẫn OK) → cần retry/backoff, giữ segment ≤ ~10 MB
- [x] CORS: CDN trả `Access-Control-Allow-Origin: *` → player tải trực tiếp, **không cần proxy**
- [x] Tốc độ: encode 3 MB ≈ 170 ms, 8 MB ≈ 0.5-0.8 s (PHP); player giải mã bằng JS trong Web Worker
- **Kết luận:** hướng PNG pixel dùng được, không cần fallback. Segment 5s ~1-3 MB là dư dả.

## Phase 1 — Nền tảng & Auth
- [x] Pest chạy được; MySQL (`cdnpro`) cho dev, test dùng SQLite memory; Redis để sau
- [x] Auth email/password (Breeze-style, Blade) — giữ tối giản
- [x] Bảng `roles` (admin/user/vip), `users.role_id` FK
- [x] Google login (Socialite): `google_id`, `avatar`, `password` nullable; chỉ liên kết khi email Google đã verified
- [x] Layout Blade chung + Tailwind
- [x] Test: callback Google (tạo mới / liên kết / từ chối email chưa verify)

## Phase 2 — Gói & Quota
- [x] Migration/model: `plans`, `subscriptions`
- [x] Seed 3 gói: Free / Pro / Business
- [x] User mới tự động dùng Free (fallback trong QuotaService, không cần dòng subscription)
- [x] `QuotaService` duy nhất (dung lượng, số video, kích thước upload, phút encode)
- [x] Command schedule hằng ngày: hết hạn → expired → về Free
- [x] Trang "Gói của tôi" (thanh dùng/giới hạn) + trang giá
- [x] Admin: CRUD plan, cấp/gia hạn gói thủ công
- [x] Test: QuotaService, hết hạn

## Phase 3 — Lõi video (port từ dự án cũ)
Mỗi service có interface + test.
- [x] `R2Service`: presigned PUT/GET/DELETE, download
- [x] `FfmpegService`: probe, needsTranscode, convertToHls
- [x] `PixelPngCodec`: encode/decode, CRC, test từng byte (cả kích thước không chia hết, CRC sai)
- [x] `CdnUploader` interface + `TikTokUploader` (stream file, verify SSL, retry backoff, ghi `video_segments` ngay khi xong)
- [x] `UploadConfig` (R2 + TikTok, config_data mã hóa) + `AccountPool` (round-robin, đánh dấu chết)
- [x] Bảng `videos`, `video_segments`
- [x] Jobs: `EncodeVideoJob` → `UploadSegmentsJob` (chia lô, resume) → `FinalizeVideoJob`; `tries` > 1 + backoff
- [x] Kiểm quota ở bước cấp presigned URL và đầu job
- [x] Route m3u8 động (đọc `video_segments`, CORS, cache) — không ghi vào `public/`
- [x] Test: parse response TikTok, policy tenant (A không đọc được video B)

Ghi chú: upload segment lên TikTok chạy song song (`TIKTOK_CONCURRENCY`, mặc định 6 ảnh/lô); giới hạn hàng đợi cấu hình bằng `UPLOAD_MAX_*` trong .env (5 GB/video, 15 GB/hàng đợi, 20 video/hàng đợi, 5 video/user); R2 gốc được xóa ngay khi hoàn tất + dọn bù mỗi giờ (`videos:cleanup-r2`). Presigned PUT đơn (chưa multipart nên trần ~4.99 GB/video). Cần cài FFmpeg + điền `R2_*` trong `.env` + thêm tài khoản TikTok ở `/admin/accounts`.

## Phase 4 — XONG
- [x] `public/player/pixel-png.js` + `pixel-hls-worker.js` + `pixel-hls-loader.js`: tự parse PNG, inflate (DecompressionStream, fallback fflate), bỏ filter 5 loại, kiểm magic + CRC, giải mã trong Web Worker (2 worker), segment .ts thường vẫn phát được
- [x] Trang `/embed/{video}` (public, iframe) + hls.js 1.5.8, tách khỏi app chính; tham số `?autoplay=1&muted=1&start=<giây>`
- [x] Proxy CDN: không cần (Phase 0: CDN trả `Access-Control-Allow-Origin: *`)
- [x] Nút "Xem thử" / "Sao chép embed" (iframe) ở trang Video
- [x] Test: decoder JS chạy bằng Node trên dữ liệu PHP mã hóa (mọi kích thước, 6 kiểu filter, CRC sai), loader hls.js với Hls/fetch giả; đã giải mã thử ảnh thật trên CDN TikTok

## Phase 5 — UI quản lý Video & Folder
- [x] Trang Videos: upload nhiều file có hàng đợi (browser → R2), tiến độ xử lý, xóa, copy link m3u8 (chỉ VIP/Admin upload)
- [ ] Folder: CRUD, lồng nhau, chống vòng lặp, breadcrumb
- [ ] Move / bulk move video, copy hàng loạt link của cả folder
- [ ] Xóa folder: hỏi xóa video hay chỉ gỡ khỏi folder
- [ ] Test: policy folder, chống vòng lặp

## Phase 6 — Thanh toán
- [ ] `orders` (unique `provider_txn_id`), webhook SePay xác thực chữ ký, idempotent
- [ ] Thanh toán xong → tạo/gia hạn subscription
- [ ] Admin: danh sách đơn, doanh thu
- [ ] Test: webhook gọi 2 lần không cấp gói 2 lần, sai chữ ký bị từ chối

## Phase 7 — Hardening
- [ ] Khóa domain embed + token ký có hạn
- [ ] Health check tài khoản TikTok, thống kê upload
- [ ] Phát hiện URL chết → re-upload
- [ ] Rate limit theo user, `user_logs`
- [ ] Horizon, báo cáo/gỡ nội dung vi phạm
- [ ] Chính sách hết hạn: khóa embed + xóa sau N ngày

---

## Rủi ro cần nhớ
- Dùng TikTok Ads CDN để lưu video vi phạm điều khoản TikTok → có thể chết hàng loạt; luôn giữ `CdnUploader` đổi được.
- Video khách có thể vi phạm → cần điều khoản + cơ chế gỡ.
- R2 tính phí lưu trữ + FFmpeg tốn CPU → giá gói phải bù.
