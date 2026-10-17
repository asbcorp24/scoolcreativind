@extends('layouts.app')
@section('title','Мои группы · Преподаватель')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Кабинет преподавателя</div>
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
   <div><h1 class="display-3 fw-bold m-0">Мои группы</h1><p class="text-white-50 mt-2 mb-0">Ученики, предметы, посещаемость и быстрый переход в журнал.</p></div>
   <a href="{{ route('admin.teacher-dashboard') }}" class="btn btn-ghost">← Сегодня</a>
  </div>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  <div class="row g-4">
   @forelse($groups as $group)
    @php($stat=$stats[$group->id] ?? null)
    <div class="col-xl-6">
     <article class="glass-card p-4 h-100">
      <div class="d-flex justify-content-between gap-3 align-items-start mb-3">
       <div>
        <div class="eyebrow">{{ $group->academicYear->name ?? '' }} · {{ $group->study_year }} год</div>
        <h2 class="h3 mb-1">{{ $group->name }}</h2>
        <div class="text-white-50">{{ $group->studio->title ?? '' }}</div>
       </div>
       <span class="badge-soft">{{ $group->students->count() }} учеников</span>
      </div>

      <div class="row g-2 mb-4">
       <div class="col-4"><div class="p-3 rounded border border-secondary-subtle"><div class="small text-white-50">Уроков</div><strong>{{ $stat['lessons'] ?? 0 }}</strong></div></div>
       <div class="col-4"><div class="p-3 rounded border border-secondary-subtle"><div class="small text-white-50">Посещаемость</div><strong>{{ isset($stat['attendance']) && $stat['attendance']!==null ? $stat['attendance'].'%' : '—' }}</strong></div></div>
       <div class="col-4"><div class="p-3 rounded border border-secondary-subtle"><div class="small text-white-50">Предметов</div><strong>{{ $group->subjects->count() }}</strong></div></div>
      </div>

      <div class="mb-3">
       @foreach($group->subjects as $subject)
        <div class="d-flex justify-content-between align-items-center gap-2 py-2 border-bottom border-secondary-subtle">
         <strong>{{ $subject->title }}</strong>
         <div class="d-flex gap-2">
          <a class="btn btn-sm btn-ghost" href="{{ route('admin.subjects.lessons',$subject) }}">КТП</a>
          <a class="btn btn-sm btn-ghost" href="{{ route('admin.journal',['group_id'=>$group->id,'subject_id'=>$subject->id]) }}">Журнал</a>
         </div>
        </div>
       @endforeach
      </div>

      <details>
       <summary class="btn btn-ghost">Ученики</summary>
       <div class="mt-3">
        @foreach($group->students->sortBy('name') as $student)
         <div class="d-flex justify-content-between align-items-center gap-3 py-2 border-bottom border-secondary-subtle">
          <span>{{ $student->name }}</span>
          <a class="btn btn-sm btn-neon" href="{{ route('admin.teacher-students.show',$student) }}">Открыть</a>
         </div>
        @endforeach
       </div>
      </details>
     </article>
    </div>
   @empty
    <div class="col-12"><div class="glass-card p-4 text-white-50">Вам пока не назначены учебные группы.</div></div>
   @endforelse
  </div>
 </div>
</section>
@endsection
