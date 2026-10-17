@extends('layouts.app')
@section('title','Расписание · ШКИ')
@section('content')
@php
$filterParams=array_filter([
 'month'=>$month,
 'date'=>$anchorDate->toDateString(),
 'studio'=>$activeStudio,
 'teacher_id'=>$activeTeacherId,
 'teacher'=>$activeTeacher,
 'group'=>$activeGroup,
 'subject'=>$activeSubject,
 'room'=>$activeRoom,
],fn($v)=>$v!=='' && $v!==null);
@endphp

<section class="page-top">
 <div class="container">
  <div class="eyebrow">Learning calendar</div>
  <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
   <div>
    <h1 class="display-1 fw-bold">Расписание</h1>
    <p class="lead text-white-50">Общее опубликованное расписание ШКИ. Фильтры одинаковы для гостей, учеников, преподавателей и администраторов.</p>
   </div>
   <a class="btn btn-ghost" href="{{ route('schedule.ics',array_merge($filterParams,['from'=>$rangeStart->toDateString(),'to'=>$rangeEnd->toDateString()])) }}">Скачать .ICS</a>
  </div>

  <div class="d-flex gap-2 flex-wrap mt-4">
   @foreach(['month'=>'Месяц','week'=>'Неделя','day'=>'День','list'=>'Список'] as $mode=>$label)
    <a class="btn {{ $viewMode===$mode ? 'btn-neon' : 'btn-ghost' }}" href="{{ route('schedule',array_merge($filterParams,['view'=>$mode])) }}">{{ $label }}</a>
   @endforeach
  </div>

  <form method="get" class="schedule-filter-panel mt-4">
   <input type="hidden" name="view" value="{{ $viewMode }}">
   <div class="row g-3 align-items-end">
    <div class="col-md-3 col-xl-2">
     <label class="form-label">Месяц</label>
     <input type="month" class="form-control" name="month" value="{{ $month }}">
    </div>

    @if(in_array($viewMode,['day','week']))
     <div class="col-md-3 col-xl-2">
      <label class="form-label">{{ $viewMode==='day' ? 'Дата' : 'Неделя от даты' }}</label>
      <input type="date" class="form-control" name="date" value="{{ $anchorDate->toDateString() }}">
     </div>
    @endif

    <div class="col-md-4 col-xl-2">
     <label class="form-label">Студия</label>
     <select class="form-select" name="studio">
      <option value="">Все студии</option>
      @foreach($studios as $studio)<option value="{{ $studio->id }}" @selected((string)$activeStudio===(string)$studio->id)>{{ $studio->title }}</option>@endforeach
     </select>
    </div>

    <div class="col-md-4 col-xl-2">
     <label class="form-label">Группа</label>
     <select class="form-select" name="group">
      <option value="">Все группы</option>
      @foreach($groups as $group)<option value="{{ $group->id }}" @selected((string)$activeGroup===(string)$group->id)>{{ $group->name }}</option>@endforeach
     </select>
    </div>

    <div class="col-md-4 col-xl-2">
     <label class="form-label">Предмет</label>
     <select class="form-select" name="subject">
      <option value="">Все предметы</option>
      @foreach($subjects as $subject)<option value="{{ $subject->id }}" @selected((string)$activeSubject===(string)$subject->id)>{{ $subject->title }}</option>@endforeach
     </select>
    </div>

    <div class="col-md-4 col-xl-2">
     <label class="form-label">Преподаватель</label>
     <select class="form-select" name="teacher_id">
      <option value="">Все преподаватели</option>
      @foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected((string)$activeTeacherId===(string)$teacher->id)>{{ $teacher->name }}</option>@endforeach
     </select>
     @if($activeTeacher!=='')
      <input type="hidden" name="teacher" value="{{ $activeTeacher }}">
      <div class="form-text text-warning">Используется старый фильтр по ФИО: {{ $activeTeacher }}</div>
     @endif
    </div>

    <div class="col-md-4 col-xl-2">
     <label class="form-label">Аудитория</label>
     <select class="form-select" name="room">
      <option value="">Все аудитории</option>
      @foreach($rooms as $room)<option value="{{ $room }}" @selected($activeRoom===$room)>{{ $room }}</option>@endforeach
     </select>
    </div>

    <div class="col-md-4 col-xl-2 d-grid gap-2">
     <button class="btn btn-neon">Показать</button>
     <a class="btn btn-ghost btn-sm" href="{{ route('schedule',['month'=>$month,'view'=>$viewMode]) }}">Сбросить</a>
    </div>
   </div>
  </form>
 </div>
