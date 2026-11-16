/*
 * Header Graft Decoder - Giải mã TS được nhúng bằng cách ghép 188 bytes PNG header.
 *
 * Logic: Quét tìm TS sync byte (0x47) cách nhau đúng 188 bytes để xác định offset bắt đầu TS data.
 * File từ CDN có cấu trúc: [PNG shell] + [junk] + [raw TS bytes]
 */
(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.HeaderGraftDecoder = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    var TS_PACKET_SIZE = 188;
    var TS_SYNC_BYTE = 0x47;
    var PNG_SIGNATURE = [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a];
    var SCAN_LIMIT = 4000; // Quét tối đa 4KB để tìm TS sync

    function isPng(bytes) {
        if (bytes.length < 8) return false;
        for (var i = 0; i < 8; i++) {
            if (bytes[i] !== PNG_SIGNATURE[i]) return false;
        }
        return true;
    }

    /**
     * Tìm offset bắt đầu của TS data bằng cách quét tìm 3 sync bytes liên tiếp.
     *
     * MPEG-TS packet luôn bắt đầu bằng 0x47 và dài 188 bytes.
     * Tìm 3 bytes 0x47 cách nhau đúng 188 bytes → xác định offset.
     */
    function findTsOffset(bytes) {
        var len = bytes.length;

        // Case 1: Byte đầu đã là sync byte → đúng TS packet
        if (len > 0 && bytes[0] === TS_SYNC_BYTE) {
            return 0;
        }

        // Case 2: Offset 188 là sync byte → có đúng 188 bytes header
        if (len > TS_PACKET_SIZE && bytes[TS_PACKET_SIZE] === TS_SYNC_BYTE) {
            return TS_PACKET_SIZE;
        }

        // Case 3: Quét tìm 3 sync bytes liên tiếp cách nhau 188 bytes
        var maxScan = Math.min(len - TS_PACKET_SIZE * 2, SCAN_LIMIT);
        for (var i = 0; i <= maxScan; i++) {
            if (bytes[i] === TS_SYNC_BYTE &&
                bytes[i + TS_PACKET_SIZE] === TS_SYNC_BYTE &&
                bytes[i + TS_PACKET_SIZE * 2] === TS_SYNC_BYTE) {
                return i;
            }
        }

        // Không tìm thấy → trả về 0 (fallback)
        return 0;
    }

    /**
     * Decode segment: tìm TS offset và trả về raw TS bytes.
     */
    function decode(bytes) {
        if (!bytes || bytes.length < TS_PACKET_SIZE) {
            return Promise.reject(new Error('Segment quá nhỏ (< 188 bytes)'));
        }

        // Chỉ xử lý nếu là PNG
        if (!isPng(bytes)) {
            return Promise.reject(new Error('Không phải PNG (header-graft chỉ dùng cho PNG)'));
        }

        var offset = findTsOffset(bytes);
        var tsData = bytes.slice(offset);

        // Validate: TS data phải chia hết cho 188
        if (tsData.length % TS_PACKET_SIZE !== 0) {
            console.warn('TS data không chia hết cho 188 bytes, có thể bị cắt cụt');
        }

        return Promise.resolve(tsData);
    }

    return {
        decode: decode,
        isPng: isPng,
        findTsOffset: findTsOffset
    };
}));
