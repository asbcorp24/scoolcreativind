@extends('layouts.app')
@section('title','Журнал · '.(auth()->user()->is_admin ? 'Админ' : 'Преподаватель'))
@section('content')
<style>
.journal-lesson-card{overflow:hidden}
.journal-lesson-summary{cursor:pointer;list-style:none;user-select:none}
.journal-lesson-summary::-webkit-details-marker{display:none}
.journal-lesson-summary:hover{background:rgba(255,255,255,.025)}
.journal-chevron{font-size:1.5rem;line-height:1;transition:transform .2s ease}
.journal-lesson-card[open] .journal-chevron{transform:rotate(180deg)}
.journal-summary-link{position:relative;z-index:2}
.journal-student-card{padding:14px;border-radius:16px;background:rgba(255,255,255,.035);border:1px solid rgba(255,255,255,.08);margin-bottom:12px}
.journal-student-name{font-size:1.05rem}
.journal-touch-control{min-height:48px;font-size:16px}
@media (max-width: 767.98px){
 .page-top .display-3{font-size:2rem}
 #journalFilterForm{padding:16px!important}
 .journal-lesson-summary{padding:16px!important}
 .journal-lesson-body{padding:0 12px 12px!important}
 .journal-mobile-list{display:grid;gap:0}
 .journal-student-card .btn{min-height:44px}
}
</style>

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
    <div class="col-lg-3">
     <label class="form-label">Группа</label>
     <select class="form-select" name="group_id" id="journalGroupSelect">
      <option value="">Выберите группу</option>
      @foreach($groups as $g)
       <option value="{{ $g->id }}" @selected(optional($group)->id===$g->id)>{{ $g->name }}</option>
      @endforeach
     </select>
    </div>

    <div class="col-lg-3">
     <label class="form-label">Предмет</label>
     <select class="form-select" name="subject_id" id="journalSubjectSelect" {{ $group ? '' : 'disabled' }}>
      <option value="">Выберите предмет</option>
      @if($group)
       @foreach($group->subjects as $s)
        <option value="{{ $s->id }}" @selected(optional($subject)->id===$s->id)>{{ $s->title }}</option>
       @endforeach
      @endif
     </select>
    </div>

    <div class="col-lg-2">
     <label class="form-label">Дата с</label>
     <input type="date" class="form-control" name="date_from" value="{{ $dateFrom }}">
    </div>

    <div class="col-lg-2">
     <label class="form-label">Дата по</label>
     <input type="date" class="form-control" name="date_to" value="{{ $dateTo }}">
    </div>

    <div class="col-lg-2">
     <label class="form-label">Ученик</label>
     <select class="form-select" name="student_id" {{ $group ? '' : 'disabled' }}>
      <option value="">Все ученики</option>
      @if($group)
       @foreach($group->students->sortBy('name') as $studentOption)
        <option value="{{ $studentOption->id }}" @selected((int)$studentId===(int)$studentOption->id)>{{ $studentOption->name }}</option>
       @endforeach
      @endif
     </select>
    </div>

    <div class="col-lg-8">
     <label class="form-label">Урок</label>
     <input class="form-control" name="lesson_q" value="{{ $lessonQuery }}" placeholder="Название урока или номер">
     <div class="form-text text-white-50" id="journalSubjectHint">
      Фильтр применяется к проведённым урокам. Группа и предмет определяют доступный журнал.
     </div>
    </div>

    <div class="col-lg-2 d-grid">
     <button class="btn btn-neon">Применить</button>
    </div>

    <div class="col-lg-2 d-grid">
     <a class="btn btn-ghost" href="{{ route('admin.journal',array_filter([
      'group_id'=>optional($group)->id,
      'subject_id'=>optional($subject)->id,
     ])) }}">Сбросить</a>
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
   <div class="d-flex gap-2 flex-wrap mb-4">
    <a class="btn btn-ghost" target="_blank" href="{{ route('admin.attendance.print',[
      'group_id'=>$group->id,
      'subject_id'=>$subject->id,
      'date_from'=>request('date_from', now()->startOfMonth()->toDateString()),
      'date_to'=>request('date_to', now()->toDateString())
    ]) }}">Печать журнала</a>
    <a class="btn btn-ghost" target="_blank" href="{{ route('admin.ktp.print',[
      'subject'=>$subject,
      'group_id'=>$group->id
    ]) }}">Печать КТП</a>
   </div>
   <form class="glass-card p-4 mb-4" method="post" action="{{ route('admin.journal.lessons.create') }}">
    @csrf
    <input type="hidden" name="study_group_id" value="{{ $group->id }}">
    <input type="hidden" name="subject_id" value="{{ $subject->id }}">
    <div class="row g-3">
     <div class="col-md-3">
      <label class="form-label">Дата проведения</label>
      <input type="date" class="form-control" name="lesson_date" value="{{ now()->toDateString() }}" required>
     </div>
     <div class="col-md-7">
      <label class="form-label">Урок из календарно-тематического плана</label>
      <select class="form-select" name="subject_lesson_id" required>
       <option value="">Выберите урок</option>
       @foreach($planLessons as $planLesson)
        <option value="{{ $planLesson->id }}">
         Урок {{ $planLesson->lesson_number }} · {{ $planLesson->title }}
        </option>
       @endforeach
      </select>
      @if($planLessons->isEmpty())
       <div class="form-text text-warning">
        В предмете ещё нет опубликованных уроков.
        <a href="{{ route('admin.subjects.lessons',$subject) }}">Открыть КТП</a>
       </div>
      @else
       <div class="form-text text-white-50">Название, содержание, материалы и домашнее задание берутся из плана предмета.</div>
      @endif
     </div>
     <div class="col-md-2 d-flex align-items-end">
      <button class="btn btn-neon w-100" @disabled($planLessons->isEmpty())>Провести урок</button>
     </div>
     <div class="col-12">
      <label class="form-label">Комментарий преподавателя к проведению</label>
      <textarea class="form-control" rows="3" name="notes" placeholder="Дополнительные пояснения именно для этого проведения урока..."></textarea>
     </div>
    </div>
   </form>

   @foreach($lessons as $lesson)
    @php
     $lessonEntries=$lesson->entries;
     $lessonAbsent=$lessonEntries->where('attendance','absent')->count();
     $lessonLate=$lessonEntries->where('attendance','late')->count();
     $lessonGraded=$lessonEntries->whereNotNull('grade')->count();
     $openLesson = request('open_lesson') ? (int)request('open_lesson')===(int)$lesson->id : $loop->first;
    @endphp
    <details class="glass-card p-0 mb-3 journal-lesson-card" @if($openLesson) open @endif>
     <summary class="journal-lesson-summary p-3 p-lg-4">
      <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
       <div class="min-w-0">
        <div class="small text-white-50">
         {{ $lesson->lesson_date->format('d.m.Y') }}
         @if($lesson->planLesson) · Урок {{ $lesson->planLesson->lesson_number }} @endif
        </div>
        <div class="fw-bold fs-5 text-truncate">{{ $lesson->topic }}</div>
        <div class="d-flex gap-2 flex-wrap mt-2">
         <span class="badge-soft">Оценок: {{ $lessonGraded }}/{{ $lessonEntries->count() }}</span>
         @if($lessonAbsent)<span class="badge bg-danger">Нет: {{ $lessonAbsent }}</span>@endif
         @if($lessonLate)<span class="badge bg-warning text-dark">Опоздали: {{ $lessonLate }}</span>@endif
         @if($lesson->homeworkAssignment)<span class="badge-soft">Есть ДЗ</span>@endif
        </div>
       </div>
       <div class="d-flex align-items-center gap-2">
        @if($lesson->planLesson)
         <a class="btn btn-sm btn-ghost journal-summary-link" href="{{ route('admin.subjects.lessons',$subject) }}">КТП</a>
        @endif
        <span class="journal-chevron">⌄</span>
       </div>
      </div>
     </summary>

     <div class="journal-lesson-body p-3 p-lg-4 pt-0">
      <div class="d-flex justify-content-end gap-2 flex-wrap mb-3">
       <form method="post" action="{{ route('admin.journal.lessons.all-present',$lesson) }}" onsubmit="return confirm('Отметить всех учеников присутствующими?')">
        @csrf
        <button class="btn btn-sm btn-ghost">✓ Все присутствуют</button>
       </form>
       @if($lesson->homeworkAssignment)
        <a class="btn btn-sm btn-ghost" href="{{ route('admin.homework.submissions',$lesson->homeworkAssignment) }}">Проверить ДЗ</a>
       @endif
      </div>
      <div class="d-none d-lg-block table-responsive">
       <table class="table admin-table align-middle">
        <thead><tr><th>Ученик</th><th>Посещение</th><th>Оценка</th><th>Комментарий</th><th></th></tr></thead>
        <tbody>
         @foreach($group->students as $student)
          @continue($studentId && (int)$student->id !== (int)$studentId)
          @php($entry = $lesson->entries->firstWhere('student_id',$student->id))
          @if($entry)
           <tr>
            <td>{{ $student->name }}</td>
            <td colspan="4">
             <form method="post" action="{{ route('admin.journal.entries.update',$entry) }}" class="journal-entry-form row g-2 align-items-center">
              @csrf
              @method('PATCH')
              <div class="col-xl-3">
               <select class="form-select form-select-sm" name="attendance">
                @foreach(['present'=>'Был','absent'=>'Отсутствовал','late'=>'Опоздал','excused'=>'Уваж. причина'] as $k=>$v)
                 <option value="{{ $k }}" @selected($entry->attendance===$k)>{{ $v }}</option>
                @endforeach
               </select>
              </div>
              <div class="col-xl-2">
               <input class="form-control form-control-sm" name="grade" type="number" min="1" max="100" step="0.01" value="{{ $entry->grade }}" placeholder="Оценка">
              </div>
              <div class="col-xl-4">
               <input class="form-control form-control-sm" name="comment" value="{{ $entry->comment }}" placeholder="Комментарий">
               <input type="hidden" name="grade_label" value="{{ $entry->grade_label }}">
              </div>
              <div class="col-xl-3 text-nowrap">
               <button class="btn btn-sm btn-ghost journal-save-btn" type="submit">Сохранить</button>
               <span class="small ms-2 journal-save-status text-white-50"></span>
              </div>
             </form>
            </td>
           </tr>
          @endif
         @endforeach
        </tbody>
       </table>
      </div>

      <div class="d-lg-none journal-mobile-list">
       @foreach($group->students as $student)
        @continue($studentId && (int)$student->id !== (int)$studentId)
        @php($entry = $lesson->entries->firstWhere('student_id',$student->id))
        @if($entry)
         <form method="post" action="{{ route('admin.journal.entries.update',$entry) }}" class="journal-entry-form journal-student-card">
          @csrf
          @method('PATCH')
          <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
           <strong class="journal-student-name">{{ $student->name }}</strong>
           <span class="small journal-save-status text-white-50"></span>
          </div>

          <label class="form-label small">Посещение</label>
          <select class="form-select mb-3 journal-touch-control" name="attendance">
           @foreach(['present'=>'Был','absent'=>'Отсутствовал','late'=>'Опоздал','excused'=>'Уваж. причина'] as $k=>$v)
            <option value="{{ $k }}" @selected($entry->attendance===$k)>{{ $v }}</option>
           @endforeach
          </select>

          <div class="row g-2">
           <div class="col-4">
            <label class="form-label small">Оценка</label>
            <input class="form-control journal-touch-control" name="grade" type="number" min="1" max="100" step="0.01" inputmode="decimal" value="{{ $entry->grade }}" placeholder="—">
           </div>
           <div class="col-8">
            <label class="form-label small">Комментарий</label>
            <input class="form-control journal-touch-control" name="comment" value="{{ $entry->comment }}" placeholder="Комментарий">
            <input type="hidden" name="grade_label" value="{{ $entry->grade_label }}">
           </div>
          </div>

          <button class="btn btn-ghost w-100 mt-3 journal-save-btn" type="submit">Сохранить</button>
         </form>
        @endif
       @endforeach
      </div>
     </div>
    </details>
   @endforeach

   @if(method_exists($lessons,'hasPages') && $lessons->hasPages())
    <div class="mt-4 d-flex justify-content-center">
     {{ $lessons->links() }}
    </div>
   @endif
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

 document.querySelectorAll('.journal-entry-form').forEach(form=>{
  const status=form.querySelector('.journal-save-status');
  const button=form.querySelector('.journal-save-btn');
  let saveTimer=null;

  const save=async()=>{
   if(form.dataset.saving==='1'){
    form.dataset.pending='1';
    return;
   }

   form.dataset.saving='1';
   if(status){
    status.textContent='Сохранение...';
    status.className='small ms-2 journal-save-status text-warning';
   }
   if(button)button.disabled=true;

   try{
    const response=await fetch(form.action,{
     method:'POST',
     headers:{
      'Accept':'application/json',
      'X-Requested-With':'XMLHttpRequest'
     },
     body:new FormData(form)
    });

    const payload=await response.json().catch(()=>({}));

    if(!response.ok){
     const errors=payload.errors
      ? Object.values(payload.errors).flat().join(' ')
      : (payload.message || 'Не удалось сохранить запись.');
     throw new Error(errors);
    }

    if(status){
     status.textContent='Сохранено';
     status.className='small ms-2 journal-save-status text-success';
     window.setTimeout(()=>{
      if(status.textContent==='Сохранено')status.textContent='';
     },1800);
    }
   }catch(error){
    if(status){
     status.textContent=error.message || 'Ошибка';
     status.className='small ms-2 journal-save-status text-danger';
    }
   }finally{
    form.dataset.saving='0';
    if(button)button.disabled=false;

    if(form.dataset.pending==='1'){
     form.dataset.pending='0';
     save();
    }
   }
  };

  form.addEventListener('submit',event=>{
   event.preventDefault();
   save();
  });

  form.querySelector('[name="attendance"]')?.addEventListener('change',save);

  form.querySelectorAll('[name="grade"], [name="comment"]').forEach(field=>{
   field.addEventListener('input',()=>{
    window.clearTimeout(saveTimer);
    saveTimer=window.setTimeout(save,700);
   });
   field.addEventListener('change',()=>{
    window.clearTimeout(saveTimer);
    save();
   });
  });
 });
});
</script>
@endsection
