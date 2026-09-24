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
  <div class="media-masonry">
   @foreach($photos as $m)<button class="media-tile" data-bs-toggle="modal" data-bs-target="#photoModal" data-src="{{ $m->url }}"><img src="{{ $m->url }}" alt="{{ $m->title }}"><span>{{ $m->title }}</span></button>@endforeach
  </div>
 </div>
</section>
@endif

@php($panos=$studio->media->where('type','panorama'))
@if($panos->count())
<section class="section-space immersive-section">
 <div class="container">
  <div class="section-head"><div><div class="eyebrow">Immersive spaces</div><h2>360° галерея</h2></div><p>Осмотрите пространство мышью или пальцем.</p></div>
  @foreach($panos as $m)
   <div class="pano-shell mb-4" data-panorama="{{ $m->url }}"><div class="pano-placeholder"><strong>360°</strong><span>{{ $m->title ?: 'Панорама студии' }}</span></div></div>
  @endforeach
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

<section class="cta-section"><div class="container text-center"><div class="eyebrow justify-content-center">Хочу в эту студию</div><h2>Попробуй себя<br>в {{ mb_strtolower($studio->title) }}.</h2><a href="{{ route('apply') }}?studio={{ $studio->id }}" class="btn btn-neon btn-lg mt-4">Подать заявку</a></div></section>

<div class="modal fade" id="photoModal" tabindex="-1"><div class="modal-dialog modal-fullscreen"><div class="modal-content bg-black"><button type="button" class="btn-close btn-close-white modal-x" data-bs-dismiss="modal"></button><div class="modal-body d-flex align-items-center justify-content-center"><img id="modalPhoto" class="img-fluid mh-100" alt=""></div></div></div></div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-src]').forEach(el=>el.addEventListener('click',()=>document.getElementById('modalPhoto').src=el.dataset.src));
</script>
@endpush
