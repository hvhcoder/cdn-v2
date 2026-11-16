/*
 * Giải mã segment .ts được giấu trong pixel của ảnh PNG RGB 8-bit (PixelPngCodec phía server).
 *
 * Khung dữ liệu: magic "PXTS"(4) | version(1) | payload_length(4, BE) | crc32(4, BE) | payload | padding.
 * Không dùng canvas getImageData (có thể bị color management làm sai byte): tự đọc chunk PNG, inflate,
 * bỏ filter từng dòng (đủ 5 loại vì CDN/thư viện khác có thể re-encode), kiểm magic + CRC rồi cắt đúng payload.
 *
 * Dùng được trong trang, Web Worker (importScripts) và Node (require).
 */
(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory(root);
    } else {
        root.PixelPng = factory(root);
    }
}(typeof self !== 'undefined' ? self : this, function (root) {
    'use strict';

    var SIGNATURE = [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a];
    var MAGIC = [0x50, 0x58, 0x54, 0x53]; // "PXTS"
    var HEADER_SIZE = 13;
    var BPP = 3;

    var crcTable = null;

    function crc32(bytes) {
        if (!crcTable) {
            crcTable = new Uint32Array(256);
            for (var n = 0; n < 256; n++) {
                var c = n;
                for (var k = 0; k < 8; k++) {
                    c = (c & 1) ? (0xedb88320 ^ (c >>> 1)) : (c >>> 1);
                }
                crcTable[n] = c >>> 0;
            }
        }

        var crc = 0xffffffff;
        for (var i = 0; i < bytes.length; i++) {
            crc = crcTable[(crc ^ bytes[i]) & 0xff] ^ (crc >>> 8);
        }

        return (crc ^ 0xffffffff) >>> 0;
    }

    function startsWith(bytes, prefix) {
        if (bytes.length < prefix.length) {
            return false;
        }
        for (var i = 0; i < prefix.length; i++) {
            if (bytes[i] !== prefix[i]) {
                return false;
            }
        }

        return true;
    }

    function isPng(bytes) {
        return startsWith(bytes, SIGNATURE);
    }

    function readChunks(bytes) {
        var view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
        var offset = 8;
        var width = 0;
        var height = 0;
        var parts = [];
        var total = 0;

        while (offset + 12 <= bytes.length) {
            var length = view.getUint32(offset);
            var type = String.fromCharCode(bytes[offset + 4], bytes[offset + 5], bytes[offset + 6], bytes[offset + 7]);
            var data = bytes.subarray(offset + 8, offset + 8 + length);
            offset += 12 + length;

            if (type === 'IHDR') {
                width = view.getUint32(offset - 12 - length + 8);
                height = view.getUint32(offset - 12 - length + 12);
                var depth = data[8];
                var colorType = data[9];
                var interlace = data[12];
                if (depth !== 8 || colorType !== 2 || interlace !== 0) {
                    throw new Error('PNG không phải RGB 8-bit không interlace (CDN đã đổi định dạng).');
                }
            } else if (type === 'IDAT') {
                parts.push(data);
                total += data.length;
            } else if (type === 'IEND') {
                break;
            }
        }

        if (!width || !height || !total) {
            throw new Error('PNG thiếu IHDR hoặc IDAT.');
        }

        var idat = new Uint8Array(total);
        var pos = 0;
        parts.forEach(function (part) {
            idat.set(part, pos);
            pos += part.length;
        });

        return { width: width, height: height, idat: idat };
    }

    function inflate(zlibBytes) {
        if (typeof DecompressionStream !== 'undefined') {
            var stream = new Blob([zlibBytes]).stream().pipeThrough(new DecompressionStream('deflate'));

            return new Response(stream).arrayBuffer().then(function (buffer) {
                return new Uint8Array(buffer);
            });
        }

        if (root.fflate && root.fflate.unzlibSync) {
            return Promise.resolve(root.fflate.unzlibSync(zlibBytes));
        }

        return Promise.reject(new Error('Trình duyệt thiếu DecompressionStream và fflate.'));
    }

    function paeth(a, b, c) {
        var p = a + b - c;
        var pa = Math.abs(p - a);
        var pb = Math.abs(p - b);
        var pc = Math.abs(p - c);

        return (pa <= pb && pa <= pc) ? a : (pb <= pc ? b : c);
    }

    /** Bỏ filter từng dòng (None/Sub/Up/Average/Paeth), trả về RGB thô của toàn ảnh. */
    function unfilter(raw, width, height) {
        var rowBytes = width * BPP;
        if (raw.length < height * (rowBytes + 1)) {
            throw new Error('Dữ liệu ảnh bị thiếu.');
        }

        var out = new Uint8Array(height * rowBytes);

        for (var y = 0; y < height; y++) {
            var src = y * (rowBytes + 1);
            var filter = raw[src];
            var o = y * rowBytes;
            var prev = o - rowBytes;
            src += 1;
            var i;

            switch (filter) {
                case 0:
                    out.set(raw.subarray(src, src + rowBytes), o);
                    break;
                case 1:
                    for (i = 0; i < rowBytes; i++) {
                        out[o + i] = (raw[src + i] + (i >= BPP ? out[o + i - BPP] : 0)) & 0xff;
                    }
                    break;
                case 2:
                    for (i = 0; i < rowBytes; i++) {
                        out[o + i] = (raw[src + i] + (y > 0 ? out[prev + i] : 0)) & 0xff;
                    }
                    break;
                case 3:
                    for (i = 0; i < rowBytes; i++) {
                        var left = i >= BPP ? out[o + i - BPP] : 0;
                        var up = y > 0 ? out[prev + i] : 0;
                        out[o + i] = (raw[src + i] + ((left + up) >> 1)) & 0xff;
                    }
                    break;
                case 4:
                    for (i = 0; i < rowBytes; i++) {
                        var a = i >= BPP ? out[o + i - BPP] : 0;
                        var b = y > 0 ? out[prev + i] : 0;
                        var c = (i >= BPP && y > 0) ? out[prev + i - BPP] : 0;
                        out[o + i] = (raw[src + i] + paeth(a, b, c)) & 0xff;
                    }
                    break;
                default:
                    throw new Error('Filter PNG không hợp lệ: ' + filter);
            }
        }

        return out;
    }

    /**
     * @param {ArrayBuffer|Uint8Array} input bytes của file PNG
     * @returns {Promise<Uint8Array>} payload .ts gốc
     */
    function decode(input) {
        var bytes = input instanceof Uint8Array ? input : new Uint8Array(input);

        if (!isPng(bytes)) {
            return Promise.reject(new Error('Không phải file PNG.'));
        }

        var png;
        try {
            png = readChunks(bytes);
        } catch (error) {
            return Promise.reject(error);
        }

        return inflate(png.idat).then(function (raw) {
            var frame = unfilter(raw, png.width, png.height);

            if (frame.length < HEADER_SIZE || !startsWith(frame, MAGIC)) {
                throw new Error('Sai magic của khung dữ liệu.');
            }

            var view = new DataView(frame.buffer, frame.byteOffset, frame.byteLength);
            var length = view.getUint32(5);
            var crc = view.getUint32(9);

            if (HEADER_SIZE + length > frame.length) {
                throw new Error('Độ dài payload vượt quá ảnh.');
            }

            var payload = frame.slice(HEADER_SIZE, HEADER_SIZE + length);

            if (crc32(payload) !== crc) {
                throw new Error('Sai CRC: ảnh đã bị CDN làm hỏng.');
            }

            return payload;
        });
    }

    return { decode: decode, isPng: isPng, crc32: crc32 };
}));
