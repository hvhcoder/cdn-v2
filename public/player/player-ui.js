/* Biểu tượng cho giao diện player (nét trắng, bo tròn). Dùng cùng player.css. */
(function () {
    'use strict';

    function svg(size, body, viewBox) {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + size + '" viewBox="' + (viewBox || '0 0 24 24') + '" fill="none" '
            + 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + body + '</svg>';
    }

    /** Nút tua 10 giây: vòng cung có mũi tên kèm chữ "10". */
    function skip(direction) {
        var arc = direction === 'back'
            ? '<path d="M7.5 7.4A10 10 0 1 1 4.6 15.5"/><path d="M7.2 3v4.6h4.6"/>'
            : '<path d="M24.5 7.4A10 10 0 1 0 27.4 15.5"/><path d="M24.8 3v4.6h-4.6"/>';

        return svg(32, arc + '<text x="16" y="21.2" font-size="9.5" font-weight="700" text-anchor="middle" fill="currentColor" stroke="none" font-family="Arial,Helvetica,sans-serif">10</text>', '0 0 32 32');
    }

    var circle = '<circle cx="24" cy="24" r="21.5"/>';

    window.PLAYER_UI = {
        rewind: skip('back'),
        forward: skip('forward'),
        icons: {
            play: svg(44, circle + '<path d="M19.5 16v16l13-8z"/>', '0 0 48 48'),
            pause: svg(44, circle + '<path d="M19 17v14M29 17v14" stroke-width="3.4"/>', '0 0 48 48'),
            volume: svg(28, '<path d="M4 9.5v5h4l5 4v-13l-5 4z"/><path d="M16.5 9a4.2 4.2 0 0 1 0 6"/><path d="M19.2 6.3a8 8 0 0 1 0 11.4"/>'),
            volumeClose: svg(28, '<path d="M4 9.5v5h4l5 4v-13l-5 4z"/><path d="M17 9.5l5 5M22 9.5l-5 5"/>'),
            setting: svg(28, '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>') + '<span class="art-badge">Auto</span>',
            pip: svg(28, '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><rect x="12" y="11" width="7" height="5" rx="1" fill="currentColor"/>'),
            fullscreenOn: svg(28, '<path d="M4 9V5.5A1.5 1.5 0 0 1 5.5 4H9M15 4h3.5A1.5 1.5 0 0 1 20 5.5V9M20 15v3.5a1.5 1.5 0 0 1-1.5 1.5H15M9 20H5.5A1.5 1.5 0 0 1 4 18.5V15"/>'),
            fullscreenOff: svg(28, '<path d="M9 4v3.5A1.5 1.5 0 0 1 7.5 9H4M20 9h-3.5A1.5 1.5 0 0 1 15 7.5V4M15 20v-3.5a1.5 1.5 0 0 1 1.5-1.5H20M4 15h3.5A1.5 1.5 0 0 1 9 16.5V20"/>'),
            fullscreenWebOn: svg(28, '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M9.5 5v14"/><path d="M15 9.5l2.8 2.5-2.8 2.5"/>'),
            fullscreenWebOff: svg(28, '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M9.5 5v14"/><path d="M17.8 9.5L15 12l2.8 2.5"/>'),
            state: '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 100 100">'
                + '<path d="M31 9l11 13M69 9L58 22" stroke="#fff" stroke-width="7" stroke-linecap="round" fill="none"/>'
                + '<rect x="8" y="22" width="84" height="66" rx="17" fill="#fff" fill-opacity=".92"/>'
                + '<path d="M41 42v30l26-15z" fill="#2b2b2b" fill-opacity=".7" stroke="#2b2b2b" stroke-opacity=".7" stroke-width="5" stroke-linejoin="round"/>'
                + '</svg>',
        },
    };
}());
