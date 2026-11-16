/*
 * Custom loader cho hls.js: manifest/level tải như mặc định; segment là ảnh PNG nên được tải về,
 * giải mã (trong Web Worker) ra bytes .ts rồi trả cho hls.js như một segment bình thường.
 *
 * Dùng: new Hls({ loader: createPixelHlsLoader(Hls) })
 */
(function () {
    'use strict';

    var inNode = typeof module === 'object' && module.exports;
    var PixelPng = inNode ? require('./pixel-png.js') : window.PixelPng;
    var scriptSrc = (!inNode && document.currentScript) ? document.currentScript.src : '';
    var BASE = scriptSrc ? scriptSrc.replace(/[^/]*$/, '') : '/player/';

    /** Nhóm worker giải mã; không có Worker (hoặc worker lỗi) thì giải mã ngay trên luồng chính. */
    function DecoderPool(size) {
        this.workers = [];
        this.pending = {};
        this.nextId = 1;
        this.cursor = 0;

        if (typeof Worker === 'undefined') {
            return;
        }

        var self = this;
        try {
            for (var i = 0; i < size; i++) {
                var worker = new Worker(BASE + 'pixel-hls-worker.js');
                worker.onmessage = function (event) {
                    var entry = self.pending[event.data.id];
                    if (!entry) {
                        return;
                    }
                    delete self.pending[event.data.id];
                    event.data.error ? entry.reject(new Error(event.data.error)) : entry.resolve(event.data.buffer);
                };
                worker.onerror = function () {
                    self.workers = [];
                };
                this.workers.push(worker);
            }
        } catch (error) {
            this.workers = [];
        }
    }

    DecoderPool.prototype.decode = function (buffer) {
        if (!this.workers.length) {
            return PixelPng.decode(buffer).then(function (payload) {
                return payload.buffer;
            });
        }

        var self = this;
        var id = this.nextId++;
        var worker = this.workers[this.cursor++ % this.workers.length];

        return new Promise(function (resolve, reject) {
            self.pending[id] = { resolve: resolve, reject: reject };
            worker.postMessage({ id: id, buffer: buffer }, [buffer]);
        });
    };

    function newStats() {
        return {
            aborted: false,
            loaded: 0,
            retry: 0,
            total: 0,
            chunkCount: 0,
            bwEstimate: 0,
            loading: { start: 0, first: 0, end: 0 },
            parsing: { start: 0, end: 0 },
            buffering: { start: 0, first: 0, end: 0 },
        };
    }

    function isMpegTs(bytes) {
        return bytes.length > 188 && bytes[0] === 0x47 && bytes[188] === 0x47;
    }

    function createPixelHlsLoader(Hls) {
        var Base = Hls.DefaultConfig.loader;
        var pool = new DecoderPool(2);

        return class PixelHlsLoader extends Base {
            load(context, config, callbacks) {
                // Chỉ segment (fragment) là PNG; manifest, key, subtitle... đi đường mặc định.
                if (!context.frag || context.keyInfo) {
                    return super.load(context, config, callbacks);
                }

                var self = this;
                var stats = newStats();
                var controller = new AbortController();
                var finished = false;
                var timer = null;

                this.context = context;
                this.stats = stats;
                this.callbacks = callbacks;
                this.controller = controller;
                this.cancel = function () {
                    if (finished) {
                        return false;
                    }
                    finished = true;
                    clearTimeout(timer);
                    stats.aborted = true;
                    controller.abort();

                    return true;
                };

                var fail = function (code, text) {
                    if (finished) {
                        return;
                    }
                    finished = true;
                    clearTimeout(timer);
                    callbacks.onError({ code: code, text: text }, context, null, stats);
                };

                if (config && config.timeout) {
                    timer = setTimeout(function () {
                        if (finished) {
                            return;
                        }
                        finished = true;
                        controller.abort();
                        callbacks.onTimeout(stats, context, null);
                    }, config.timeout);
                }

                stats.loading.start = performance.now();

                fetch(context.url, { signal: controller.signal, mode: 'cors', credentials: 'omit' })
                    .then(function (response) {
                        if (!response.ok) {
                            throw Object.assign(new Error('HTTP ' + response.status), { httpStatus: response.status });
                        }
                        stats.loading.first = performance.now();

                        return response.arrayBuffer();
                    })
                    .then(function (buffer) {
                        stats.loaded = stats.total = buffer.byteLength;
                        stats.loading.end = performance.now();

                        if (isMpegTs(new Uint8Array(buffer))) {
                            return buffer; // segment .ts thường, không phải PNG
                        }

                        stats.parsing.start = performance.now();

                        return pool.decode(buffer);
                    })
                    .then(function (data) {
                        if (finished) {
                            return;
                        }
                        finished = true;
                        clearTimeout(timer);
                        stats.parsing.end = performance.now();
                        callbacks.onSuccess({ url: context.url, data: data }, stats, context, null);
                    })
                    .catch(function (error) {
                        if (finished) {
                            return;
                        }
                        fail(error.httpStatus || 0, error.message || 'Tải segment thất bại');
                    });
            }

            abort() {
                if (this.cancel) {
                    var wasActive = this.cancel();
                    if (wasActive && this.callbacks && this.callbacks.onAbort) {
                        this.callbacks.onAbort(this.stats, this.context, null);
                    }

                    return;
                }

                super.abort();
            }

            destroy() {
                if (this.cancel) {
                    this.cancel();
                }
                this.callbacks = null;
                super.destroy();
            }
        };
    }

    if (inNode) {
        module.exports = { createPixelHlsLoader: createPixelHlsLoader };
    } else {
        window.createPixelHlsLoader = createPixelHlsLoader;
    }
}());
