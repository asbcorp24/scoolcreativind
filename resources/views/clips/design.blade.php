<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#05070d">
<title>Дизайн начинается с чувства · ШКИ</title>
<link rel="stylesheet" href="{{ asset('css/design-clip.css') }}">
</head>
<body>
<div id="designClip" class="design-clip" data-audio-url="{{ $track?->file_url ?? '' }}">
  <canvas id="designClipCanvas"></canvas>

  <div class="clip-noise"></div>
  <div class="clip-vignette"></div>

  <header class="clip-head">
    <a href="{{ url('/studios/design') }}" class="clip-back">← <span>Студия дизайна</span></a>
    <div class="clip-brand"><span>ШКИ</span><small>DESIGN / VISUAL SYSTEM</small></div>
    <button type="button" class="clip-fullscreen" data-clip-fullscreen>⛶</button>
  </header>

  <div class="clip-hud clip-hud-left">
    <span>FORM</span><span>COLOR</span><span>TYPE</span><span>MOTION</span>
  </div>

  <div class="clip-hud clip-hud-right">
    <div><small>MODE</small><strong data-clip-mode>EMPTY SPACE</strong></div>
    <div><small>AUDIO</small><strong data-clip-audio-state>READY</strong></div>
  </div>

  <section class="clip-copy">
    <div class="clip-kicker">СТУДИЯ ДИЗАЙНА · ШКИ ВОЛЖСК</div>
    <div class="clip-lyric" data-clip-lyric>
      <span>Дизайн начинается</span>
      <strong>с чувства.</strong>
    </div>
    <div class="clip-word" data-clip-word>DESIGN</div>
  </section>

  <div class="clip-start {{ $track ? '' : 'is-error' }}" data-clip-start-overlay>
    <div class="clip-start-mark">✦</div>
    <div class="clip-start-title">{{ $track ? 'Дизайн начинается с чувства' : 'Аудиотрек не найден' }}</div>
    <p>{{ $track ? 'Интерактивный 3D-клип студии дизайна' : 'Загрузите трек в разделе «Админ → Музыка» с названием «Дизайн начинается с чувства».' }}</p>
    @if($track)
      <button type="button" data-clip-start>ВОЙТИ В КЛИП</button>
      <small>{{ $track->artist ?: 'Школа креативных индустрий' }} · {{ $track->title }}</small>
    @endif
  </div>

  @if($track)
  <footer class="clip-controls">
    <button type="button" data-clip-play>▶</button>
    <div class="clip-time" data-clip-current>00:00</div>
    <div class="clip-seek" data-clip-seek><i data-clip-seek-fill></i></div>
    <div class="clip-time" data-clip-duration>02:35</div>
    <button type="button" data-clip-mute>VOL</button>
  </footer>
  <audio data-clip-audio preload="metadata" src="{{ $track->file_url }}"></audio>
  @endif
</div>
<script type="module" src="{{ asset('js/design-clip.js') }}"></script>
</body>
</html>
