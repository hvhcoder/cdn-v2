# Troubleshooting: Header Graft Upload & Player Issues

## Issue 1: Upload Fails - FIXED ✅

### Problem
```
UploadSegmentsJob FAIL after 1m 15s
Class "App\Jobs\Log" not found
```

### Root Cause
Thêm `Log::info()` trong UploadSegmentsJob nhưng quên import `use Illuminate\Support\Facades\Log;`

### Fix
```php
// app/Jobs/UploadSegmentsJob.php
use Illuminate\Support\Facades\Log;  // ← Added
```

### How to Apply
```bash
php artisan queue:restart
```

Job sẽ tự động retry và upload segments.

---

## Issue 2: Player Script Error - INVESTIGATING 🔍

### Problem
```
[browser.log] Script error. null 0 0 null
```

### Potential Causes
1. ✅ **Syntax OK** - Both `header-graft-decoder.js` và `unified-hls-loader.js` pass syntax check
2. ⚠️ **Function not found** - `createUnifiedHlsLoader` có thể chưa được export đúng
3. ⚠️ **Load order** - Decoder phải load trước loader

### Current Load Order (embed.blade.php)
```html
<script src="pixel-png.js"></script>                    <!-- PixelPng decoder -->
<script src="header-graft-decoder.js"></script>         <!-- HeaderGraftDecoder -->
<script src="unified-hls-loader.js"></script>           <!-- createUnifiedHlsLoader -->
<script src="player.js"></script>                       <!-- Uses createUnifiedHlsLoader -->
```

Load order **đúng** ✅

### Verification Steps

#### 1. Check if function exists in browser
```javascript
// In browser console
console.log(typeof createUnifiedHlsLoader);  // Should be "function"
console.log(typeof PixelPng);                // Should be "object"
console.log(typeof HeaderGraftDecoder);      // Should be "object"
```

#### 2. Check network tab
- Verify all JS files load successfully (HTTP 200)
- No 404 errors

#### 3. Manual test decode
```javascript
// Test pixel decode
PixelPng.decode(someBytes).then(ts => console.log('Pixel OK', ts.length));

// Test header-graft decode
HeaderGraftDecoder.decode(someBytes).then(ts => console.log('Header-graft OK', ts.length));
```

---

## Status Check Commands

### Check upload progress
```bash
php artisan tinker --execute="\$v = App\Models\Video::latest()->first(); 
echo 'Status: ' . \$v->status . PHP_EOL; 
echo 'Method: ' . \$v->encoding_method . PHP_EOL;
echo 'Uploaded: ' . \$v->segments()->where('status', 'uploaded')->count() . '/' . \$v->segments()->count() . PHP_EOL;"
```

### Watch logs
```bash
tail -f storage/logs/laravel.log | grep "Header Graft\|UploadSegments\|ERROR"
```

### Check queue status
```bash
php artisan queue:work --once --verbose
```

---

## Next Steps

### If Upload Still Fails
1. Check TikTok CDN response:
   ```bash
   tail -200 storage/logs/laravel.log | grep "TikTok\|UploadFailedException"
   ```
2. Verify fake2.png template:
   ```bash
   php artisan tinker --execute="
   \$e = new App\Services\Video\PngHeaderGraftEncoder();
   \$e->loadHeaderFromFile(public_path('fake2.png'));
   echo 'Header: ' . strlen(\$e->getCachedHeader()) . ' bytes';
   "
   ```
3. Test encode manually:
   ```bash
   php artisan tinker --execute="
   \$e = new App\Services\Video\PngHeaderGraftEncoder();
   \$e->loadHeaderFromFile(public_path('fake2.png'));
   \$ts = file_get_contents('path/to/segment_00000.ts');
   \$png = \$e->encode(\$ts);
   file_put_contents('test_encoded.png', \$png);
   echo 'Encoded: ' . strlen(\$png) . ' bytes';
   "
   ```

### If Player Still Fails
1. Hard refresh browser (Ctrl+Shift+R) to clear cache
2. Check browser console for detailed error
3. Verify all scripts load in network tab
4. Test with a video encoded with pixel method (should work with old pixel-hls-loader code)

---

## Rollback Plan (If Needed)

### Rollback Player Only
```blade
<!-- resources/views/embed.blade.php -->
<script src="{{ asset('player/pixel-hls-loader.js') }}"></script>  <!-- Old loader -->
<!-- Remove unified-hls-loader.js and header-graft-decoder.js -->
```

```javascript
// public/player/player.js
loader: createPixelHlsLoader(Hls),  // Revert to old loader
```

This will make **pixel encoding** work again, but **header-graft won't work** until player is fixed.

### Rollback Upload (Prevent Header-Graft Selection)
```blade
<!-- resources/views/videos/create.blade.php -->
<!-- Comment out header-graft radio button -->
```

---

## Expected Timeline

- **Upload fix**: Should work immediately after queue restart (~2-3 minutes)
- **Player fix**: Needs browser cache clear + verification (~5-10 minutes)

Current status: ⏳ Waiting for queue to process segments...
