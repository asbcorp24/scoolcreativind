@extends('layouts.app')
@section('title','Конкурсы и достижения · ШКИ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Competitions / Awards</div><h1 class="display-1 fw-bold">Конкурсы<br>и достижения</h1><p class="lead text-white-50 col-lg-8">Фестивали, олимпиады, творческие соревнования и результаты учеников школы.</p></div></section>
<section class="pb-5"><div class="container">
<div class="section-head"><div><div class="eyebrow">Opportunities</div><h2>Конкурсы</h2></div></div>
<div class="row g-4">
@forelse($competitions as $c)
 @php($reg=$registrations->get($c->id))
 <div class="col-md-6">
  <article class="glass-card p-4 h-100 tilt-card">
   <div class="eyebrow">{{ optional($c->starts_on)->format('d.m.Y') }} @if($c->ends_on) — {{ $c->ends_on->format('d.m.Y') }} @endif</div>
   <h3 class="display-6 fw-bold mt-3">{{ $c->title }}</h3>
   <div class="text-white-50 mb-3">{{ $c->organizer }} · участников: {{ $c->registrations_count }}</div>
   <p>{{ $c->description }}</p>
   @if($c->location)<div class="small mb-3">{{ $c->location }}</div>@endif
   <div class="d-flex gap-2 flex-wrap mb-3">@if($c->url)<a href="{{ $c->url }}" class="btn btn-ghost" target="_blank">Подробнее ↗</a>@endif
   @auth
    @if(!$reg)
      <form method="post" action="{{ route('competitions.register',$c) }}">@csrf<button class="btn btn-neon">Записаться</button></form>
    @else
      <span class="badge-soft">{{ ['registered'=>'Вы записаны','submitted'=>'Работа отправлена','reviewed'=>'Проверено','cancelled'=>'Отменено'][$reg->status] ?? $reg->status }}</span>
      @if($reg->status==='registered')
       <form method="post" action="{{ route('competitions.cancel',$c) }}">@csrf @method('DELETE')<button class="btn btn-ghost">Отменить запись</button></form>
      @endif
    @endif
   @else
    <a href="{{ route('login') }}" class="btn btn-neon">Войти для участия</a>
   @endauth
   </div>
   @auth
   @if($reg && in_array($reg->status,['registered','submitted']))
    <form method="post" enctype="multipart/form-data" action="{{ route('competitions.submit',$c) }}" class="competition-submit mt-4">@csrf
      <label class="form-label">Моя конкурсная работа</label>
      <textarea class="form-control mb-2" rows="3" name="submission_text" placeholder="Описание работы">{{ old('submission_text',$reg->submission_text) }}</textarea>
      <input class="form-control mb-2" name="submission_url" value="{{ old('submission_url',$reg->submission_url) }}" placeholder="Ссылка на проект / видео / облако">
      <input type="file" class="form-control mb-2" name="submission_file">
      @if($reg->file_url)<div class="small mb-2"><a href="{{ $reg->file_url }}" target="_blank">Текущий файл: {{ $reg->file_name }}</a></div>@endif
      <button class="btn btn-neon">{{ $reg->status==='submitted' ? 'Обновить работу' : 'Отправить работу' }}</button>
    </form>
   @endif
   @endauth
  </article>
 </div>
@empty<div class="text-white-50">Конкурсы пока не опубликованы.</div>@endforelse
</div>
<div class="section-head mt-5"><div><div class="eyebrow">Hall of fame</div><h2>Наши достижения</h2></div></div>
<div class="achievement-grid">@forelse($achievements as $a)<article class="achievement-card reveal"><div class="achievement-medal">★</div><div><div class="small text-white-50">{{ optional($a->awarded_at)->format('d.m.Y') }}</div><h4>{{ $a->student->user->name ?? $a->title }}</h4><strong>{{ $a->result ?: $a->title }}</strong><div class="text-white-50">{{ $a->competition->title ?? $a->level }}</div></div></article>@empty<div class="text-white-50">Достижения появятся здесь после добавления.</div>@endforelse</div>
</div></section>
@endsection
