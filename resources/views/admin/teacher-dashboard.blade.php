@extends('layouts.app')
@section('title','Сегодня · Кабинет преподавателя')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Кабинет преподавателя</div>
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
   <div>
    <h1 class="display-3 fw-bold m-0">Сегодня</h1>
    <p class="text-white-50 mt-2 mb-0">{{ now()->translatedFormat('d F Y, l') }} · {{ auth()->user()->name }}</p>
   </div>
   <a href="{{ route('schedule') }}" class="btn btn-ghost" target="_blank">Общее расписание ↗</a>
  </div>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <div class="row g-3 mb-5">
   <div class="col-6 col-xl-3"><div class="glass-card p-4 h-100"><div class="small text-white-50">Занятий сегодня</div><strong class="display-6">{{ $today->count() }}</strong></div></div>
   <div class="col-6 col-xl-3"><div class="glass-card p-4 h-100"><div class="small text-white-50">Нужно заполнить журнал</div><strong class="display-6 text-warning">{{ $needsJournal->count() }}</strong></div></div>
   <div class="col-6 col-xl-3"><div class="glass-card p-4 h-100"><div class="small text-white-50">Работ на проверку</div><strong class="display-6 text-info">{{ $pendingHomework->count() }}</strong></div></div>
   <div class="col-6 col-xl-3"><div class="glass-card p-4 h-100"><div class="small text-white-50">Моих групп</div><strong class="display-6">{{ $groups->count() }}</strong></div></div>
  </div>

  <div class="section-head">
   <div><div class="eyebrow">Today</div><h2>Занятия сегодня</h2></div>
   <a class="btn btn-ghost" href="{{ route('admin.schedule') }}">Управлять расписанием</a>
  </div>

  <div class="row g-3 mb-5">
   @forelse($today as $lesson)
    <div class="col-lg-6">
     <article class="glass-card p-4 h-100">
      <div class="d-flex justify-content-between gap-3 align-items-start">
       <div>
        <div class="eyebrow">{{ substr($lesson->starts_at,0,5) }}–{{ substr($lesson->ends_at,0,5) }} · {{ $lesson->room ?: 'аудитория не указана' }}</div>
        <h3 class="mt-2">{{ $lesson->title }}</h3>
        <div class="text-white-50">{{ $lesson->group->name ?? 'Общее занятие' }}@if($lesson->subject) · {{ $lesson->subject->title }}@endif</div>
        @if($lesson->planLesson)<div class="small mt-2">КТП №{{ $lesson->planLesson->lesson_number }} · {{ $lesson->planLesson->title }}</div>@endif
       </div>
       <span class="badge-soft">{{ $lesson->status_label }}</span>
      </div>
      <div class="d-flex gap-2 flex-wrap mt-4">
       @if(!$lesson->journal_lesson_id && $lesson->status!=='cancelled')
        <form method="post" action="{{ route('admin.schedule.conduct',$lesson) }}">@csrf
         <button class="btn btn-neon">Провести урок</button>
        </form>
       @elseif($lesson->journalLesson)
        <a class="btn btn-neon" href="{{ route('admin.journal',['group_id'=>$lesson->study_group_id,'subject_id'=>$lesson->subject_id]) }}">Открыть журнал</a>
       @endif
       <a class="btn btn-ghost" href="{{ route('admin.schedule') }}">Расписание</a>
      </div>
     </article>
    </div>
   @empty
    <div class="col-12"><div class="glass-card p-4 text-white-50">На сегодня занятий нет.</div></div>
   @endforelse
  </div>

  @if($needsJournal->count())
   <div class="section-head"><div><div class="eyebrow">Attention</div><h2>Нужно оформить прошедшие занятия</h2></div></div>
   <div class="glass-card p-3 p-lg-4 mb-5">
    @foreach($needsJournal as $lesson)
     <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap py-3 border-bottom border-secondary-subtle">
      <div>
       <strong>{{ $lesson->lesson_date->format('d.m.Y') }} · {{ $lesson->title }}</strong>
       <div class="small text-white-50">{{ $lesson->group->name ?? '' }}@if($lesson->subject) · {{ $lesson->subject->title }}@endif</div>
      </div>
      <form method="post" action="{{ route('admin.schedule.conduct',$lesson) }}">@csrf
       <button class="btn btn-sm btn-neon">Провести и открыть журнал</button>
      </form>
     </div>
    @endforeach
   </div>
  @endif

  <div class="row g-4 mb-5">
   <div class="col-xl-7">
    <div class="section-head"><div><div class="eyebrow">Progress</div><h2>Мои группы и КТП</h2></div></div>
    <div class="glass-card p-4">
     @forelse($progress as $row)
      <div class="py-3 border-bottom border-secondary-subtle">
       <div class="d-flex justify-content-between gap-3">
        <div><strong>{{ $row['group']->name }}</strong><div class="small text-white-50">{{ $row['subject']->title }}</div></div>
        <strong>{{ $row['conducted'] }} / {{ $row['total'] }}</strong>
       </div>
       <div class="progress mt-2" style="height:8px"><div class="progress-bar" style="width:{{ $row['percent'] }}%"></div></div>
       <div class="d-flex gap-2 mt-3">
        <a class="btn btn-sm btn-ghost" href="{{ route('admin.subjects.lessons',$row['subject']) }}">КТП</a>
        <a class="btn btn-sm btn-ghost" href="{{ route('admin.journal',['group_id'=>$row['group']->id,'subject_id'=>$row['subject']->id]) }}">Журнал</a>
       </div>
      </div>
     @empty
      <div class="text-white-50">Нет назначенных предметов.</div>
     @endforelse
    </div>
   </div>

   <div class="col-xl-5">
    <div class="section-head"><div><div class="eyebrow">Homework</div><h2>На проверку</h2></div></div>
    <div class="glass-card p-4">
     @forelse($pendingHomework as $submission)
      <div class="py-3 border-bottom border-secondary-subtle">
       <strong>{{ $submission->student->name ?? 'Ученик' }}</strong>
       <div class="small text-white-50">{{ $submission->assignment->group->name ?? '' }} · {{ $submission->assignment->subject->title ?? '' }}</div>
       <div class="mt-2"><a class="btn btn-sm btn-ghost" href="{{ route('admin.homework.submissions',$submission->assignment) }}">Проверить</a></div>
      </div>
     @empty
      <div class="text-white-50">Новых работ на проверку нет.</div>
     @endforelse
    </div>
   </div>
  </div>

  @if($upcoming->count())
   <div class="section-head"><div><div class="eyebrow">Next</div><h2>Ближайшие занятия</h2></div></div>
   <div class="glass-card p-4 mb-5">
    @foreach($upcoming as $lesson)
     <div class="d-flex justify-content-between gap-3 py-3 border-bottom border-secondary-subtle">
      <div><strong>{{ $lesson->lesson_date->format('d.m') }} · {{ substr($lesson->starts_at,0,5) }} · {{ $lesson->title }}</strong><div class="small text-white-50">{{ $lesson->group->name ?? '' }}@if($lesson->subject) · {{ $lesson->subject->title }}@endif</div></div>
      <span class="badge-soft">{{ $lesson->status_label }}</span>
     </div>
    @endforeach
   </div>
  @endif

  @if($announcements->count())
   <div class="section-head"><div><div class="eyebrow">Notice</div><h2>Объявления</h2></div></div>
   <div class="row g-3">
    @foreach($announcements as $notice)
     <div class="col-md-6"><div class="glass-card p-4 h-100"><div class="eyebrow">{{ $notice->group->name ?? 'Всем' }}</div><h4>{{ $notice->title }}</h4><p class="text-white-50 mb-0">{{ $notice->body }}</p></div></div>
    @endforeach
   </div>
  @endif
 </div>
</section>
@endsection
