@extends('layouts.app')
@section('title',$profile->user->name.' · Портфолио')
@section('content')
<section class="page-top"><div class="container"><div class="portfolio-hero">
 <div class="portfolio-avatar">@if($profile->avatar_url)<img src="{{ $profile->avatar_url }}" alt="{{ $profile->user->name }}">@else<div class="team-placeholder">{{ mb_substr($profile->user->name,0,1) }}</div>@endif</div>
 <div><div class="eyebrow">Student portfolio</div><h1 class="display-2 fw-bold mt-2">{{ $profile->user->name }}</h1><div class="text-white-50">{{ $profile->studio->title ?? 'Школа креативных индустрий' }} @if($profile->class_name) · {{ $profile->class_name }} @endif</div>@if($profile->bio)<p class="lead mt-3">{{ $profile->bio }}</p>@endif</div>
</div></div></section>
<section class="pb-5"><div class="container">
<div class="section-head"><div><div class="eyebrow">Works</div><h2>Проекты</h2></div></div>
<div class="row g-4">
@forelse($profile->portfolio->where('is_public',true) as $item)
@php
 $preview=$item->media->firstWhere('is_featured',true) ?: $item->media->first(fn($m)=>in_array($m->type,['photo','panorama']));
@endphp
<div class="col-md-6 col-xl-4">
 <a href="{{ route('projects.show',$item) }}" class="project-card d-block">
  <div class="project-media">
   @if($preview && in_array($preview->type,['photo','panorama']))
    <img src="{{ $preview->thumbnail_url ?: $preview->display_url }}" alt="{{ $item->title }}">
   @elseif($item->cover_url)
    <img src="{{ $item->cover_url }}" alt="{{ $item->title }}">
   @else
    <div class="project-noise"></div>
   @endif
  </div>
  <div class="pt-3">
   <div class="small text-white-50">{{ $item->studio->title ?? $item->type }}</div>
   <h4>{{ $item->title }}</h4>
   <p class="text-white-50">{{ $item->description }}</p>
   <div class="small text-white-50">{{ $item->media->count() }} медиа</div>
  </div>
 </a>
</div>
@empty<div class="text-white-50">Публичных работ пока нет.</div>@endforelse
</div>
<div class="section-head mt-5"><div><div class="eyebrow">Awards</div><h2>Достижения</h2></div></div>
<div class="achievement-grid">
@forelse($profile->achievements->where('is_public',true) as $a)
<article class="achievement-card"><div class="achievement-medal">✦</div><div><div class="small text-white-50">{{ optional($a->awarded_at)->format('d.m.Y') }}</div><h4>{{ $a->title }}</h4><strong>{{ $a->result }}</strong><div class="text-white-50">{{ $a->competition->title ?? $a->level }}</div></div></article>
@empty<div class="text-white-50">Достижения появятся здесь после добавления.</div>@endforelse
</div>
</div></section>
@endsection