</section>

<section class="pb-5 mb-5">
 <div class="container">
  <div class="schedule-active-filters mb-4">
   <strong>
    @if($viewMode==='day')
     {{ $rangeStart->translatedFormat('d F Y') }}
    @elseif($viewMode==='week')
     {{ $rangeStart->format('d.m') }}–{{ $rangeEnd->format('d.m.Y') }}
    @else
     {{ $start->translatedFormat('F Y') }}
    @endif
   </strong>
   <span class="text-white-50">· найдено занятий: {{ $lessons->count() }}</span>
  </div>

  @if($viewMode==='month')
   @php($days=$lessons->groupBy(fn($l)=>$l->lesson_date->format('Y-m-d')))
   <div class="calendar-grid">
    @for($d=1;$d<=$start->daysInMonth;$d++)
     @php
      $date=$start->copy()->day($d);
      $dayLessons=$days->get($date->format('Y-m-d'),collect());
     @endphp
     <article class="calendar-day {{ $dayLessons->count()?'has-lessons':'' }}">
      <div class="calendar-day-head"><span>{{ $d }}</span><small>{{ mb_strtoupper($date->translatedFormat('D')) }}</small></div>
      <div class="calendar-lessons">
       @foreach($dayLessons as $lesson)
        <div class="lesson-chip {{ $lesson->status==='cancelled' ? 'opacity-50' : '' }}" style="--lesson:{{ $lesson->color ?: '#8a5cff' }}">
         <strong>{{ substr($lesson->starts_at,0,5) }} · {{ $lesson->title }}</strong>
         <span>{{ $lesson->group->name ?? 'Общее занятие' }} · {{ $lesson->studio->title ?? 'ШКИ' }}</span>
         @if($lesson->subject)<small>{{ $lesson->subject->title }}</small>@endif
         @if($lesson->display_teacher_name)<small>Преподаватель: {{ $lesson->display_teacher_name }}@if($lesson->substituteTeacher) <span class="text-warning">(замена)</span>@endif</small>@endif
         @if($lesson->substitution_note)<small class="text-warning">{{ $lesson->substitution_note }}</small>@endif
         @if($lesson->room)<small>Ауд. {{ $lesson->room }}</small>@endif
         @if($lesson->status!=='planned')<small><strong>{{ $lesson->status_label }}</strong>@if($lesson->status_note) · {{ $lesson->status_note }}@endif</small>@endif
        </div>
       @endforeach
      </div>
     </article>
    @endfor
   </div>
  @else
   @php($days=$lessons->groupBy(fn($l)=>$l->lesson_date->format('Y-m-d')))
   @forelse($days as $date=>$dayLessons)
    <div class="glass-card p-4 mb-3">
     <div class="eyebrow">{{ CarbonCarbon::parse($date)->translatedFormat('l, d F Y') }}</div>
     @foreach($dayLessons as $lesson)
      <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap py-3 border-bottom border-secondary-subtle {{ $lesson->status==='cancelled' ? 'opacity-50' : '' }}">
       <div>
        <strong class="fs-5">{{ substr($lesson->starts_at,0,5) }}–{{ substr($lesson->ends_at,0,5) }} · {{ $lesson->title }}</strong>
        <div class="text-white-50 mt-1">
         {{ $lesson->group->name ?? 'Общее занятие' }}
         @if($lesson->subject) · {{ $lesson->subject->title }}@endif
         @if($lesson->display_teacher_name) · {{ $lesson->display_teacher_name }}@if($lesson->substituteTeacher) (замена)@endif @endif
         @if($lesson->room) · ауд. {{ $lesson->room }}@endif
        </div>
        @if($lesson->planLesson)<div class="small mt-1">КТП №{{ $lesson->planLesson->lesson_number }} · {{ $lesson->planLesson->title }}</div>@endif
       </div>
       <div class="text-end">
        <span class="badge-soft">{{ $lesson->status_label }}</span>
        @if($lesson->status_note)<div class="small text-white-50 mt-1">{{ $lesson->status_note }}</div>@endif
       </div>
      </div>
     @endforeach
    </div>
   @empty
    <div class="glass-card p-4 text-white-50">На выбранный период занятий нет.</div>
   @endforelse
  @endif
 </div>
</section>
@endsection
