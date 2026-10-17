@extends('layouts.app')
@section('title','Преподаватели · ШКИ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Mentors</div><h1 class="display-2 fw-bold">Преподаватели</h1><p class="lead text-white-50">Профессиональные профили, компетенции, проекты и работы учеников под руководством наставников.</p></div></section>
<section class="pb-5"><div class="container"><div class="row g-4">
@forelse($profiles as $p)
<div class="col-md-6 col-xl-4"><a class="glass-card p-4 h-100 d-block text-decoration-none" href="{{ route('teachers.show',$p) }}">
 <div class="d-flex gap-3 align-items-center">
  <div class="portfolio-avatar" style="width:84px;height:84px">@if($p->avatar_url)<img src="{{ $p->avatar_url }}" alt="{{ $p->user->name }}">@else<div class="team-placeholder">{{ mb_substr($p->user->name,0,1) }}</div>@endif</div>
  <div><div class="eyebrow">{{ $p->studio->title ?? 'ШКИ' }}</div><h3 class="mb-1">{{ $p->user->name }}</h3><div class="text-white-50">{{ $p->headline }}</div></div>
 </div>
 @if($p->competencies)<p class="text-white-50 mt-3 mb-0">{{ mb_strimwidth(strip_tags($p->competencies),0,180,'…') }}</p>@endif
</a></div>
@empty<div class="col-12"><div class="glass-card p-4 text-white-50">Публичных профилей преподавателей пока нет.</div></div>@endforelse
</div></div></section>
@endsection
