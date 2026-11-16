/*
 * Unified HLS Loader - Hỗ trợ cả Pixel encoding và Header Graft encoding.
 *
 * Tự động detect encoding method:
 * 1. Thử pixel decode (PXTS format với CRC)
 * 2. Nếu fail → fallback header-graft (quét 0x47 sync byte)
 */
(function () {
    'use strict';

    var inNode = typeof module === 'object' && module.exports;
    var PixelPng = inNode ? require('./pixel-png.js') : window.PixelPng;
    var HeaderGraftDecoder = inNode ? require('./header-graft-decoder.js') : window.HeaderGraftDecoder;

    /**
     * Decode segment với auto-detection.
     */
    function decodeSegment(bytes) {
        // Kiểm tra PNG signature
        if (!PixelPng.isPng(bytes)) {
            return Promise.reject(new Error('Không phải file PNG'));
        }

        // Thử pixel decode trước (PXTS format)
        return PixelPng.decode(bytes)
            .catch(function (pixelError) {
                // Pixel decode fail → thử header-graft
                console.log('Pixel decode failed, trying header-graft:', pixelError.message);
                return HeaderGraftDecoder.decode(bytes);
            });
    }

    /**
     * Create custom HLS loader.
     */
    function createUnifiedHlsLoader(Hls) {
        var DefaultLoader = Hls.DefaultConfig.loader;

        return class UnifiedHlsLoader extends DefaultLoader {
            load(context, config, callbacks) {
                this.context = context;
                this.config = config;
                this.callbacks = callbacks;
                this.stats = { loading: {}, buffering: { start: 0, first: 0, end: 0 } };
                this.cancel = null;

                // Chỉ decode segments, không decode manifest/playlist
                if (context.type !== 'manifest' && context.type !== 'level') {
                    this.loadAndDecode(context, config, callbacks);
                    return;
                }

                // Manifest/level tải bình thường
                super.load(context, config, callbacks);
            }

            loadAndDecode(context, config, callbacks) {
                var self = this;
                var aborted = false;

                this.cancel = function () {
                    aborted = true;
                    return true;
                };

                self.stats.loading.start = performance.now();

                fetch(context.url)
                    .then(function (response) {
                        if (aborted) return;
                        if (!response.ok) {
                            throw new Error('HTTP ' + response.status);
                        }
                        return response.arrayBuffer();
                    })
                    .then(function (buffer) {
                        if (aborted) return;
                        return decodeSegment(new Uint8Array(buffer));
                    })
                    .then(function (tsData) {
                        if (aborted) return;

                        var now = performance.now();
                        self.stats.loading.first = now;
                        self.stats.loading.end = now;
                        self.stats.loaded = tsData.byteLength;
                        self.stats.total = tsData.byteLength;

                        callbacks.onSuccess(
                            { url: context.url, data: tsData.buffer },
                            self.stats,
                            context,
                            null
                        );
                    })
                    .catch(function (error) {
                        if (aborted) return;
                        callbacks.onError(
                            { code: 500, text: error.message || 'Decode failed' },
                            context,
                            null,
                            self.stats
                        );
                    });
            }

            abort() {
                if (this.cancel) {
                    this.cancel();
                }
                if (super.abort) {
                    super.abort();
                }
            }

            destroy() {
                if (this.cancel) {
                    this.cancel();
                }
                this.callbacks = null;
                if (super.destroy) {
                    super.destroy();
                }
            }
        };
    }

    if (inNode) {
        module.exports = { createUnifiedHlsLoader: createUnifiedHlsLoader };
    } else {
        window.createUnifiedHlsLoader = createUnifiedHlsLoader;
    }
}());
