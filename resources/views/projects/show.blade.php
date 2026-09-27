@extends('layouts.app')
@section('title',$project->title.' · Работы учеников · ШКИ')
@section('content')

<section class="page-top">
 <div class="container">
  <a href="{{ route('projects.index') }}" class="backlink">← Все работы</a>
  <div class="eyebrow mt-4">{{ $project->studio->title ?? 'Работа ученика' }}</div>
  <h1 class="display-2 fw-bold scroll-title">{{ $project->title }}</h1>
  <div class="d-flex gap-3 flex-wrap text-white-50">
   <span>{{ $project->student->user->name ?? 'Ученик ШКИ' }}</span>
   @if($project->completed_at)<span>· {{ $project->completed_at->format('Y') }}</span>@endif
   @if($project->type)<span>· {{ $project->type }}</span>@endif
  </div>
  @if($project->description)<p class="lead col-lg-9 mt-4 text-white-50">{{ $project->description }}</p>@endif
 </div>
</section>

@php
 $photos=$project->media->where('type','photo');
 $panos=$project->media->where('type','panorama');
 $models=$project->media->where('type','model');
 $audioTracks=$project->media->where('type','audio');
 $videos=$project->media->where('type','video');
 $files=$project->media->whereIn('type',['file','link']);
@endphp

@if($photos->count())
<section class="section-space pt-0">
 <div class="container-fluid px-lg-5">
  <div class="section-head"><div><div class="eyebrow">Photo</div><h2>Фотогалерея</h2></div></div>
  <div class="media-masonry" data-paginated-list data-page-size="6">
   @foreach($photos as $m)
    <button class="media-tile" data-page-item data-lightbox-photo data-src="{{ $m->display_url }}" data-title="{{ $m->title }}">
     <img src="{{ $m->display_url }}" alt="{{ $m->title }}">
     <span>{{ $m->title }}</span>
    </button>
   @endforeach
  </div>
  @if($photos->count()>6)<div class="media-pagination media-pagination-hitech mt-4" data-pagination></div>@endif
 </div>
</section>
@endif

@if($panos->count())
<section class="section-space immersive-section">
 <div class="container">
  <div class="section-head"><div><div class="eyebrow">Immersive</div><h2>360° панорамы</h2></div><p>Осматривайте сцену мышью или пальцем.</p></div>
  <div class="media-slider-shell" data-media-slider>
   <div class="media-slider-track" data-slider-track>
    @foreach($panos as $m)
     <div class="media-slider-slide">
      <div class="pano-shell" data-panorama="{{ $m->display_url }}" data-hotspots='{{ $m->hotspots_json ? e($m->hotspots_json) : "[]" }}'>
       <div class="pano-placeholder"><strong>360°</strong><span>{{ $m->title ?: 'Панорама' }}</span></div>
      </div>
     </div>
    @endforeach
   </div>
   <div class="media-slider-controls">
    <button class="media-slider-nav prev" type="button" data-slider-prev><span>←</span><small>ПРЕДЫДУЩАЯ</small></button>
    <div class="media-slider-counter" data-slider-counter>01 / {{ str_pad($panos->count(),2,'0',STR_PAD_LEFT) }}</div>
    <button class="media-fullscreen-btn" type="button" data-slider-fullscreen><span>⛶</span><small>НА ВЕСЬ ЭКРАН</small></button>
    <button class="media-slider-nav next" type="button" data-slider-next><small>СЛЕДУЮЩАЯ</small><span>→</span></button>
   </div>
  </div>
 </div>
</section>
@endif

@if($models->count())
<section class="section-space">
 <div class="container-fluid px-lg-5">
  <div class="section-head"><div><div class="eyebrow">Realtime 3D</div><h2>3D-модели</h2></div><p>Модели можно вращать и приближать прямо в браузере.</p></div>
  <div class="media-slider-shell" data-media-slider>
   <div class="media-slider-track" data-slider-track>
    @foreach($models as $m)
     <div class="media-slider-slide">
      <article class="model-card">
       <div class="model-viewer-local" data-model-viewer data-model-url="{{ $m->display_url }}"><div class="model-loading">Загрузка 3D-модели…</div></div>
       <div class="model-meta"><div class="eyebrow">3D object</div><h3>{{ $m->title ?: '3D модель' }}</h3>@if($m->caption)<p>{{ $m->caption }}</p>@endif</div>
      </article>
     </div>
    @endforeach
   </div>
   <div class="media-slider-controls">
    <button class="media-slider-nav prev" type="button" data-slider-prev><span>←</span><small>ПРЕДЫДУЩАЯ</small></button>
    <div class="media-slider-counter" data-slider-counter>01 / {{ str_pad($models->count(),2,'0',STR_PAD_LEFT) }}</div>
    <button class="media-fullscreen-btn" type="button" data-slider-fullscreen><span>⛶</span><small>НА ВЕСЬ ЭКРАН</small></button>
    <button class="media-slider-nav next" type="button" data-slider-next><small>СЛЕДУЮЩАЯ</small><span>→</span></button>
   </div>
  </div>
 </div>
