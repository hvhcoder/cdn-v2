// Dùng bởi Pest: node tests/js/decode.cjs <png> <payload.bin|-> [--expect-error]
// Giải mã PNG bằng đúng đoạn JS chạy ở player và so với payload gốc do PHP sinh ra.
const fs = require('fs');
const PixelPng = require('./load.cjs')('public/player/pixel-png.js');

const [, , pngPath, payloadPath, flag] = process.argv;
const expectError = flag === '--expect-error';

(async () => {
    const png = fs.readFileSync(pngPath);

    try {
        const out = await PixelPng.decode(new Uint8Array(png));

        if (expectError) {
            console.error('Mong đợi lỗi nhưng giải mã vẫn thành công.');
            process.exit(1);
        }

        const expected = fs.readFileSync(payloadPath);
        if (Buffer.compare(Buffer.from(out), expected) !== 0) {
            console.error(`Payload khác nhau: nhận ${out.length} byte, mong đợi ${expected.length} byte.`);
            process.exit(2);
        }
        console.log('OK ' + out.length);
    } catch (error) {
        if (expectError) {
            console.log('ERROR ' + error.message);
            process.exit(0);
        }
        console.error(error);
        process.exit(3);
    }
})();
