/* Web Worker: giải mã PNG -> bytes .ts ngoài luồng giao diện để video không bị giật. */
'use strict';

importScripts('pixel-png.js');

if (typeof DecompressionStream === 'undefined') {
    importScripts('https://cdn.jsdelivr.net/npm/fflate@0.8.2/umd/index.js');
}

self.onmessage = function (event) {
    var id = event.data.id;

    self.PixelPng.decode(event.data.buffer).then(function (payload) {
        self.postMessage({ id: id, buffer: payload.buffer }, [payload.buffer]);
    }).catch(function (error) {
        self.postMessage({ id: id, error: error && error.message ? error.message : String(error) });
    });
};
