@extends('layouts.app')
@section('title','Журнал · '.(auth()->user()->is_admin ? 'Админ' : 'Преподаватель'))
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">{{ auth()->user()->is_admin ? 'Админ' : 'Кабинет преподавателя' }} / журнал</div>
  <h1 class="display-3 fw-bold">Электронный журнал</h1>
  @unless(auth()->user()->is_admin)
   <p class="text-white-50 mb-0">Доступны только закреплённые за вами группы и предметы.</p>
  @endunless
 </div>
</section>

<section class="pb-5">
 <div class="container">
  <div class="d-flex gap-2 flex-wrap mb-4">
   <a class="btn btn-neon" href="{{ route('admin.journal') }}">Журнал</a>
   <a class="btn btn-ghost" href="{{ route('admin.attendance') }}">Посещаемость</a>
   <a class="btn btn-ghost" href="{{ route('admin.homework') }}">Домашние задания</a>
   <a class="btn btn-ghost" href="{{ route('admin.schedule') }}">Расписание</a>
  </div>

  <form class="glass-card p-4 mb-4" method="get" id="journalFilterForm">
   <div class="row g-3 align-items-end">
    <div class="col-md-5">
     <label class="form-label">Группа</label>
     <select class="form-select" name="group_id" id="journalGroupSelect">
      <option value="">Выберите группу</option>
      @foreach($groups as $g)
       <option value="{{ $g->id }}" @selected(optional($group)->id===$g->id)>{{ $g->name }}</option>
      @endforeach
     </select>
    </div>

    <div class="col-md-5">
     <label class="form-label">Предмет</label>
     <select class="form-select" name="subject_id" id="journalSubjectSelect" {{ $group ? '' : 'disabled' }}>
      <option value="">Выберите предмет</option>
      @if($group)
       @foreach($group->subjects as $s)
        <option value="{{ $s->id }}" @selected(optional($subject)->id===$s->id)>{{ $s->title }}</option>
       @endforeach
      @endif
     </select>
     <div class="form-text text-white-50" id="journalSubjectHint">
      @if($group && $group->subjects->isEmpty())
       У этой группы пока нет закреплённых предметов. Добавьте их в разделе «Предметы».
      @else
       Показываются только предметы, закреплённые за выбранной группой.
      @endif
     </div>
    </div>

    <div class="col-md-2 d-grid">
     <button class="btn btn-neon">Открыть</button>
    </div>
   </div>
  </form>

  @if($group && !$subject && $group->subjects->isNotEmpty())
   <div class="alert alert-info">Выберите предмет для группы «{{ $group->name }}».</div>
  @endif

  @if($group && $group->subjects->isEmpty())
   <div class="alert alert-warning">
    Для группы «{{ $group->name }}» не закреплён ни один предмет.
    <a href="{{ route('admin.subjects') }}">Перейти к предметам</a>
   </div>
  @endif

  @if($group && $subject)
   <form class="glass-card p-4 mb-4" method="post" action="{{ route('admin.journal.lessons.create') }}">
    @csrf
    <input type="hidden" name="study_group_id" value="{{ $group->id }}">
    <input type="hidden" name="subject_id" value="{{ $subject->id }}">
    <div class="row g-3">
     <div class="col-md-3"><label class="form-label">Дата</label><input type="date" class="form-control" name="lesson_date" value="{{ now()->toDateString() }}" required></div>
     <div class="col-md-7"><label class="form-label">Тема урока</label><input class="form-control" name="topic" placeholder="Тема урока" required></div>
     <div class="col-md-2 d-flex align-items-end"><button class="btn btn-ghost w-100">+ Урок</button></div>
     <div class="col-12">
      <label class="form-label">Практическое задание / описание урока</label>
      <textarea class="form-control" rows="4" name="notes" placeholder="Что нужно выполнить, требования к работе, материалы, ссылки..."></textarea>
      <div class="form-text text-white-50">Этот текст ученик увидит, когда откроет практическую работу к уроку.</div>
     </div>
    </div>
   </form>

   @foreach($lessons as $lesson)
    <div class="glass-card p-4 mb-4">
     <h4>{{ $lesson->lesson_date->format('d.m.Y') }} · {{ $lesson->topic }}</h4>
     <div class="table-responsive">
      <table class="table admin-table align-middle">
       <thead><tr><th>Ученик</th><th>Посещение</th><th>Оценка</th><th>Комментарий</th><th></th></tr></thead>
       <tbody>
        @foreach($group->students as $student)
         @php
          $entry = $lesson->entries->firstWhere('student_id',$student->id);
         @endphp
         @if($entry)
          <tr>
           <form method="post" action="{{ route('admin.journal.entries.update',$entry) }}">
            @csrf
            @method('PATCH')
            <td>{{ $student->name }}</td>
            <td>
             <select class="form-select form-select-sm" name="attendance">
              @foreach(['present'=>'Был','absent'=>'Отсутствовал','late'=>'Опоздал','excused'=>'Уваж. причина'] as $k=>$v)
               <option value="{{ $k }}" @selected($entry->attendance===$k)>{{ $v }}</option>
              @endforeach
             </select>
            </td>
            <td><input class="form-control form-control-sm" name="grade" type="number" min="1" max="100" step="0.01" value="{{ $entry->grade }}"></td>
            <td><input class="form-control form-control-sm" name="comment" value="{{ $entry->comment }}"><input type="hidden" name="grade_label" value="{{ $entry->grade_label }}"></td>
            <td><button class="btn btn-sm btn-ghost">Сохранить</button></td>
           </form>
          </tr>
         @endif
        @endforeach
       </tbody>
      </table>
     </div>
    </div>
   @endforeach
  @endif
 </div>
</section>

<script>
document.addEventListener('DOMContentLoaded',()=>{
 const groupSelect=document.getElementById('journalGroupSelect');
 const subjectSelect=document.getElementById('journalSubjectSelect');
 const hint=document.getElementById('journalSubjectHint');
 const options=@json($subjectOptions);

 if(!groupSelect || !subjectSelect)return;

 const currentSubject='{{ optional($subject)->id ?? '' }}';

 const renderSubjects=()=>{
  const groupId=groupSelect.value;
  const subjects=options[groupId] || [];

  subjectSelect.innerHTML='<option value="">Выберите предмет</option>';

  if(!groupId){
   subjectSelect.disabled=true;
   if(hint)hint.textContent='Сначала выберите группу.';
   return;
  }

  subjectSelect.disabled=false;

  subjects.forEach(item=>{
   const option=document.createElement('option');
   option.value=String(item.id);
   option.textContent=item.title;
   if(String(item.id)===String(currentSubject))option.selected=true;
   subjectSelect.appendChild(option);
  });

  if(hint){
   hint.textContent=subjects.length
    ? 'Показываются только предметы, закреплённые за выбранной группой.'
    : 'У этой группы пока нет закреплённых предметов. Добавьте их в разделе «Предметы».';
  }
 };

 groupSelect.addEventListener('change',()=>{
  renderSubjects();
  subjectSelect.value='';
 });

 renderSubjects();
});
</script>
@endsection
