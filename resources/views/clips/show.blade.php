@extends('layouts.app')
@section('title',$clip->title.' · Клипы ШКИ')
@section('content')
<section class="clip-player-page">
 <div class="container-fluid px-lg-4">
  <div class="clip-player-head">
    <div>
      <a href="{{ route('clips.index') }}" class="backlink">← Все клипы</a>
      <div class="eyebrow mt-3">Interactive clip</div>
      <h1>{{ $clip->title }}</h1>
      @if($clip->description)<p>{{ $clip->description }}</p>@endif
    </div>
    <button type="button" class="btn btn-ghost" data-clip-frame-fullscreen>⛶ На весь экран</button>
  </div>

  <div class="clip-frame-shell" data-clip-frame-shell>
    <div class="clip-frame-loader" data-clip-frame-loader>
      <span>◈</span><strong>Загрузка клипа</strong>
    </div>
    <iframe
      src="{{ $clip->player_url }}"
      title="{{ $clip->title }}"
      class="clip-frame"
      data-clip-frame
      allow="autoplay; fullscreen; accelerometer; gyroscope"
      allowfullscreen
      loading="eager"></iframe>
  </div>
 </div>
</section>

@if($moreClips->count())
<section class="section-space pt-0">
 <div class="container">
  <div class="section-head"><div><div class="eyebrow">Дальше</div><h2>Другие клипы</h2></div></div>
  <div class="clips-mini-grid">
   @foreach($moreClips as $item)
    <a href="{{ route('clips.show',$item) }}" class="clip-mini-card">
      @if($item->cover_url)<img src="{{ $item->cover_url }}" alt="{{ $item->title }}">@else<div class="clip-mini-placeholder">◈</div>@endif
      <div><strong>{{ $item->title }}</strong><span>Открыть ↗</span></div>
    </a>
   @endforeach
  </div>
 </div>
</section>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const frame=document.querySelector('[data-clip-frame]');
 const loader=document.querySelector('[data-clip-frame-loader]');
 const shell=document.querySelector('[data-clip-frame-shell]');
 const full=document.querySelector('[data-clip-frame-fullscreen]');
 frame?.addEventListener('load',()=>loader?.classList.add('hidden'));
 full?.addEventListener('click',()=>{
   if(!document.fullscreenElement)shell?.requestFullscreen?.();
   else document.exitFullscreen?.();
 });
});
</script>
@endpush