</section>
@endif

@if($audioTracks->count())
<section class="section-space audio-section">
 <div class="container">
  <div class="section-head"><div><div class="eyebrow">Listen</div><h2>Аудио</h2></div></div>
  <div class="audio-playlist" data-paginated-list data-page-size="6">
   @foreach($audioTracks as $i=>$track)
    <article class="audio-track" data-audio-track data-page-item>
     <button class="audio-play" type="button" data-audio-button>▶</button>
     <div class="audio-track-main">
      <div class="audio-track-top"><div><div class="small text-white-50">TRACK {{ str_pad($i+1,2,'0',STR_PAD_LEFT) }}</div><h3>{{ $track->title ?: 'Аудиотрек' }}</h3></div><span class="audio-time" data-audio-time>00:00</span></div>
      @if($track->caption)<p>{{ $track->caption }}</p>@endif
      <div class="audio-progress"><div class="audio-progress-fill" data-audio-progress></div></div>
      <audio preload="metadata" src="{{ $track->display_url }}" data-audio></audio>
     </div>
    </article>
   @endforeach
  </div>
  @if($audioTracks->count()>6)<div class="media-pagination mt-4" data-pagination></div>@endif
 </div>
</section>
@endif

@if($videos->count())
<section class="section-space">
 <div class="container-fluid px-lg-5">
  <div class="section-head"><div><div class="eyebrow">Watch</div><h2>Видео</h2></div></div>
  <div class="row g-4">
   @foreach($videos as $m)
    <div class="col-lg-6">
     <article class="video-card">
      <div class="ratio ratio-16x9">
       @if(preg_match('~rutube\.ru/video/([^/]+)~',$m->url,$match))
        <iframe src="https://rutube.ru/play/embed/{{ $match[1] }}" allow="clipboard-write; autoplay; fullscreen" allowfullscreen></iframe>
       @elseif(preg_match('~^(https?:)?//~i',$m->url))
        <iframe src="{{ $m->url }}" allow="autoplay; fullscreen" allowfullscreen></iframe>
       @else
        <video controls playsinline preload="metadata" src="{{ $m->display_url }}" class="w-100 h-100"></video>
       @endif
      </div>
      <div class="p-3"><h4>{{ $m->title }}</h4>@if($m->caption)<p class="text-white-50 mb-0">{{ $m->caption }}</p>@endif</div>
     </article>
    </div>
   @endforeach
  </div>
 </div>
</section>
@endif

@if($files->count())
<section class="section-space pt-0">
 <div class="container">
  <div class="section-head"><div><div class="eyebrow">Materials</div><h2>Файлы и ссылки</h2></div></div>
  <div class="d-grid gap-2">
   @foreach($files as $m)
    <a href="{{ $m->display_url }}" target="_blank" rel="noopener" class="glass-card p-3 d-flex justify-content-between align-items-center">
     <div><strong>{{ $m->title ?: 'Открыть материал' }}</strong>@if($m->caption)<div class="small text-white-50">{{ $m->caption }}</div>@endif</div><span>↗</span>
    </a>
   @endforeach
  </div>
 </div>
</section>
@endif

@if(!$project->media->count())
<section class="pb-5"><div class="container"><div class="competition-empty"><div class="competition-empty-visual"><strong>00</strong></div><div><div class="eyebrow">Media</div><h3>Медиа для работы не опубликовано</h3></div></div></div></section>
@endif

<div class="photo-lightbox" data-photo-lightbox aria-hidden="true">
 <button class="photo-lightbox-close" type="button" data-lightbox-close aria-label="Закрыть">×</button>
 <button class="photo-lightbox-nav prev" type="button" data-lightbox-prev aria-label="Предыдущее фото">←</button>
 <div class="photo-lightbox-stage"><img data-lightbox-image alt=""><div class="photo-lightbox-bottom"><div class="photo-lightbox-title" data-lightbox-title></div><div class="photo-lightbox-counter" data-lightbox-counter></div></div></div>
 <button class="photo-lightbox-nav next" type="button" data-lightbox-next aria-label="Следующее фото">→</button>
</div>
@endsection
