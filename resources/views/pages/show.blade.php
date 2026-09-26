@extends('layouts.app')
@section('title',$page->title.' · ШКИ')
@section('content')
<section class="page-top custom-page-hero">
 <div class="container">
  @if($page->parent)<a href="{{ route('pages.show',$page->parent) }}" class="backlink">← {{ $page->parent->title }}</a>@endif
  <div class="eyebrow mt-4">Раздел</div>
  <h1 class="display-1 fw-bold scroll-title">{{ $page->title }}</h1>
  @if($page->subtitle)<p class="lead text-white-50 col-lg-8">{{ $page->subtitle }}</p>@endif
 </div>
</section>

@if($page->cover_url)<div class="container"><img src="{{ $page->cover_url }}" class="w-100 rounded-4 news-main-cover" alt="{{ $page->title }}"></div>@endif

<section class="section-space pt-5"><div class="container"><article class="cms-public-content col-xl-9">{!! $page->body_html !!}</article></div></section>

@if($page->children->count())
<section class="section-space pt-0"><div class="container"><div class="section-head"><div><div class="eyebrow">Подразделы</div><h2>В этом разделе</h2></div></div><div class="custom-subpages-grid">@foreach($page->children as $child)<a href="{{ route('pages.show',$child) }}" class="glass-card p-4 text-decoration-none"><div class="eyebrow">Раздел</div><h3 class="mt-2">{{ $child->title }}</h3>@if($child->subtitle)<p class="text-white-50">{{ $child->subtitle }}</p>@endif<span>Открыть ↗</span></a>@endforeach</div></div></section>
@endif

@php($images=$page->media->where('type','image'))
@if($images->count())
<section class="section-space pt-0"><div class="container"><div class="section-head"><div><div class="eyebrow">Media</div><h2>Галерея</h2></div></div><div class="news-media-grid">@foreach($images as $m)<button class="news-media-image" type="button" data-lightbox-photo data-src="{{ $m->display_url }}" data-title="{{ $m->title }}"><img src="{{ $m->display_url }}" alt="{{ $m->title }}">@if($m->title)<span>{{ $m->title }}</span>@endif</button>@endforeach</div></div></section>
@endif

@php($videos=$page->media->where('type','video'))
@if($videos->count())
<section class="section-space pt-0"><div class="container"><div class="section-head"><div><div class="eyebrow">Video</div><h2>Видео</h2></div></div><div class="news-video-grid">@foreach($videos as $m)<article class="glass-card p-3">@if($m->path)<video controls playsinline class="w-100 rounded-4" src="{{ $m->display_url }}"></video>@else<div class="ratio ratio-16x9"><iframe src="{{ $m->display_url }}" allow="autoplay; fullscreen" loading="lazy"></iframe></div>@endif@if($m->title)<h3 class="h5 mt-3">{{ $m->title }}</h3>@endif</article>@endforeach</div></div></section>
@endif

@php($audios=$page->media->where('type','audio'))
@if($audios->count())
<section class="section-space pt-0"><div class="container"><div class="section-head"><div><div class="eyebrow">Audio</div><h2>Аудио</h2></div></div><div class="d-grid gap-3">@foreach($audios as $m)<article class="glass-card p-4"><strong>{{ $m->title ?: $m->file_name }}</strong><audio controls class="w-100 mt-3" src="{{ $m->display_url }}"></audio></article>@endforeach</div></div></section>
@endif

@php($downloads=$page->media->whereIn('type',['file','link']))
@if($downloads->count())
<section class="section-space pt-0"><div class="container"><div class="section-head"><div><div class="eyebrow">Materials</div><h2>Материалы</h2></div></div><div class="d-grid gap-2">@foreach($downloads as $m)<a href="{{ $m->display_url }}" target="_blank" class="glass-card p-3 d-flex justify-content-between text-decoration-none"><strong>{{ $m->title ?: ($m->file_name ?: 'Открыть материал') }}</strong><span>↗</span></a>@endforeach</div></div></section>
@endif

@if($images->count())
<div class="photo-lightbox" data-photo-lightbox aria-hidden="true"><button class="photo-lightbox-close" type="button" data-lightbox-close>×</button><button class="photo-lightbox-nav prev" type="button" data-lightbox-prev>←</button><div class="photo-lightbox-stage"><img data-lightbox-image alt=""><div class="photo-lightbox-bottom"><div class="photo-lightbox-title" data-lightbox-title></div><div class="photo-lightbox-counter" data-lightbox-counter></div></div></div><button class="photo-lightbox-nav next" type="button" data-lightbox-next>→</button></div>
@endif
@endsection
