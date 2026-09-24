@extends('layouts.app')
@section('title',$studio->title.' · ШКИ')
@section('content')
<section class="studio-hero" style="--accent:{{ $studio->accent ?: '#8a5cff' }}">
 <div class="studio-hero-bg" @if($studio->cover) style="background-image:url('{{ asset('storage/'.$studio->cover) }}')" @endif></div>
 <div class="container position-relative">
  <div class="row min-vh-100 align-items-end py-5">
   <div class="col-xl-9 pb-5">
    <a href="{{ route('home') }}#studios" class="backlink">← Все студии</a>
    <div class="eyebrow mt-4">{{ $studio->icon ?: '✦' }} / направление</div>
    <h1 class="studio-title">{{ $studio->title }}</h1>
    <p class="lead col-lg-8 text-white-50">{{ $studio->subtitle }}</p>
   </div>
  </div>
 </div>
</section>

<section class="section-space">
 <div class="container">
  <div class="row g-5">
   <div class="col-lg-4"><div class="eyebrow">О студии</div><h2 class="display-5 fw-bold">Учимся<br>создавая.</h2></div>
   <div class="col-lg-8"><div class="studio-description">{!! nl2br(e($studio->description)) !!}</div></div>
  </div>
 </div>
</section>

@php($photos=$studio->media->where('type','photo'))
@if($photos->count())
<section class="section-space pt-0">
 <div class="container-fluid px-lg-5">
  <div class="section-head"><div><div class="eyebrow">Photo lab</div><h2>Фотогалерея</h2></div></div>
  <div class="media-masonry" data-paginated-list data-page-size="6">
   @foreach($photos as $m)<button class="media-tile" data-page-item data-bs-toggle="modal" data-bs-target="#photoModal" data-src="{{ $m->display_url }}"><img src="{{ $m->display_url }}" alt="{{ $m->title }}"><span>{{ $m->title }}</span></button>@endforeach
  </div>
  @if($photos->count()>6)<div class="media-pagination mt-4" data-pagination></div>@endif
 </div>
</section>
@endif

@php($panos=$studio->media->where('type','panorama'))
@if($panos->count())
<section class="section-space immersive-section">
 <div class="container">
  <div class="section-head"><div><div class="eyebrow">Immersive spaces</div><h2>360° галерея</h2></div><p>Осмотрите пространство мышью или пальцем.</p></div>
  <div class="media-slider-shell" data-media-slider>
   <button class="media-slider-nav prev" type="button" data-slider-prev aria-label="Предыдущая панорама">←</button>
   <div class="media-slider-track" data-slider-track>
    @foreach($panos as $m)
    <div class="media-slider-slide">
     <div class="pano-shell"
          data-panorama="{{ $m->display_url }}"
          data-hotspots='{{ $m->hotspots_json ? e($m->hotspots_json) : "[]" }}'>
        <div class="pano-placeholder"><strong>360°</strong><span>{{ $m->title ?: 'Панорама студии' }}</span></div>
     </div>
    </div>
    @endforeach
   </div>
   <button class="media-slider-nav next" type="button" data-slider-next aria-label="Следующая панорама">→</button>
  </div>
 </div>
</section>
@endif


@php($models=$studio->media->where('type','model'))
@if($models->count())
<section class="section-space">
 <div class="container-fluid px-lg-5">
  <div class="section-head">
   <div><div class="eyebrow">Realtime 3D</div><h2>3D-галерея</h2></div>
   <p>Модели можно вращать, приближать и рассматривать прямо в браузере. Поддерживаются GLB и GLTF.</p>
  </div>
  <div class="media-slider-shell" data-media-slider>
   <button class="media-slider-nav prev" type="button" data-slider-prev aria-label="Предыдущая модель">←</button>
   <div class="media-slider-track" data-slider-track>
   @foreach($models as $m)
   <div class="media-slider-slide">
    <article class="model-card">
      <div class="model-viewer-local" data-model-viewer data-model-url="{{ $m->display_url }}">
        <div class="model-loading">Загрузка 3D-модели…</div>
      </div>
      <div class="model-meta">
        <div class="eyebrow">3D object</div>
        <h3>{{ $m->title ?: '3D модель' }}</h3>
        @if($m->caption)<p>{{ $m->caption }}</p>@endif
      </div>
    </article>
   </div>
   @endforeach
   </div>
   <button class="media-slider-nav next" type="button" data-slider-next aria-label="Следующая модель">→</button>
  </div>
 </div>
</section>
@endif


