@extends('layouts.app')
@section('title','Моё портфолио · ШКИ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">My portfolio</div><div class="d-flex justify-content-between align-items-end gap-3 flex-wrap"><div><h1 class="display-2 fw-bold">Моё портфолио</h1><p class="text-white-50">Настройте профиль. Учебные и практические работы добавляются из уроков и преподавателем после проверки.</p></div>@if($profile->is_public)<a class="btn btn-ghost" href="{{ route('portfolio.show',$profile) }}">Открыть публичную страницу ↗</a>@endif</div></div></section>
<section class="pb-5"><div class="container">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<form method="post" enctype="multipart/form-data" action="{{ route('portfolio.mine.profile') }}" class="form-shell mb-5">@csrf
 <div class="row g-3">
  <div class="col-md-6"><label class="form-label">Короткий заголовок</label><input class="form-control" name="headline" value="{{ old('headline',$profile->headline) }}" placeholder="3D-дизайнер / VR-разработчик / фотограф"></div>
  <div class="col-md-6"><label class="form-label">Фото</label><input type="file" class="form-control" name="avatar" accept="image/*"></div>
  <div class="col-12"><label class="form-label">О себе</label><textarea class="form-control" rows="4" name="bio">{{ old('bio',$profile->bio) }}</textarea></div>
  <div class="col-12"><label class="form-label">Навыки</label><textarea class="form-control" rows="3" name="skills" placeholder="Blender, SketchUp, Unreal Engine, монтаж...">{{ old('skills',$profile->skills) }}</textarea><div class="form-text text-white-50">Разделяйте навыки запятыми, точкой с запятой или новой строкой.</div></div>
  <div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_public" value="1" @checked($profile->is_public)><label class="form-check-label">Показывать портфолио публично</label></div></div>
  <div class="col-md-6 text-end"><button class="btn btn-neon">Сохранить профиль</button></div>
 </div>
</form>

<div class="section-head"><div><div class="eyebrow">Works</div><h2>Мои работы</h2></div><span class="badge-soft">{{ $profile->portfolio->count() }}</span></div>
<div class="row g-4">
@forelse($profile->portfolio as $item)
 <div class="col-md-6 col-xl-4"><article class="project-card h-100">
  <div class="project-media">@if($item->cover_url)<img src="{{ $item->cover_url }}" alt="{{ $item->title }}">@else<div class="project-noise"></div>@endif</div>
  <div class="pt-3">
   <div class="small text-white-50">{{ $item->subject->title ?? $item->lesson->subject->title ?? $item->type }}</div>
   <h4>{{ $item->title }}</h4>
   @if($item->mentor)<div class="small text-white-50">Наставник: {{ $item->mentor->name }}</div>@endif
   <div class="small text-white-50 mt-1">{{ $item->is_public ? 'Публично' : 'Скрыто' }}</div>
   @if($item->is_public)<a class="btn btn-sm btn-ghost mt-3" href="{{ route('projects.show',$item) }}">Открыть ↗</a>@endif
  </div>
 </article></div>
@empty<div class="col-12"><div class="glass-card p-4 text-white-50">Работ пока нет.</div></div>@endforelse
</div>

<div class="section-head mt-5"><div><div class="eyebrow">Awards</div><h2>Достижения и сертификаты</h2></div></div>
<div class="achievement-grid">@forelse($profile->achievements as $a)<article class="achievement-card"><div class="achievement-medal">✦</div><div><div class="small text-white-50">{{ optional($a->awarded_at)->format('d.m.Y') }}</div><h4>{{ $a->title }}</h4><strong>{{ $a->result }}</strong><div class="text-white-50">{{ $a->competition->title ?? $a->level }}</div></div></article>@empty<div class="text-white-50">Достижения появятся здесь после добавления.</div>@endforelse</div>
</div></section>
@endsection
