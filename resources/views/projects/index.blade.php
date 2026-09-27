@extends('layouts.app')
@section('title','Работы учеников · ШКИ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Student works</div><h1 class="display-1 fw-bold scroll-title">Работы учеников</h1><p class="lead text-white-50 col-lg-8">Проекты, созданные в студиях школы: дизайн, 3D, VR/AR, звук, музыка, фото и видео.</p></div></section>

<section class="pb-5"><div class="container">
 <div class="d-flex gap-2 flex-wrap mb-4">
  <a href="{{ route('projects.index') }}" class="btn {{ empty($activeType) ? 'btn-neon' : 'btn-ghost' }}">Все</a>
  @foreach($types as $type)
   <a href="{{ route('projects.index',['type'=>$type]) }}" class="btn {{ $activeType===$type ? 'btn-neon' : 'btn-ghost' }}">{{ $type }}</a>
  @endforeach
 </div>

 <div class="row g-4">
 @forelse($projects as $project)
  <div class="col-md-6 col-xl-4">
   <article class="glass-card h-100 project-card tilt-card">
    @php
      $preview=$project->media->firstWhere('is_featured',true)
          ?: $project->media->first(fn($m)=>in_array($m->type,['photo','panorama']));
    @endphp
    <a class="project-media" href="{{ route('projects.show',$project) }}">
     @if($preview && in_array($preview->type,['photo','panorama']))
      <img src="{{ $preview->thumbnail_url ?: $preview->display_url }}" alt="{{ $project->title }}">
     @elseif($project->cover_url)
      <img src="{{ $project->cover_url }}" alt="{{ $project->title }}">
     @else
      <div class="project-noise"></div>
     @endif
    </a>
    <div class="p-4">
     <div class="eyebrow">{{ $project->studio->title ?? $project->type }}</div>
     <h3 class="mt-3">{{ $project->title }}</h3>
     <p class="text-white-50">{{ \Illuminate\Support\Str::limit($project->description,160) }}</p>
     <div class="small text-white-50 mb-3">
      {{ $project->student->user->name ?? 'Ученик ШКИ' }}
      @if($project->completed_at) · {{ $project->completed_at->format('Y') }} @endif
     </div>
     <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-neon" href="{{ route('projects.show',$project) }}">Смотреть работу</a>
      @if($project->media->count())<span class="badge-soft align-self-center">{{ $project->media->count() }} медиа</span>@endif
     </div>
    </div>
   </article>
  </div>
 @empty
  <div class="col-12"><div class="competition-empty"><div class="competition-empty-visual"><strong>00</strong></div><div><div class="eyebrow">Student portfolio</div><h3>Нет опубликованных работ</h3><p>В этом разделе представлены публичные проекты учеников школы.</p></div></div></div>
 @endforelse
 </div>

 @if($projects->hasPages())
  <div class="mt-5">{{ $projects->links() }}</div>
 @endif
</div></section>
@endsection
