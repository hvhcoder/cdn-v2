/*
 * Player embed: ArtPlayer (giao diện) + hls.js (phát HLS) + loader giải mã segment PNG.
 * Đọc nguồn từ <div id="player" data-src="...m3u8">.
 * Tham số URL: ?autoplay=1 (tự phát, tắt tiếng) · ?muted=1 · ?start=<giây>
 */
(function () {
    'use strict';

    var container = document.getElementById('player');
    var params = new URLSearchParams(location.search);

    function showError(message) {
        var box = document.getElementById('player-error');
        box.textContent = message;
        box.hidden = false;
    }

    if (!window.Artplayer || !window.Hls || !Hls.isSupported()) {
        showError('Trình duyệt này không hỗ trợ phát video (cần Media Source Extensions). Hãy dùng Chrome, Edge, Firefox hoặc Safari mới.');

        return;
    }

    // Ghi lại mọi lỗi hls.js để gỡ lỗi: gõ window.__hlsErrors trong console của trang embed.
    window.__hlsErrors = [];

    function playHls(video, url, art) {
        // Fallback to pixel loader if unified loader not available
        var loaderFn = typeof createPixelHlsLoader !== 'undefined' ? createPixelHlsLoader : null;

        if (!loaderFn) {
            showError('Player loader không khả dụng. Vui lòng clear browser cache (Ctrl+Shift+R) và thử lại.');
            return;
        }

        var hls = new Hls({
            loader: loaderFn(Hls),
            maxBufferLength: 30,
            maxMaxBufferLength: 60,
            startPosition: parseFloat(params.get('start')) || -1,
        });
        var retries = 0;

        hls.on(Hls.Events.ERROR, function (event, data) {
            window.__hlsErrors.push(data.type + '/' + data.details + (data.fatal ? ' (fatal)' : ''));

            if (!data.fatal) {
                return;
            }

            if (data.type === Hls.ErrorTypes.NETWORK_ERROR && retries < 3) {
                retries++;
                hls.startLoad();

                return;
            }

            if (data.type === Hls.ErrorTypes.MEDIA_ERROR && retries < 3) {
                retries++;
                hls.recoverMediaError();

                return;
            }

            art.notice.show = 'Không phát được video. Có thể file đã bị CDN xóa hoặc mạng gặp sự cố.';
            hls.destroy();
        });

        video.addEventListener('playing', function () {
            retries = 0;
        });

        hls.loadSource(url);
        hls.attachMedia(video);

        art.hls = hls;
        art.on('destroy', function () {
            hls.destroy();
        });
    }

    var autoplay = params.get('autoplay') === '1';

    var art = new Artplayer({
        container: container,
        url: container.dataset.src,
        type: 'm3u8',
        customType: { m3u8: playHls },
        lang: 'vi',
        i18n: { vi: window.ARTPLAYER_VI || {} },
        theme: '#35d07f',
        icons: (window.PLAYER_UI || {}).icons,
        controls: window.PLAYER_UI ? [
            { name: 'rewind10', position: 'left', index: 11, html: PLAYER_UI.rewind, tooltip: 'Tua lùi 10 giây', click: function () { art.backward = 10; } },
            { name: 'forward10', position: 'left', index: 12, html: PLAYER_UI.forward, tooltip: 'Tua tới 10 giây', click: function () { art.forward = 10; } },
        ] : [],
        volume: 1,
        muted: autoplay || params.get('muted') === '1',
        autoplay: autoplay,
        autoSize: false,
        autoMini: false,
        playbackRate: true,
        aspectRatio: true,
        setting: true,
        fullscreen: true,
        fullscreenWeb: true,
        pip: true,
        miniProgressBar: true,
        autoPlayback: true,
        fastForward: true,
        lock: true,
        mutex: true,
        backdrop: true,
        playsInline: true,
        hotkey: true,
        moreVideoAttr: { preload: 'metadata' },
    });

    window.__art = art;
}());
