@extends('layouts.app')
@section('title','Конкурсы и достижения · ШКИ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Competitions / Awards</div><h1 class="display-1 fw-bold">Конкурсы<br>и достижения</h1><p class="lead text-white-50 col-lg-8">Фестивали, олимпиады, творческие соревнования и результаты учеников школы.</p></div></section>
<section class="pb-5"><div class="container">
<div class="section-head"><div><div class="eyebrow">Opportunities</div><h2>Конкурсы</h2></div></div>
<div class="row g-4">@forelse($competitions as $c)<div class="col-md-6"><article class="glass-card p-4 h-100"><div class="eyebrow">{{ optional($c->starts_on)->format('d.m.Y') }} @if($c->ends_on) — {{ $c->ends_on->format('d.m.Y') }} @endif</div><h3 class="display-6 fw-bold mt-3">{{ $c->title }}</h3><div class="text-white-50 mb-3">{{ $c->organizer }}</div><p>{{ $c->description }}</p>@if($c->location)<div class="small mb-3">{{ $c->location }}</div>@endif @if($c->url)<a href="{{ $c->url }}" class="btn btn-ghost" target="_blank">Подробнее ↗</a>@endif</article></div>@empty<div class="text-white-50">Конкурсы пока не опубликованы.</div>@endforelse</div>
<div class="section-head mt-5"><div><div class="eyebrow">Hall of fame</div><h2>Наши достижения</h2></div></div>
<div class="achievement-grid">@forelse($achievements as $a)<article class="achievement-card reveal"><div class="achievement-medal">★</div><div><div class="small text-white-50">{{ optional($a->awarded_at)->format('d.m.Y') }}</div><h4>{{ $a->student->user->name ?? $a->title }}</h4><strong>{{ $a->result ?: $a->title }}</strong><div class="text-white-50">{{ $a->competition->title ?? $a->level }}</div></div></article>@empty<div class="text-white-50">Достижения появятся здесь после добавления.</div>@endforelse</div>
</div></section>
@endsection
