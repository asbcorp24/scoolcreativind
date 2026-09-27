@extends('layouts.app')
@section('title',$post->title.' · ШКИ')
@section('content')
<section class="page-top">
 <div class="container">
  <a href="{{ route('news.index') }}" class="backlink">← Новости</a>
  <div class="eyebrow mt-4">{{ optional($post->published_at)->format('d.m.Y') }}</div>
  <h1 class="display-2 fw-bold mt-3">{{ $post->title }}</h1>
  @if($post->excerpt)<p class="lead text-white-50">{{ $post->excerpt }}</p>@endif
 </div>
</section>

@if($post->cover_url)
<div class="container"><img src="{{ $post->cover_url }}" class="w-100 rounded-4 news-main-cover" alt="{{ $post->title }}"></div>
@endif

<section class="section-space pt-5">
 <div class="container"><div class="studio-description col-xl-9">{!! nl2br(e($post->body)) !!}</div></div>
</section>

@include('partials.unified-media-gallery',['media'=>$post->media])
@endsection
