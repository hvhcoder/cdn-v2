<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $video?->title ?? 'Video' }}</title>
    <style>
        html, body { margin: 0; height: 100%; background: #000; color: #fff; font-family: system-ui, sans-serif; }
        #player { width: 100%; height: 100%; background: #000; }
        .overlay { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; padding: 24px; text-align: center; font-size: 15px; line-height: 1.5; background: rgba(0, 0, 0, .8); }
        .overlay[hidden] { display: none; }
    </style>
    <link rel="stylesheet" href="{{ asset('player/player.css') }}">
</head>
<body>
@if ($video)
    <div id="player" data-src="{{ route('m3u8', $video) }}"></div>
    <div id="player-error" class="overlay" hidden></div>

    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.7.4/dist/hls.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/artplayer@5.4.0/dist/artplayer.js"></script>
    <script src="{{ asset('player/player-vi.js') }}?v=2"></script>
    <script src="{{ asset('player/player-ui.js') }}?v=2"></script>
    <script src="{{ asset('player/pixel-png.js') }}?v=2"></script>
    <script src="{{ asset('player/pixel-hls-loader.js') }}?v=2"></script>
    {{-- Temporarily disabled unified loader for debugging --}}
    {{-- <script src="{{ asset('player/header-graft-decoder.js') }}"></script> --}}
    {{-- <script src="{{ asset('player/unified-hls-loader.js') }}"></script> --}}
    <script src="{{ asset('player/player.js') }}?v=2"></script>
@else
    <div class="overlay">{{ $message }}</div>
@endif
</body>
</html>