@php($audioTracks=$studio->media->where('type','audio'))
@if($audioTracks->count())
<section class="section-space audio-section">
 <div class="container">
  <div class="section-head">
   <div><div class="eyebrow">Listen</div><h2>Аудиотреки</h2></div>
   <p>Музыка, саунд-дизайн, вокальные работы, подкасты и записи учеников студии.</p>
  </div>
  <div class="audio-playlist" data-paginated-list data-page-size="6">
   @foreach($audioTracks as $i=>$track)
   <article class="audio-track" data-audio-track data-page-item>
    <button class="audio-play" type="button" aria-label="Воспроизвести" data-audio-button>▶</button>
    <div class="audio-track-main">
     <div class="audio-track-top">
      <div>
       <div class="small text-white-50">TRACK {{ str_pad($i+1,2,'0',STR_PAD_LEFT) }}</div>
       <h3>{{ $track->title ?: 'Аудиотрек' }}</h3>
      </div>
      <span class="audio-time" data-audio-time>00:00</span>
     </div>
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

@php($videos=$studio->media->where('type','video'))
@if($videos->count())
<section class="section-space">
 <div class="container-fluid px-lg-5">
  <div class="section-head"><div><div class="eyebrow">Watch / listen</div><h2>Видеогалерея</h2></div><p>Видео размещаются на Rutube, а в админке хранится ссылка на ролик.</p></div>
  <div class="row g-4">
   @foreach($videos as $m)
   <div class="col-lg-6"><div class="video-card"><div class="ratio ratio-16x9">
    <iframe src="{{ preg_replace('~rutube\.ru/video/([^/]+)/?.*~','rutube.ru/play/embed/$1',$m->url) }}" allow="clipboard-write; autoplay" webkitAllowFullScreen mozallowfullscreen allowfullscreen></iframe>
   </div><div class="p-3"><h4>{{ $m->title }}</h4><p class="text-white-50 mb-0">{{ $m->caption }}</p></div></div></div>
   @endforeach
  </div>
 </div>
</section>
@endif

<section class="section-space">
 <div class="container">
  <div class="section-head"><div><div class="eyebrow">Portfolio</div><h2>Проекты учеников</h2></div></div>
  <div class="row g-4">
   @forelse($studio->projects as $project)
    <div class="col-md-6 col-xl-4"><article class="project-card"><div class="project-media">@if($project->cover)<img src="{{ $project->cover }}" alt="">@else<div class="project-noise"></div>@endif</div><div class="pt-3"><h4>{{ $project->title }}</h4><p class="text-white-50">{{ $project->description }}</p></div></article></div>
   @empty
    <div class="text-white-50">Проекты будут добавляться через админ-панель.</div>
   @endforelse
  </div>
 </div>
</section>


@if($studio->team->count() || $studio->equipment->count())
<section class="section-space pt-0">
 <div class="container">
  @if($studio->team->count())
  <div class="section-head"><div><div class="eyebrow">Team</div><h2>Команда студии</h2></div><a href="{{ route('team') }}" class="btn btn-ghost">Вся команда</a></div>
  <div class="row g-4 mb-5">
   @foreach($studio->team as $m)
   <div class="col-md-6 col-xl-4">
    <article class="team-card">
      <div class="team-photo">@if($m->photo_url)<img src="{{ $m->photo_url }}" alt="{{ $m->name }}">@else<div class="team-placeholder">{{ mb_substr($m->name,0,1) }}</div>@endif</div>
      <div class="team-info"><h3>{{ $m->name }}</h3><div class="team-role">{{ $m->role }}</div>@if($m->bio)<p>{{ $m->bio }}</p>@endif</div>
    </article>
   </div>
   @endforeach
  </div>
  @endif

  @if($studio->equipment->count())
  <div class="section-head mt-5"><div><div class="eyebrow">Tools</div><h2>Оборудование</h2></div><a href="{{ route('equipment') }}" class="btn btn-ghost">Весь каталог</a></div>
  <div class="equipment-strip">
   @foreach($studio->equipment as $item)
   <a href="{{ route('equipment') }}" class="equipment-mini">
    <div class="equipment-mini-image">@if($item->image_url)<img src="{{ $item->image_url }}" alt="{{ $item->title }}">@endif</div>
    <div><div class="small text-white-50">{{ $item->category }}</div><strong>{{ $item->title }}</strong><div class="small">{{ trim(($item->brand ?? '').' '.($item->model ?? '')) }}</div></div>
   </a>
   @endforeach
  </div>
  @endif
 </div>
</section>
@endif

<section class="cta-section"><div class="container text-center"><div class="eyebrow justify-content-center">Хочу в эту студию</div><h2>Попробуй себя<br>в {{ mb_strtolower($studio->title) }}.</h2><a href="{{ route('apply') }}?studio={{ $studio->id }}" class="btn btn-neon btn-lg mt-4">Подать заявку</a></div></section>

<div class="modal fade" id="photoModal" tabindex="-1"><div class="modal-dialog modal-fullscreen"><div class="modal-content bg-black"><button type="button" class="btn-close btn-close-white modal-x" data-bs-dismiss="modal"></button><div class="modal-body d-flex align-items-center justify-content-center"><img id="modalPhoto" class="img-fluid mh-100" alt=""></div></div></div></div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-src]').forEach(el=>el.addEventListener('click',()=>document.getElementById('modalPhoto').src=el.dataset.src));
</script>
@endpush
