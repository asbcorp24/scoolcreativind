@extends('layouts.app')

@section('title',$page->title.' · ШКИ')

@section('content')
<div class="custom-page-shell">
<section class="page-top custom-page-hero">
  <div class="container">
    @if($page->parent)
      <a href="{{ route('pages.show',$page->parent) }}" class="backlink">← {{ $page->parent->title }}</a>
    @endif

    <div class="eyebrow mt-4">Раздел</div>
    <h1 class="display-1 fw-bold scroll-title">{{ $page->title }}</h1>

    @if($page->subtitle)
      <p class="lead text-white-50 col-lg-8">{{ $page->subtitle }}</p>
    @endif
  </div>
</section>

@if($page->cover_url)
  <div class="container">
    <img
      src="{{ $page->cover_url }}"
      class="w-100 rounded-4 news-main-cover"
      alt="{{ $page->title }}"
    >
  </div>
@endif

<section class="section-space pt-5">
  <div class="container">
    <article class="cms-public-content col-xl-9">
      {!! $page->body_html !!}
    </article>
  </div>
</section>

@if($page->children->count())
  <section class="section-space pt-0">
    <div class="container">
      <div class="section-head">
        <div>
          <div class="eyebrow">Подразделы</div>
          <h2>В этом разделе</h2>
        </div>
      </div>

      <div class="custom-subpages-grid">
        @foreach($page->children as $child)
          <a href="{{ route('pages.show',$child) }}" class="glass-card p-4 text-decoration-none">
            <div class="eyebrow">Раздел</div>
            <h3 class="mt-2">{{ $child->title }}</h3>

            @if($child->subtitle)
              <p class="text-white-50">{{ $child->subtitle }}</p>
            @endif

            <span>Открыть ↗</span>
          </a>
        @endforeach
      </div>
    </div>
  </section>
@endif

@include('partials.unified-media-gallery',['media'=>$page->media])
</div>
@endsection
