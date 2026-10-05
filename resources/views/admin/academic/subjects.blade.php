@extends('layouts.app')
@section('title','Предметы · Админ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Админ / предметы</div>
  <h1 class="display-3 fw-bold">Предметы</h1>
  <p class="text-white-50 mb-0">Предмет закрепляется за конкретной группой вместе с преподавателем.</p>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  <form class="form-shell mb-5" method="post" action="{{ route('admin.subjects.save') }}">
   @csrf
   <div class="row g-3">
    <div class="col-md-7"><label class="form-label">Название</label><input class="form-control" name="title" required></div>
    <div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" name="description"></textarea></div>
    <div class="col-12 text-end"><button class="btn btn-neon">Добавить предмет</button></div>
   </div>
  </form>

  @foreach($groups as $g)
   <div class="glass-card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
     <div>
      <strong class="fs-5">{{ $g->name }}</strong>
      <div class="small text-white-50">{{ $g->study_year }} год</div>
     </div>
    </div>

    @if($g->subjects->count())
     <div class="table-responsive mb-4">
      <table class="table admin-table align-middle">
       <thead><tr><th>Предмет</th><th>Преподаватель</th></tr></thead>
       <tbody>
        @foreach($g->subjects as $attached)
         @php
          $teacher = $teachers->firstWhere('id',(int)$attached->pivot->teacher_id);
         @endphp
         <tr>
          <td><strong>{{ $attached->title }}</strong></td>
          <td>
           @if($teacher)
            {{ $teacher->name }}
           @else
            <span class="text-warning">Не назначен</span>
           @endif
          </td>
         </tr>
        @endforeach
       </tbody>
      </table>
     </div>
    @endif

    <form class="row g-3 align-items-end" method="post" action="{{ route('admin.groups.subjects.attach',$g) }}">
     @csrf
     <div class="col-md-5">
      <label class="form-label">Предмет</label>
      <select class="form-select" name="subject_id" required data-subject-select>
       @foreach($subjects as $s)
        @php
         $attached = $g->subjects->firstWhere('id',$s->id);
        @endphp
        <option value="{{ $s->id }}" data-teacher-id="{{ $attached?->pivot?->teacher_id ?? '' }}">{{ $s->title }}</option>
       @endforeach
      </select>
     </div>
     <div class="col-md-5">
      <label class="form-label">Преподаватель</label>
      <select class="form-select" name="teacher_id" data-teacher-select>
       <option value="">Без преподавателя</option>
       @foreach($teachers as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
      </select>
     </div>
     <div class="col-md-2 d-grid">
      <button class="btn btn-neon">Сохранить</button>
     </div>
    </form>
   </div>
  @endforeach
 </div>
</section>

<script>
document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('form').forEach(form=>{
  const subject=form.querySelector('[data-subject-select]');
  const teacher=form.querySelector('[data-teacher-select]');
  if(!subject||!teacher)return;

  const sync=()=>{
   const option=subject.options[subject.selectedIndex];
   teacher.value=option?.dataset.teacherId||'';
  };

  subject.addEventListener('change',sync);
  sync();
 });
});
</script>
@endsection
