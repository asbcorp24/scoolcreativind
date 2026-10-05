@extends('layouts.app')
@section('title','Предметы · Админ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Админ / предметы</div>
  <h1 class="display-3 fw-bold">Предметы</h1>
  <p class="text-white-50 mb-0">Сначала создайте предмет в справочнике, затем закрепите его за группой и назначьте преподавателя.</p>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  @if($errors->any())
   <div class="alert alert-danger">
    <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
   </div>
  @endif

  <div class="row g-4 mb-5">
   <div class="col-lg-5">
    <form class="form-shell h-100" method="post" action="{{ route('admin.subjects.save') }}">
     @csrf
     <div class="eyebrow">Справочник</div>
     <h3 class="mt-2 mb-4">Новый предмет</h3>
     <div class="mb-3">
      <label class="form-label">Название</label>
      <input class="form-control" name="title" required placeholder="Например: Основы 3D-моделирования">
     </div>
     <div class="mb-3">
      <label class="form-label">Студия</label>
      <select class="form-select" name="studio_id">
       <option value="">Общий предмет</option>
       @foreach($studios as $studio)<option value="{{ $studio->id }}">{{ $studio->title }}</option>@endforeach
      </select>
     </div>
     <div class="mb-3">
      <label class="form-label">Описание</label>
      <textarea class="form-control" rows="4" name="description"></textarea>
     </div>
     <div class="text-end"><button class="btn btn-neon">Добавить предмет</button></div>
    </form>
   </div>

   <div class="col-lg-7">
    <div class="glass-card p-4 h-100">
     <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
       <div class="eyebrow">Справочник</div>
       <h3 class="mt-2 mb-0">Список предметов</h3>
      </div>
      <span class="badge-soft">{{ $subjects->count() }}</span>
     </div>

     @if($subjects->isEmpty())
      <div class="text-white-50 py-4">
       Список пока пуст. Добавьте первый предмет слева — после сохранения он появится здесь и во всех выпадающих списках ниже.
      </div>
     @else
      <div class="table-responsive">
       <table class="table admin-table align-middle">
        <thead><tr><th>Название</th><th>Студия</th><th>Описание</th><th></th></tr></thead>
        <tbody>
         @foreach($subjects as $subject)
          <tr>
           <td><strong>{{ $subject->title }}</strong></td>
           <td>{{ $subject->studio->title ?? 'Общий' }}</td>
           <td class="text-white-50">{{ IlluminateSupportStr::limit($subject->description,80) }}</td>
           <td class="text-end">
            <form method="post" action="{{ route('admin.subjects.delete',$subject) }}" onsubmit="return confirm('Удалить предмет?')">
             @csrf @method('DELETE')
             <button class="btn btn-sm btn-outline-danger">Удалить</button>
            </form>
           </td>
          </tr>
         @endforeach
        </tbody>
       </table>
      </div>
     @endif
    </div>
   </div>
  </div>

  <div class="section-head mb-4">
   <div>
    <div class="eyebrow">Привязка</div>
    <h2>Предметы по группам</h2>
   </div>
   <p>Для каждой группы выберите предмет из справочника и преподавателя.</p>
  </div>

  @if($subjects->isEmpty())
   <div class="glass-card p-4 text-white-50">
    Нельзя закрепить предмет за группой: справочник предметов пока пуст.
   </div>
  @else
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
           <td>@if($teacher){{ $teacher->name }}@else<span class="text-warning">Не назначен</span>@endif</td>
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
      <div class="col-md-2 d-grid"><button class="btn btn-neon">Сохранить</button></div>
     </form>
    </div>
   @endforeach
  @endif
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
