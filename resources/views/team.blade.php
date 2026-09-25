@extends('layouts.app')
@section('title','Команда · ШКИ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">People behind the ideas</div><h1 class="display-1 fw-bold">Команда</h1><p class="lead text-white-50 col-lg-7">Наставники, преподаватели и специалисты, которые помогают превращать идеи в реальные проекты.</p></div></section>
<section class="pb-5 mb-5"><div class="container"><div class="team-grid">
@forelse($members as $m)
<article class="team-card reveal">
 <div class="team-photo">@if($m->photo_url)<img src="{{ $m->photo_url }}" alt="{{ $m->name }}">@else<div class="team-placeholder">{{ mb_substr($m->name,0,1) }}</div>@endif</div>
 <div class="team-info">
  <div class="eyebrow">{{ $m->studio->title ?? 'ШКИ' }}</div>
  <h3>{{ $m->name }}</h3>
  <div class="team-role">{{ $m->role }}</div>
  @if($m->bio)<p>{{ $m->bio }}</p>@endif
  <div class="team-links">
   @if($m->email)<a href="mailto:{{ $m->email }}">Email</a>@endif
   @if($m->vk_url)<a href="{{ $m->vk_url }}" target="_blank" rel="noopener">VK ↗</a>@endif
   @if($m->telegram_url)<a href="{{ $m->telegram_url }}" target="_blank" rel="noopener">Telegram ↗</a>@endif
  </div>
 </div>
</article>
@empty
<div class="text-white-50">Нет опубликованных данных о команде.</div>
@endforelse
</div></div></section>
@endsection
