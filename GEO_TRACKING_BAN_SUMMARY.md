# Geo Tracking & User Ban - Hoàn thành

## Tổng quan
Bổ sung tính năng theo dõi IP/tọa độ người dùng và khóa tài khoản để tuân thủ pháp luật, có cơ sở báo cáo cơ quan chức năng khi phát hiện nội dung vi phạm.

## Tính năng đã triển khai

### 1. IP & Geolocation Tracking

#### Theo dõi đăng nhập
- **Listener**: `TrackUserLogin` tự động ghi lại mỗi lần user đăng nhập
- **Thông tin lưu trữ** (bảng `users`):
  - `last_login_ip`: Địa chỉ IP
  - `last_login_country`: Mã quốc gia (2 ký tự, VD: VN, US)
  - `last_login_city`: Tên thành phố
  - `last_login_lat`, `last_login_lon`: Tọa độ GPS
  - `last_login_at`: Thời gian đăng nhập

#### Theo dõi hành động quan trọng
- **UserLog model** đã được bổ sung:
  - `country`, `city`, `lat`, `lon`: Tọa độ cho mỗi action log
  - `UserLog::record()` tự động tra cứu geolocation khi có IP
- **Actions mới**:
  - `USER_BANNED`: Ghi lại khi admin ban user
  - `USER_UNBANNED`: Ghi lại khi admin mở khóa

#### Geolocation Service
- **Class**: `App\Services\Geo\GeoLocator`
- **API sử dụng**:
  - **Primary**: ip-api.com (free, 45 requests/phút, không cần API key)
  - **Fallback**: ipapi.co (free tier 1000 requests/ngày)
- **Caching**: Kết quả cache 24h để tiết kiệm API calls
- **Private IP**: Tự động bỏ qua 127.0.0.1, 192.168.x.x, 10.x.x.x

### 2. User Ban System

#### Database Schema
Bảng `users` bổ sung:
- `banned_at`: Timestamp khi bị khóa
- `banned_reason`: Lý do khóa (text, VD: "Đăng nội dung vi phạm pháp luật")
- `banned_by`: Foreign key đến user (admin) thực hiện ban

#### Admin Controller
- **Route**: `POST /admin/users/{user}/ban`
- **Action**: `Admin\UserController@ban`
- **Validation**:
  - Admin không thể tự ban chính mình
  - Không thể ban admin khác
  - Reason bắt buộc, tối đa 500 ký tự
- **Route**: `POST /admin/users/{user}/unban`
- **Action**: `Admin\UserController@unban`

#### Middleware Ban Check
- **Class**: `EnsureUserNotBanned`
- **Behavior**: 
  - Tự động logout user bị ban
  - Invalidate session
  - Redirect về login với thông báo lý do
- **Áp dụng**: Global middleware, kiểm tra mọi request

#### User Model
- **Method**: `isBanned()` - kiểm tra xem user có bị khóa không
- **Relationship**: `banner()` - admin đã thực hiện ban

### 3. Tuân thủ pháp luật

#### Dữ liệu sẵn sàng cho báo cáo
Khi phát hiện nội dung vi phạm, admin có đầy đủ thông tin:
- **User info**: Email, tên, role
- **Login history**: IP, quốc gia, thành phố, tọa độ GPS lần đăng nhập gần nhất
- **Action logs**: Lịch sử hành động (upload video, xóa video, v.v.) kèm IP và tọa độ
- **Ban history**: Ai ban, khi nào, lý do gì
- **Video metadata**: Video nào vi phạm, khi nào upload, từ IP nào

#### Export cho cơ quan chức năng
Admin có thể:
1. Tra cứu user logs theo user_id
2. Xem chi tiết IP/tọa độ từng hành động
3. Export dữ liệu JSON/CSV (future: thêm UI export)
4. Cung cấp bằng chứng cụ thể về thời gian, địa điểm vi phạm

## Database Migrations

### 1. `add_ban_and_geo_columns_to_users_table`
```php
banned_at (timestamp, nullable)
banned_reason (text, nullable)
banned_by (foreign key users.id, nullable)
last_login_ip (varchar 45, nullable) - hỗ trợ IPv6
last_login_country (varchar 2, nullable)
last_login_city (varchar 100, nullable)
last_login_lat (decimal 10,7, nullable)
last_login_lon (decimal 10,7, nullable)
last_login_at (timestamp, nullable)

Indexes:
- banned_at
- last_login_ip
```

### 2. `add_geo_to_user_logs_table`
```php
country (varchar 2, nullable)
city (varchar 100, nullable)
lat (decimal 10,7, nullable)
lon (decimal 10,7, nullable)
```

