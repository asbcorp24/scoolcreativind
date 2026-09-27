@extends('layouts.app')
@section('title','Расписание · ШКИ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Learning calendar</div>
  <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
   <div>
    <h1 class="display-1 fw-bold">Расписание</h1>
    <p class="lead text-white-50">Занятия, мастер-классы и студийная практика по дням.</p>
   </div>
  </div>

  <form method="get" class="schedule-filter-panel mt-4">
   <div class="row g-3 align-items-end">
    <div class="col-md-3">
     <label class="form-label">Месяц</label>
     <input type="month" class="form-control" name="month" value="{{ $month }}">
    </div>

    <div class="col-md-4">
     <label class="form-label">Студия</label>
     <select class="form-select" name="studio">
      <option value="">Все студии</option>
      @foreach($studios as $studio)
       <option value="{{ $studio->id }}" @selected((string)$activeStudio===(string)$studio->id)>{{ $studio->title }}</option>
      @endforeach
     </select>
    </div>

    <div class="col-md-3">
     <label class="form-label">Преподаватель</label>
     <select class="form-select" name="teacher">
      <option value="">Все преподаватели</option>
      @foreach($teachers as $teacher)
       <option value="{{ $teacher }}" @selected($activeTeacher===$teacher)>{{ $teacher }}</option>
      @endforeach
     </select>
    </div>

    <div class="col-md-2 d-grid gap-2">
     <button class="btn btn-neon">Показать</button>
     @if($activeStudio!=='' || $activeTeacher!=='')
      <a class="btn btn-ghost btn-sm" href="{{ route('schedule',['month'=>$month]) }}">Сбросить</a>
     @endif
    </div>
   </div>
  </form>
 </div>
</section>

<section class="pb-5 mb-5">
 <div class="container">
  @php
   $days = $lessons->groupBy(fn($l)=>$l->lesson_date->format('Y-m-d'));
  @endphp

  @if($activeStudio!=='' || $activeTeacher!=='')
   <div class="schedule-active-filters mb-4">
    <span>Фильтр:</span>
    @if($activeStudio!=='')
     @php
      $activeStudioModel = $studios->firstWhere('id',(int)$activeStudio);
     @endphp
     @if($activeStudioModel)<strong>{{ $activeStudioModel->title }}</strong>@endif
    @endif
    @if($activeTeacher!=='')<strong>{{ $activeTeacher }}</strong>@endif
    <span class="text-white-50">· найдено занятий: {{ $lessons->count() }}</span>
   </div>
  @endif

  <div class="calendar-grid">
   @for($d=1;$d<=$start->daysInMonth;$d++)
    @php
     $date = $start->copy()->day($d);
     $key = $date->format('Y-m-d');
     $dayLessons = $days->get($key,collect());
    @endphp
    <article class="calendar-day {{ $dayLessons->count()?'has-lessons':'' }}">
     <div class="calendar-day-head">
      <span>{{ $d }}</span>
      <small>{{ mb_strtoupper($date->translatedFormat('D')) }}</small>
     </div>
     <div class="calendar-lessons">
      @foreach($dayLessons as $lesson)
       <div class="lesson-chip" style="--lesson:{{ $lesson->color ?: '#8a5cff' }}">
        <strong>{{ substr($lesson->starts_at,0,5) }} · {{ $lesson->title }}</strong>
        <span>{{ $lesson->group->name ?? 'Общее занятие' }} · {{ $lesson->studio->title ?? 'ШКИ' }}</span>
        @if($lesson->teacher_name)<small>Преподаватель: {{ $lesson->teacher_name }}</small>@endif
        @if($lesson->room)<small>{{ $lesson->room }}</small>@endif
       </div>
      @endforeach
     </div>
    </article>
   @endfor
  </div>
 </div>
</section>
@endsection
