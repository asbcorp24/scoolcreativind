@extends('layouts.app')
@section('title',$student->name.' · Ученик')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Кабинет преподавателя / ученик</div>
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
   <div><h1 class="display-3 fw-bold m-0">{{ $student->name }}</h1><p class="text-white-50 mt-2 mb-0">{{ $studentGroups->pluck('name')->implode(' · ') }}</p></div>
   <a href="{{ route('admin.teacher-groups') }}" class="btn btn-ghost">← Мои группы</a>
  </div>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  <div class="row g-3 mb-5">
   <div class="col-6 col-lg"><div class="glass-card p-3 h-100"><div class="small text-white-50">Уроков</div><strong class="fs-3">{{ $summary['lessons'] }}</strong></div></div>
   <div class="col-6 col-lg"><div class="glass-card p-3 h-100"><div class="small text-white-50">Посещаемость</div><strong class="fs-3">{{ $summary['attendance']!==null ? $summary['attendance'].'%' : '—' }}</strong></div></div>
   <div class="col-6 col-lg"><div class="glass-card p-3 h-100"><div class="small text-white-50">Пропуски</div><strong class="fs-3 text-danger">{{ $summary['absent'] }}</strong></div></div>
   <div class="col-6 col-lg"><div class="glass-card p-3 h-100"><div class="small text-white-50">Опоздания</div><strong class="fs-3 text-warning">{{ $summary['late'] }}</strong></div></div>
   <div class="col-6 col-lg"><div class="glass-card p-3 h-100"><div class="small text-white-50">Средняя оценка</div><strong class="fs-3">{{ $summary['avg_grade'] ?? '—' }}</strong></div></div>
  </div>

  <div class="row g-4">
   <div class="col-xl-7">
    <div class="section-head"><div><div class="eyebrow">Journal</div><h2>Последние оценки и посещаемость</h2></div></div>
    <div class="glass-card p-4">
     <div class="table-responsive">
      <table class="table admin-table align-middle">
       <thead><tr><th>Дата</th><th>Предмет</th><th>Урок</th><th>Посещение</th><th>Оценка</th></tr></thead>
       <tbody>
        @forelse($entries as $entry)
         <tr>
          <td>{{ $entry->lesson->lesson_date->format('d.m.Y') }}</td>
          <td>{{ $entry->lesson->subject->title ?? '' }}</td>
          <td>{{ $entry->lesson->topic }}</td>
          <td>{{ ['present'=>'Был','absent'=>'Отсутствовал','late'=>'Опоздал','excused'=>'Уваж.'][$entry->attendance] ?? $entry->attendance }}</td>
          <td><strong>{{ $entry->grade ?? '—' }}</strong></td>
         </tr>
        @empty<tr><td colspan="5" class="text-white-50">Записей пока нет.</td></tr>@endforelse
       </tbody>
      </table>
     </div>
    </div>
   </div>

   <div class="col-xl-5">
    <div class="section-head"><div><div class="eyebrow">Homework</div><h2>Домашние задания</h2></div></div>
    <div class="glass-card p-4">
     @forelse($homework as $item)
      <div class="py-3 border-bottom border-secondary-subtle">
       <strong>{{ $item->assignment->title }}</strong>
       <div class="small text-white-50">{{ $item->assignment->subject->title ?? '' }} · {{ $item->status }}</div>
       <div class="mt-1">Оценка: <strong>{{ $item->score ?? '—' }}</strong></div>
       <a class="btn btn-sm btn-ghost mt-2" href="{{ route('admin.homework.submissions',$item->assignment) }}">Открыть проверку</a>
      </div>
     @empty<div class="text-white-50">Отправленных работ пока нет.</div>@endforelse
    </div>
   </div>
  </div>

  <div class="section-head mt-5"><div><div class="eyebrow">Portfolio</div><h2>Практические работы и портфолио</h2></div></div>
  <div class="row g-3">
   @forelse($portfolio as $work)
    <div class="col-md-6 col-xl-4">
     <div class="glass-card p-4 h-100">
      <div class="eyebrow">{{ $work->type }}</div>
      <h4>{{ $work->title }}</h4>
      @if($work->lesson)<div class="small text-white-50">{{ $work->lesson->subject->title ?? '' }} · {{ $work->lesson->topic }}</div>@endif
      @if($work->description)<p class="small text-white-50 mt-2">{{ IlluminateSupportStr::limit($work->description,180) }}</p>@endif
      @if($profile)<a class="btn btn-sm btn-ghost mt-2" target="_blank" href="{{ route('portfolio.show',$profile) }}">Портфолио ↗</a>@endif
     </div>
    </div>
   @empty
    <div class="col-12"><div class="glass-card p-4 text-white-50">Практических работ пока нет.</div></div>
   @endforelse
  </div>
 </div>
</section>
@endsection
