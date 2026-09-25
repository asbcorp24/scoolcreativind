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
    <div class="project-media">
     @if($project->cover_url)
      <img src="{{ $project->cover_url }}" alt="{{ $project->title }}">
     @else
      <div class="project-noise"></div>
     @endif
    </div>
    <div class="p-4">
     <div class="eyebrow">{{ $project->studio->title ?? $project->type }}</div>
     <h3 class="mt-3">{{ $project->title }}</h3>
     <p class="text-white-50">{{ IlluminateSupportStr::limit($project->description,160) }}</p>
     <div class="small text-white-50 mb-3">
      {{ $project->student->user->name ?? 'Ученик ШКИ' }}
      @if($project->completed_at) · {{ $project->completed_at->format('Y') }} @endif
     </div>
     <div class="d-flex gap-2 flex-wrap">
      @if($project->project_url)<a class="btn btn-ghost" href="{{ $project->project_url }}" target="_blank">Открыть проект ↗</a>@endif
      @if($project->video_url)<a class="btn btn-ghost" href="{{ $project->video_url }}" target="_blank">Видео ↗</a>@endif
      @if($project->file_url)<a class="btn btn-neon" href="{{ $project->file_url }}" target="_blank">Файл{{ $project->human_file_size ? ' · '.$project->human_file_size : '' }}</a>@endif
     </div>
    </div>
   </article>
  </div>
 @empty
  <div class="col-12"><div class="competition-empty"><div class="competition-empty-visual"><strong>00</strong></div><div><div class="eyebrow">Portfolio loading</div><h3>Работы скоро появятся</h3><p>Когда ученики опубликуют проекты, они появятся здесь.</p></div></div></div>
 @endforelse
 </div>

 @if($projects->hasPages())
  <div class="mt-5">{{ $projects->links() }}</div>
 @endif
</div></section>
@endsection