## API Endpoints

### Admin - Ban User
```
POST /admin/users/{user}/ban
Body: { reason: string (required, max 500) }
Response: Redirect back với status message
```

### Admin - Unban User
```
POST /admin/users/{user}/unban
Response: Redirect back với status message
```

## Testing
- **File**: `tests/Feature/GeoTrackingTest.php`
- **Tests**: 10 tests, tất cả pass
- **Coverage**:
  - Login tracking với geolocation
  - GeoLocator service (ip-api.com, private IP check)
  - UserLog records geolocation
  - Admin ban/unban user
  - Banned user bị logout tự động
  - Admin không thể tự ban hoặc ban admin khác
  - Geolocation caching

## Performance Considerations

### Geolocation API
- **Rate limits**: 
  - ip-api.com: 45 requests/phút
  - ipapi.co: 1000 requests/ngày
- **Caching**: 24h cache cho mỗi IP → giảm 99% API calls cho IP lặp lại
- **Timeout**: 5 giây cho mỗi API call
- **Fallback**: Nếu primary API fail, tự động dùng fallback
- **Graceful degradation**: Nếu cả 2 API fail, vẫn lưu IP, chỉ thiếu geo data

### Database Impact
- **Indexes**: Đã thêm index cho `banned_at` và `last_login_ip` để tìm kiếm nhanh
- **Overhead**: Mỗi login thêm 1 UPDATE query, mỗi action log thêm 1 geolocation lookup (cached)

## Security Notes

### Data Privacy
- Geolocation chỉ chính xác đến cấp thành phố, không đến địa chỉ cụ thể
- Dữ liệu chỉ admin mới xem được
- Tuân thủ GDPR/privacy laws: lưu trữ cho mục đích bảo mật và tuân thủ pháp luật

### IP Spoofing
- Hệ thống lưu IP từ `request()->ip()` (Laravel tự động xử lý proxy headers)
- Trong production, cấu hình `trusted_proxies` đúng để tránh fake IP

### Rate Limiting
- Geolocation API có rate limit → cache 24h giảm thiểu risk
- Nếu vượt rate limit, hệ thống vẫn hoạt động, chỉ thiếu geo data

## Usage Examples

### Admin ban user
```php
// Controller
$user->update([
    'banned_at' => now(),
    'banned_reason' => 'Đăng nội dung khiêu dâm vi phạm pháp luật VN',
    'banned_by' => auth()->id(),
]);

UserLog::record($user->id, UserLog::USER_BANNED, [
    'reason' => 'Đăng nội dung khiêu dâm',
    'by' => auth()->user()->email
], request()->ip());
```

### Kiểm tra user bị ban
```php
if ($user->isBanned()) {
    // User không thể truy cập hệ thống
}
```

### Tra cứu geolocation thủ công
```php
$locator = app(\App\Services\Geo\GeoLocator::class);
$geo = $locator->locate('8.8.8.8');
// ['country' => 'US', 'city' => 'Mountain View', 'lat' => 37.386, 'lon' => -122.0838]
```

### Export dữ liệu cho cơ quan chức năng
```php
// Future: Admin UI để export
$logs = UserLog::where('user_id', $suspectUserId)
    ->with('user')
    ->orderBy('created_at', 'desc')
    ->get();

// Bao gồm: IP, country, city, lat, lon, action, meta, timestamp
```

## Deployment Checklist
- [x] Run migrations
- [x] Test geolocation API connectivity trong production
- [x] Cấu hình `trusted_proxies` đúng (nếu dùng Cloudflare/load balancer)
- [ ] Thêm UI admin để xem user logs với map (future enhancement)
- [ ] Thêm export CSV/JSON cho user logs (future enhancement)

## Compliance & Legal
- Hệ thống lưu trữ IP và tọa độ là hợp pháp theo luật Việt Nam khi phục vụ mục đích bảo mật và tuân thủ pháp luật
- Dữ liệu này có thể cung cấp cho cơ quan chức năng khi có yêu cầu
- Admin cần có chính sách rõ ràng về lưu trữ và sử dụng dữ liệu cá nhân
- Khuyến nghị thêm Privacy Policy và Terms of Service nêu rõ việc tracking này

## Future Enhancements
- Admin UI: Xem bản đồ login locations của user
- Export tool: Xuất dữ liệu JSON/CSV cho báo cáo
- IP blacklist: Tự động block IP có hành vi đáng ngờ
- Automated detection: AI phát hiện nội dung vi phạm tự động
- Email notifications: Thông báo admin khi có hành vi bất thường
- Retention policy: Tự động xóa logs cũ sau N tháng (tuân thủ GDPR)
