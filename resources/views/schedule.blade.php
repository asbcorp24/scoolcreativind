@extends('layouts.app')
@section('title','Расписание · ШКИ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Learning calendar</div><div class="d-flex justify-content-between align-items-end gap-3 flex-wrap"><div><h1 class="display-1 fw-bold">Расписание</h1><p class="lead text-white-50">Занятия, мастер-классы и студийная практика по дням.</p></div><form method="get" class="d-flex gap-2"><input type="month" class="form-control" name="month" value="{{ $month }}"><button class="btn btn-ghost">Показать</button></form></div></div></section>
<section class="pb-5 mb-5"><div class="container">
@php($days=$lessons->groupBy(fn($l)=>$l->lesson_date->format('Y-m-d')))
<div class="calendar-grid">
@for($d=1;$d<=$start->daysInMonth;$d++)
 @php($date=$start->copy()->day($d); $key=$date->format('Y-m-d'); $dayLessons=$days->get($key,collect()))
 <article class="calendar-day {{ $dayLessons->count()?'has-lessons':'' }}">
  <div class="calendar-day-head"><span>{{ $d }}</span><small>{{ mb_strtoupper($date->translatedFormat('D')) }}</small></div>
  <div class="calendar-lessons">
   @foreach($dayLessons as $lesson)
    <div class="lesson-chip" style="--lesson:{{ $lesson->color ?: '#8a5cff' }}">
      <strong>{{ substr($lesson->starts_at,0,5) }} · {{ $lesson->title }}</strong>
      <span>{{ $lesson->group->name ?? 'Общее занятие' }} · {{ $lesson->studio->title ?? 'ШКИ' }}</span>
      @if($lesson->room)<small>{{ $lesson->room }}</small>@endif
    </div>
   @endforeach
  </div>
 </article>
@endfor
</div>
</div></section>
@endsection
