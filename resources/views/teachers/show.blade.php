@extends('layouts.app')
@section('title',$profile->user->name.' · Преподаватель')
@section('content')
<section class="page-top"><div class="container"><div class="portfolio-hero">
 <div class="portfolio-avatar">@if($profile->avatar_url)<img src="{{ $profile->avatar_url }}" alt="{{ $profile->user->name }}">@else<div class="team-placeholder">{{ mb_substr($profile->user->name,0,1) }}</div>@endif</div>
 <div><div class="eyebrow">Teacher portfolio</div><h1 class="display-2 fw-bold mt-2">{{ $profile->user->name }}</h1><div class="text-white-50">{{ $profile->headline ?: 'Преподаватель ШКИ' }} @if($profile->studio) · {{ $profile->studio->title }} @endif</div>@if($profile->bio)<p class="lead mt-3">{{ $profile->bio }}</p>@endif</div>
</div></div></section>
<section class="pb-5"><div class="container">
<div class="row g-4 mb-5">
 @foreach(['education'=>'Образование','experience'=>'Опыт','competencies'=>'Компетенции','courses'=>'Курсы и повышение квалификации','publications'=>'Публикации','professional_achievements'=>'Достижения'] as $field=>$label)
  @if($profile->$field)<div class="col-md-6"><div class="glass-card p-4 h-100"><div class="eyebrow">{{ $label }}</div><div class="mt-2" style="white-space:pre-line">{{ $profile->$field }}</div></div></div>@endif
 @endforeach
</div>
@if($subjects->count())<div class="section-head"><div><div class="eyebrow">Teaching</div><h2>Предметы</h2></div></div><div class="d-flex gap-2 flex-wrap mb-5">@foreach($subjects as $subject)<span class="badge-soft">{{ $subject->title }}</span>@endforeach</div>@endif
<div class="section-head"><div><div class="eyebrow">Projects</div><h2>Проекты преподавателя</h2></div></div>
<div class="row g-4 mb-5">@forelse($profile->portfolio as $item)<div class="col-md-6 col-xl-4"><article class="project-card h-100">
 <div class="project-media">@if($item->cover_url)<img src="{{ $item->cover_url }}" alt="{{ $item->title }}">@else<div class="project-noise"></div>@endif</div>
 <div class="pt-3">
  <div class="small text-white-50">{{ $item->studio->title ?? $item->type }}</div>
  <h4>{{ $item->title }}</h4>
  <p class="text-white-50">{{ $item->description }}</p>

  @if($item->media->count())
   <div class="row g-2 mb-3">
    @foreach($item->media->take(6) as $media)
     <div class="col-6">
      @if(in_array($media->type,['photo','panorama']))
       <a href="{{ $media->display_url }}" target="_blank" class="d-block ratio ratio-4x3 overflow-hidden rounded">
        <img src="{{ $media->thumbnail_url ?: $media->display_url }}" alt="{{ $media->title }}" style="object-fit:cover">
       </a>
      @elseif($media->type==='video')
       <div class="ratio ratio-16x9">
        @if(preg_match('~^(https?:)?//~i',$media->url))
         <iframe src="{{ $media->url }}" allow="autoplay; fullscreen" allowfullscreen></iframe>
        @else
         <video controls preload="metadata" src="{{ $media->display_url }}"></video>
        @endif
       </div>
      @elseif($media->type==='audio')
       <audio class="w-100" controls preload="metadata" src="{{ $media->display_url }}"></audio>
      @else
       <a class="glass-card p-2 d-flex justify-content-between align-items-center h-100" href="{{ $media->display_url }}" target="_blank">
        <span class="small">{{ $media->title ?: $media->file_name ?: strtoupper($media->type) }}</span><span>↗</span>
       </a>
      @endif
     </div>
    @endforeach
   </div>
  @endif

  <div class="d-flex gap-2 flex-wrap">
   @if($item->project_url)<a class="btn btn-sm btn-ghost" href="{{ $item->project_url }}" target="_blank">Проект ↗</a>@endif
   @if($item->video_url)<a class="btn btn-sm btn-ghost" href="{{ $item->video_url }}" target="_blank">Видео ↗</a>@endif
   @if($item->media->count())<span class="badge-soft">{{ $item->media->count() }} медиа</span>@endif
  </div>
 </div>
</article></div>@empty<div class="text-white-50">Публичных проектов пока нет.</div>@endforelse</div>
<div class="section-head"><div><div class="eyebrow">Mentorship</div><h2>Работы учеников под руководством</h2></div></div>
<div class="row g-4">@forelse($profile->mentoredWorks as $item)<div class="col-md-6 col-xl-4"><a href="{{ route('projects.show',$item) }}" class="project-card d-block h-100">
 <div class="project-media">@if($item->cover_url)<img src="{{ $item->cover_url }}" alt="{{ $item->title }}">@else<div class="project-noise"></div>@endif</div>
 <div class="pt-3"><div class="small text-white-50">{{ $item->student->user->name ?? 'Ученик' }} · {{ $item->subject->title ?? $item->lesson->subject->title ?? '' }}</div><h4>{{ $item->title }}</h4></div>
</a></div>@empty<div class="text-white-50">Публичных ученических работ пока нет.</div>@endforelse</div>
<div class="d-flex gap-3 mt-5">@if($profile->website_url)<a href="{{ $profile->website_url }}" target="_blank">Сайт ↗</a>@endif @if($profile->vk_url)<a href="{{ $profile->vk_url }}" target="_blank">VK ↗</a>@endif @if($profile->telegram_url)<a href="{{ $profile->telegram_url }}" target="_blank">Telegram ↗</a>@endif</div>
</div></section>
@endsection
