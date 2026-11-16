// Dùng bởi Pest: node tests/js/loader.cjs <good.png> <payload.bin> <tampered.png>
// Kiểm tra custom loader của hls.js với Hls và fetch giả.
const assert = require('node:assert/strict');
const fs = require('fs');
const { createPixelHlsLoader } = require('./load.cjs')('public/player/pixel-hls-loader.js');

const [, , goodPath, payloadPath, tamperedPath] = process.argv;
const goodPng = fs.readFileSync(goodPath);
const payload = fs.readFileSync(payloadPath);
const tamperedPng = fs.readFileSync(tamperedPath);

const calls = { superLoad: 0, fetch: 0 };

class FakeDefaultLoader {
    load() { calls.superLoad++; }
    abort() { calls.superAbort = true; }
    destroy() {}
}
const Hls = { DefaultConfig: { loader: FakeDefaultLoader } };
const PixelHlsLoader = createPixelHlsLoader(Hls);

const toArrayBuffer = (buf) => buf.buffer.slice(buf.byteOffset, buf.byteOffset + buf.byteLength);

function stubFetch(handler) {
    global.fetch = (url, options) => { calls.fetch++; return handler(url, options); };
}

function respond(body, status = 200) {
    return Promise.resolve({ ok: status >= 200 && status < 300, status, arrayBuffer: () => Promise.resolve(toArrayBuffer(body)) });
}

function run(context, config = { timeout: 5000 }, act) {
    return new Promise((resolve) => {
        const loader = new PixelHlsLoader({});
        const result = {};
        const done = (kind) => (...args) => { result.kind = kind; result.args = args; resolve({ result, loader }); };
        loader.load(context, config, {
            onSuccess: done('success'), onError: done('error'), onTimeout: done('timeout'), onAbort: done('abort'),
        });
        if (act) act(loader);
    });
}

const fragment = (url = 'https://cdn.example/seg.png') => ({ frag: { sn: 1 }, url });

(async () => {
    // 1. Segment PNG được giải mã ra đúng bytes .ts
    stubFetch(() => respond(goodPng));
    let { result } = await run(fragment());
    assert.equal(result.kind, 'success');
    assert.equal(Buffer.compare(Buffer.from(result.args[0].data), payload), 0, 'payload phải giống hệt');
    assert.equal(result.args[1].loaded, goodPng.length, 'stats.loaded là dung lượng ảnh tải về');
    assert.ok(result.args[1].loading.end >= result.args[1].loading.start);

    // 2. Manifest/level đi đường mặc định, không gọi fetch
    calls.fetch = 0;
    await new PixelHlsLoader({}).load({ url: 'https://cdn.example/playlist.m3u8' }, {}, {});
    assert.equal(calls.superLoad, 1);
    assert.equal(calls.fetch, 0);

    // 3. Segment .ts thường (không phải PNG) được trả nguyên
    const ts = Buffer.alloc(376, 0);
    ts[0] = 0x47;
    ts[188] = 0x47;
    stubFetch(() => respond(ts));
    ({ result } = await run(fragment()));
    assert.equal(result.kind, 'success');
    assert.equal(Buffer.compare(Buffer.from(result.args[0].data), ts), 0);

    // 4. HTTP lỗi -> onError mang mã trạng thái
    stubFetch(() => respond(Buffer.alloc(0), 404));
    ({ result } = await run(fragment()));
    assert.equal(result.kind, 'error');
    assert.equal(result.args[0].code, 404);

    // 5. Ảnh bị CDN làm hỏng -> onError, không trả dữ liệu sai
    stubFetch(() => respond(tamperedPng));
    ({ result } = await run(fragment()));
    assert.equal(result.kind, 'error');
    assert.match(result.args[0].text, /CRC/);

    // 6. Lỗi mạng -> onError
    stubFetch(() => Promise.reject(new TypeError('Failed to fetch')));
    ({ result } = await run(fragment()));
    assert.equal(result.kind, 'error');
    assert.match(result.args[0].text, /Failed to fetch/);

    // 7. abort() trong khi đang tải -> onAbort duy nhất, không có onSuccess/onError
    stubFetch((url, { signal }) => new Promise((resolve, reject) => {
        signal.addEventListener('abort', () => reject(Object.assign(new Error('aborted'), { name: 'AbortError' })));
    }));
    ({ result } = await run(fragment(), { timeout: 5000 }, (loader) => setTimeout(() => loader.abort(), 10)));
    assert.equal(result.kind, 'abort');
    assert.equal(result.args[0].aborted, true);

    // 8. Quá thời gian -> onTimeout
    stubFetch((url, { signal }) => new Promise((resolve, reject) => {
        signal.addEventListener('abort', () => reject(new Error('aborted')));
    }));
    ({ result } = await run(fragment(), { timeout: 30 }));
    assert.equal(result.kind, 'timeout');

    console.log('LOADER OK');
    process.exit(0);
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
