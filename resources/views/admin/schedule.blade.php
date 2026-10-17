@extends('layouts.app')
@section('title','Расписание · Управление')
@section('content')
@php
$groupScheduleData=$groups->mapWithKeys(function($group){
    return [$group->id=>[
        'subjects'=>$group->subjects->map(function($subject){
            return [
                'id'=>$subject->id,
                'title'=>$subject->title,
                'lessons'=>$subject->lessons->map(fn($lesson)=>[
                    'id'=>$lesson->id,
                    'number'=>$lesson->lesson_number,
                    'title'=>$lesson->title,
                ])->values(),
            ];
        })->values(),
    ]];
});
@endphp

<section class="page-top">
 <div class="container">
  <div class="eyebrow">{{ auth()->user()->is_admin ? 'Админ / расписание' : 'Кабинет преподавателя / расписание' }}</div>
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
   <div>
    <h1 class="display-3 fw-bold m-0">{{ auth()->user()->is_admin ? 'Расписание' : 'Моё расписание' }}</h1>
    <p class="text-white-50 mt-2 mb-0">КТП → расписание → проведённый урок → журнал и домашнее задание.</p>
   </div>
   <div class="d-flex gap-2 flex-wrap">
    @if(!auth()->user()->is_admin)<a href="{{ route('admin.teacher-dashboard') }}" class="btn btn-ghost">← Сегодня</a>@endif
    <a href="{{ route('schedule') }}" target="_blank" class="btn btn-ghost">Общее расписание ↗</a>
   </div>
  </div>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())
   <div class="alert alert-danger">
    <strong>Проверьте данные:</strong>
    <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
   </div>
  @endif

  <div class="schedule-admin-grid mb-5">
   <div class="schedule-admin-card">
    <div class="schedule-admin-card-head">
     <div><div class="eyebrow">Один урок</div><h3>Добавить занятие из КТП</h3></div>
     <span class="schedule-mode-badge">урок</span>
    </div>

    <form method="post" action="{{ route('admin.schedule.save') }}" data-linked-schedule-form>
     @csrf
     <div class="row g-3">
      <div class="col-md-6">
       <label class="form-label">Группа</label>
       <select class="form-select" name="study_group_id" required data-schedule-group>
        <option value="">Выберите группу</option>
        @foreach($groups as $g)
         <option value="{{ $g->id }}" @selected(old('study_group_id')==$g->id)>{{ $g->study_year }} год · {{ $g->name }}</option>
        @endforeach
       </select>
      </div>
      <div class="col-md-6">
       <label class="form-label">Предмет</label>
       <select class="form-select" name="subject_id" data-schedule-subject>
        <option value="">Сначала выберите группу</option>
       </select>
      </div>

      <div class="col-12">
       <label class="form-label">Урок КТП</label>
       <select class="form-select" name="subject_lesson_id" data-schedule-plan>
        <option value="">Можно оставить пустым и ввести свободное занятие</option>
       </select>
       <div class="form-text text-white-50">Если выбран урок КТП, его тема и домашнее задание автоматически попадут в журнал после кнопки «Провести урок».</div>
      </div>

      <div class="col-md-7">
       <label class="form-label">Название занятия</label>
       <input class="form-control" name="title" value="{{ old('title') }}" data-schedule-title placeholder="Заполнится из КТП или введите вручную">
      </div>
      <div class="col-md-5">
       <label class="form-label">Студия</label>
       <select class="form-select" name="studio_id">
        <option value="">Общее</option>
        @foreach($studios as $s)<option value="{{ $s->id }}" @selected(old('studio_id')==$s->id)>{{ $s->title }}</option>@endforeach
       </select>
      </div>

      <div class="col-md-4"><label class="form-label">Дата</label><input type="date" class="form-control" name="lesson_date" value="{{ old('lesson_date',now()->toDateString()) }}" required></div>
      <div class="col-md-3"><label class="form-label">Начало</label><input type="time" class="form-control" name="starts_at" value="{{ old('starts_at','13:00') }}" required></div>
      <div class="col-md-3"><label class="form-label">Конец</label><input type="time" class="form-control" name="ends_at" value="{{ old('ends_at','16:30') }}" required></div>
      <div class="col-md-2"><label class="form-label">Аудитория</label><input class="form-control" name="room" value="{{ old('room') }}"></div>

      <div class="col-md-7">
       <label class="form-label">Преподаватель</label>
       @if(auth()->user()->is_admin)
        <input class="form-control" name="teacher_name" value="{{ old('teacher_name') }}">
       @else
        <input class="form-control" value="{{ auth()->user()->name }}" readonly>
        <div class="form-text text-white-50">Занятие автоматически закрепляется за вашим аккаунтом.</div>
       @endif
      </div>
      <div class="col-md-3"><label class="form-label">Цвет</label><input type="color" class="form-control form-control-color w-100" name="color" value="{{ old('color','#8a5cff') }}"></div>
      <div class="col-md-2 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_published" value="1" checked><label class="form-check-label">Показывать</label></div></div>

      <div class="col-12"><label class="form-label">Комментарий / описание</label><textarea class="form-control" rows="3" name="description">{{ old('description') }}</textarea></div>
      <div class="col-12 text-end"><button class="btn btn-neon">Добавить занятие</button></div>
     </div>
    </form>
   </div>

   <div class="schedule-admin-card schedule-master-card">
    <div class="schedule-admin-card-head">
     <div><div class="eyebrow">Мастер расписания</div><h3>Создать повторяющуюся серию</h3></div>
     <span class="schedule-mode-badge accent">серия</span>
    </div>

    <p class="text-white-50 small mb-4">Для регулярной сетки занятий. Конкретные уроки КТП можно затем проводить из списка расписания после их привязки.</p>

    <form method="post" action="{{ route('admin.schedule.generate') }}">@csrf
     <div class="row g-3">
      <div class="col-md-7"><label class="form-label">Название серии</label><input class="form-control" name="title" value="{{ old('series_title') }}" required></div>
      <div class="col-md-5"><label class="form-label">День недели</label><select class="form-select" name="weekday" required>
       @foreach([1=>'Понедельник',2=>'Вторник',3=>'Среда',4=>'Четверг',5=>'Пятница',6=>'Суббота',7=>'Воскресенье'] as $day=>$label)
        <option value="{{ $day }}" @selected((int)old('weekday',6)===$day)>{{ $label }}</option>
       @endforeach
      </select></div>

      <div class="col-md-6"><label class="form-label">Студия</label><select class="form-select" name="studio_id"><option value="">Общее</option>@foreach($studios as $s)<option value="{{ $s->id }}">{{ $s->title }}</option>@endforeach</select></div>
      <div class="col-md-6"><label class="form-label">Группа</label><select class="form-select" name="study_group_id" required><option value="">Выберите группу</option>@foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->study_year }} год · {{ $g->name }}</option>@endforeach</select></div>

      <div class="col-md-6"><label class="form-label">С даты</label><input type="date" class="form-control" name="date_from" required></div>
      <div class="col-md-6"><label class="form-label">По дату</label><input type="date" class="form-control" name="date_to" required></div>

      <div class="col-md-4"><label class="form-label">Начало</label><input type="time" class="form-control" name="starts_at" value="13:00" required></div>
      <div class="col-md-4"><label class="form-label">Конец</label><input type="time" class="form-control" name="ends_at" value="16:30" required></div>
      <div class="col-md-4"><label class="form-label">Аудитория</label><input class="form-control" name="room"></div>

      @if(auth()->user()->is_admin)
       <div class="col-md-7"><label class="form-label">Преподаватель</label><input class="form-control" name="teacher_name"></div>
      @else
       <div class="col-md-7"><label class="form-label">Преподаватель</label><input class="form-control" value="{{ auth()->user()->name }}" readonly></div>
      @endif
      <div class="col-md-3"><label class="form-label">Цвет</label><input type="color" class="form-control form-control-color w-100" name="color" value="#00e5ff"></div>
      <div class="col-md-2 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_published" value="1" checked><label class="form-check-label">Показывать</label></div></div>
      <div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" rows="3" name="description"></textarea></div>
      <div class="col-12 text-end"><button class="btn btn-neon btn-lg">Создать серию</button></div>
     </div>
    </form>
   </div>
  </div>

  <div class="glass-card p-4">
   <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div><div class="eyebrow">Workflow</div><h3 class="mb-0">Созданные занятия</h3></div>
    <span class="badge-soft">{{ $lessons->count() }}</span>
   </div>

   <div class="table-responsive">
    <table class="table admin-table align-middle">
     <thead><tr><th>Дата</th><th>Занятие</th><th>КТП</th><th>Группа</th><th>Статус</th><th></th></tr></thead>
     <tbody>
      @forelse($lessons as $l)
       <tr>
        <td>
         <strong>{{ $l->lesson_date->format('d.m.Y') }}</strong>
         <div class="small text-white-50">{{ substr($l->starts_at,0,5) }}–{{ substr($l->ends_at,0,5) }}</div>
         @if($l->status==='moved' && $l->original_lesson_date)
          <div class="small text-warning">Перенесено с {{ $l->original_lesson_date->format('d.m') }} {{ substr($l->original_starts_at,0,5) }}</div>
         @endif
        </td>
        <td>
         <strong>{{ $l->title }}</strong>
         <div class="small text-white-50">{{ $l->teacher_name }}</div>
         @if($l->room)<div class="small">ауд. {{ $l->room }}</div>@endif
        </td>
        <td>
         @if($l->subject)<div>{{ $l->subject->title }}</div>@endif
         @if($l->planLesson)<div class="small text-white-50">№{{ $l->planLesson->lesson_number }} · {{ $l->planLesson->title }}</div>
         @elseif($l->study_group_id)<span class="small text-warning">КТП не привязан</span>@endif
        </td>
        <td>{{ $l->group->name ?? 'Общее' }}</td>
        <td>
         <span class="badge-soft">{{ $l->status_label }}</span>
         @if($l->status_note)<div class="small text-white-50 mt-1">{{ $l->status_note }}</div>@endif
        </td>
        <td class="text-end">
         <div class="d-flex gap-2 justify-content-end flex-wrap">
          @if($l->journalLesson)
           <a class="btn btn-sm btn-neon" href="{{ route('admin.journal',['group_id'=>$l->study_group_id,'subject_id'=>$l->subject_id]) }}">Журнал</a>
          @elseif($l->status!=='cancelled' && $l->subject_lesson_id)
           <form method="post" action="{{ route('admin.schedule.conduct',$l) }}">@csrf<button class="btn btn-sm btn-neon">Провести урок</button></form>
          @endif

          @if($l->status!=='cancelled' && !$l->journal_lesson_id)
           <form method="post" action="{{ route('admin.schedule.status',$l) }}">@csrf @method('PATCH')
            <input type="hidden" name="status" value="cancelled">
            <button class="btn btn-sm btn-outline-warning">Отменить</button>
           </form>
          @elseif($l->status==='cancelled')
           <form method="post" action="{{ route('admin.schedule.status',$l) }}">@csrf @method('PATCH')
            <input type="hidden" name="status" value="planned">
            <button class="btn btn-sm btn-ghost">Вернуть</button>
           </form>
          @endif

          @if(!$l->journal_lesson_id)
           <details class="text-start">
            <summary class="btn btn-sm btn-ghost">Перенести</summary>
            <form class="mt-2 p-2 glass-card" style="min-width:230px" method="post" action="{{ route('admin.schedule.status',$l) }}">@csrf @method('PATCH')
             <input type="hidden" name="status" value="moved">
             <input type="date" class="form-control form-control-sm mb-2" name="lesson_date" value="{{ $l->lesson_date->toDateString() }}" required>
             <div class="d-flex gap-2 mb-2">
              <input type="time" class="form-control form-control-sm" name="starts_at" value="{{ substr($l->starts_at,0,5) }}" required>
              <input type="time" class="form-control form-control-sm" name="ends_at" value="{{ substr($l->ends_at,0,5) }}" required>
             </div>
             <input class="form-control form-control-sm mb-2" name="status_note" placeholder="Причина переноса">
             <button class="btn btn-sm btn-neon w-100">Сохранить перенос</button>
            </form>
           </details>
          @endif

          <form method="post" action="{{ route('admin.schedule.delete',$l) }}" onsubmit="return confirm('Удалить занятие?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">×</button></form>
         </div>
        </td>
       </tr>
      @empty
       <tr><td colspan="6" class="text-white-50">Пока нет занятий.</td></tr>
      @endforelse
     </tbody>
    </table>
   </div>
  </div>
 </div>
