@extends('layouts.app')
@section('title',$post->title.' · ШКИ')
@section('content')
<section class="page-top"><div class="container"><a href="{{ route('news.index') }}" class="backlink">← Новости</a><div class="eyebrow mt-4">{{ optional($post->published_at)->format('d.m.Y') }}</div><h1 class="display-2 fw-bold mt-3">{{ $post->title }}</h1><p class="lead text-white-50">{{ $post->excerpt }}</p></div></section>

@if($post->cover_url)
<div class="container"><img src="{{ $post->cover_url }}" class="w-100 rounded-4 news-main-cover" alt="{{ $post->title }}"></div>
@endif

<section class="section-space pt-5"><div class="container"><div class="studio-description col-xl-9">{!! nl2br(e($post->body)) !!}</div></div></section>

@php($images=$post->media->where('type','image'))
@if($images->count())
<section class="section-space pt-0">
 <div class="container">
  <div class="section-head"><div><div class="eyebrow">Галерея</div><h2>Фотографии</h2></div></div>
  <div class="news-media-grid">
   @foreach($images as $m)
    <button class="news-media-image" type="button" data-lightbox-photo data-src="{{ $m->display_url }}" data-title="{{ $m->title }}">
      <img src="{{ $m->display_url }}" alt="{{ $m->title }}">
      @if($m->title)<span>{{ $m->title }}</span>@endif
    </button>
   @endforeach
  </div>
 </div>
</section>
@endif

@php($videos=$post->media->where('type','video'))
@if($videos->count())
<section class="section-space pt-0"><div class="container">
 <div class="section-head"><div><div class="eyebrow">Video</div><h2>Видео</h2></div></div>
 <div class="news-video-grid">
 @foreach($videos as $m)
  <article class="glass-card p-3">
   @if($m->path)
    <video controls playsinline preload="metadata" class="w-100 rounded-4"><source src="{{ $m->display_url }}"></video>
   @else
    <div class="ratio ratio-16x9"><iframe src="{{ $m->display_url }}" allow="autoplay; fullscreen" loading="lazy"></iframe></div>
   @endif
   @if($m->title)<h3 class="h5 mt-3">{{ $m->title }}</h3>@endif
  </article>
 @endforeach
 </div>
</div></section>
@endif

@php($audios=$post->media->where('type','audio'))
@if($audios->count())
<section class="section-space pt-0"><div class="container">
 <div class="section-head"><div><div class="eyebrow">Audio</div><h2>Аудио</h2></div></div>
 <div class="d-grid gap-3">
 @foreach($audios as $m)
  <article class="glass-card p-4"><strong>{{ $m->title ?: $m->file_name }}</strong><audio controls preload="metadata" class="w-100 mt-3" src="{{ $m->display_url }}"></audio></article>
 @endforeach
 </div>
</div></section>
@endif

@php($downloads=$post->media->whereIn('type',['file','link']))
@if($downloads->count())
<section class="section-space pt-0"><div class="container">
 <div class="section-head"><div><div class="eyebrow">Materials</div><h2>Материалы</h2></div></div>
 <div class="d-grid gap-2">
 @foreach($downloads as $m)
  <a href="{{ $m->display_url }}" target="_blank" rel="noopener" class="glass-card p-3 d-flex justify-content-between align-items-center text-decoration-none">
   <div><strong>{{ $m->title ?: ($m->file_name ?: 'Открыть материал') }}</strong>@if($m->human_file_size)<div class="small text-white-50">{{ $m->human_file_size }}</div>@endif</div><span>↗</span>
  </a>
 @endforeach
 </div>
</div></section>
@endif

@if($images->count())
<div class="photo-lightbox" data-photo-lightbox aria-hidden="true">
 <button class="photo-lightbox-close" type="button" data-lightbox-close aria-label="Закрыть">×</button>
 <button class="photo-lightbox-nav prev" type="button" data-lightbox-prev aria-label="Предыдущее фото">←</button>
 <div class="photo-lightbox-stage">
  <img data-lightbox-image alt="">
  <div class="photo-lightbox-bottom"><div class="photo-lightbox-title" data-lightbox-title></div><div class="photo-lightbox-counter" data-lightbox-counter></div></div>
 </div>
 <button class="photo-lightbox-nav next" type="button" data-lightbox-next aria-label="Следующее фото">→</button>
</div>
@endif
@endsection
