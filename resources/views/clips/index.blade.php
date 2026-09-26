@extends('layouts.app')
@section('title','Клипы · ШКИ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Interactive / clips</div>
  <h1 class="display-1 fw-bold scroll-title">Клипы</h1>
  <p class="lead text-white-50 col-lg-8">Интерактивные музыкальные и 3D-клипы студий Школы креативных индустрий.</p>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  <div class="clips-grid">
   @forelse($clips as $clip)
    <a href="{{ route('clips.show',$clip) }}" class="clip-card">
      <div class="clip-card-visual">
        @if($clip->cover_url)
          <img src="{{ $clip->cover_url }}" alt="{{ $clip->title }}">
        @else
          <div class="clip-card-placeholder">
            <span>◈</span>
            <i></i><i></i><i></i>
          </div>
        @endif
        <div class="clip-card-play">▶</div>
        <div class="clip-card-scan"></div>
      </div>
      <div class="clip-card-copy">
        <div class="eyebrow">3D / AUDIO / INTERACTIVE</div>
        <h2>{{ $clip->title }}</h2>
        @if($clip->description)<p>{{ IlluminateSupportStr::limit($clip->description,150) }}</p>@endif
        <strong>Смотреть клип ↗</strong>
      </div>
    </a>
   @empty
    <div class="glass-card p-5 text-white-50">Нет опубликованных клипов.</div>
   @endforelse
  </div>
 </div>
</section>
@endsection