</section>

<script>
document.addEventListener('DOMContentLoaded',()=>{
 const data=@json($groupScheduleData);
 document.querySelectorAll('[data-linked-schedule-form]').forEach(form=>{
  const group=form.querySelector('[data-schedule-group]');
  const subject=form.querySelector('[data-schedule-subject]');
  const plan=form.querySelector('[data-schedule-plan]');
  const title=form.querySelector('[data-schedule-title]');
  if(!group||!subject||!plan)return;

  const oldSubject='{{ old('subject_id') }}';
  const oldPlan='{{ old('subject_lesson_id') }}';

  function syncSubjects(){
   const subjects=data[group.value]?.subjects||[];
   subject.innerHTML='<option value="">Без предмета</option>';
   subjects.forEach(item=>{
    const option=document.createElement('option');
    option.value=item.id;
    option.textContent=item.title;
    if(String(item.id)===String(oldSubject)) option.selected=true;
    subject.appendChild(option);
   });
   syncLessons();
  }

  function syncLessons(){
   const subjects=data[group.value]?.subjects||[];
   const selected=subjects.find(item=>String(item.id)===String(subject.value));
   const lessons=selected?.lessons||[];
   plan.innerHTML='<option value="">Свободное занятие</option>';
   lessons.forEach(item=>{
    const option=document.createElement('option');
    option.value=item.id;
    option.textContent='№'+item.number+' · '+item.title;
    option.dataset.title=item.title;
    if(String(item.id)===String(oldPlan)) option.selected=true;
    plan.appendChild(option);
   });
   syncTitle();
  }

  function syncTitle(){
   const option=plan.options[plan.selectedIndex];
   if(option?.value && option.dataset.title) title.value=option.dataset.title;
  }

  group.addEventListener('change',syncSubjects);
  subject.addEventListener('change',syncLessons);
  plan.addEventListener('change',syncTitle);
  syncSubjects();
 });
});
</script>
@endsection
