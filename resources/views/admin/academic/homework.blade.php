@extends('layouts.app')
@section('title','Домашние задания · '.(auth()->user()->is_admin ? 'Админ' : 'Преподаватель'))
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">{{ auth()->user()->is_admin ? 'Админ' : 'Кабинет преподавателя' }} / домашние задания</div>
  <h1 class="display-3 fw-bold">Домашние задания</h1>
  @unless(auth()->user()->is_admin)
   <p class="text-white-50 mb-0">Вы видите только свои группы, предметы и выданные задания.</p>
  @endunless
 </div>
</section>

<section class="pb-5">
 <div class="container">
  <div class="d-flex gap-2 flex-wrap mb-4">
   <a class="btn btn-ghost" href="{{ route('admin.journal') }}">Журнал</a>
   <a class="btn btn-ghost" href="{{ route('admin.attendance') }}">Посещаемость</a>
   <a class="btn btn-neon" href="{{ route('admin.homework') }}">Домашние задания</a>
   <a class="btn btn-ghost" href="{{ route('admin.schedule') }}">Расписание</a>
  </div>

  @if($groups->isEmpty())
   <div class="alert alert-warning">
    За вами пока не закреплены учебные группы и предметы.
   </div>
  @else
   <form class="form-shell mb-5" method="post" enctype="multipart/form-data" action="{{ route('admin.homework.save') }}" id="homeworkForm">
    @csrf
    <div class="row g-3">
     <div class="col-md-6">
      <label class="form-label">Группа</label>
      <select class="form-select" name="study_group_id" id="homeworkGroup" required>
       <option value="">Выберите группу</option>
       @foreach($groups as $g)
        @if($g->subjects->isNotEmpty())
         <option value="{{ $g->id }}" @selected(old('study_group_id')==$g->id)>{{ $g->name }}</option>
        @endif
       @endforeach
      </select>
     </div>

     <div class="col-md-6">
      <label class="form-label">Предмет</label>
      <select class="form-select" name="subject_id" id="homeworkSubject" required disabled>
       <option value="">Сначала выберите группу</option>
      </select>
      <div class="form-text text-white-50">Показываются только предметы, закреплённые за вами в выбранной группе.</div>
     </div>

     <div class="col-12"><label class="form-label">Заголовок</label><input class="form-control" name="title" value="{{ old('title') }}" required></div>
     <div class="col-12"><label class="form-label">Задание</label><textarea class="form-control" rows="6" name="description">{{ old('description') }}</textarea></div>
     <div class="col-md-6"><label class="form-label">Срок</label><input type="datetime-local" class="form-control" name="due_at" value="{{ old('due_at') }}"></div>
     <div class="col-md-3"><label class="form-label">Макс. балл</label><input type="number" class="form-control" name="max_score" value="{{ old('max_score',5) }}" min="1" max="100" required></div>
     <div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_published" value="1" @checked(old('is_published',true))><label class="form-check-label">Опубликовать</label></div></div>
     <div class="col-md-6"><label class="form-label">Файл</label><input type="file" class="form-control" name="attachment"></div>
     <div class="col-md-6"><label class="form-label">Ссылка</label><input class="form-control" name="external_url" value="{{ old('external_url') }}"></div>
     <div class="col-12 text-end"><button class="btn btn-neon">Создать задание</button></div>
    </div>
   </form>
  @endif

  <div class="section-head"><div><div class="eyebrow">Assignments</div><h2>Мои задания</h2></div></div>
  <div class="row g-3">
   @forelse($assignments as $a)
    <div class="col-md-6">
     <article class="glass-card p-4 h-100">
      <div class="small text-white-50">{{ $a->group->name ?? '' }} · {{ $a->subject->title ?? '' }}</div>
      <h3>{{ $a->title }}</h3>
      <div class="text-white-50 mb-3">
       @if($a->due_at)до {{ $a->due_at->format('d.m.Y H:i') }} · @endif
       {{ $a->is_published ? 'Опубликовано' : 'Черновик' }}
      </div>
      <div class="d-flex justify-content-between align-items-center">
       <span>{{ $a->submissions->count() }} сдач</span>
       <a class="btn btn-ghost btn-sm" href="{{ route('admin.homework.submissions',$a) }}">Проверить</a>
      </div>
     </article>
    </div>
   @empty
    <div class="text-white-50">Вы ещё не выдавали домашних заданий.</div>
   @endforelse
  </div>
 </div>
</section>

<script>
document.addEventListener('DOMContentLoaded',()=>{
 const group=document.getElementById('homeworkGroup');
 const subject=document.getElementById('homeworkSubject');
 if(!group||!subject)return;

 const options=@json($subjectOptions);
 const oldSubject='{{ old('subject_id') }}';

 const refresh=()=>{
  const items=options[group.value] || [];
  subject.innerHTML='<option value="">Выберите предмет</option>';
  subject.disabled=!group.value || !items.length;

  items.forEach(item=>{
   const option=document.createElement('option');
   option.value=String(item.id);
   option.textContent=item.title;
   if(String(item.id)===String(oldSubject))option.selected=true;
   subject.appendChild(option);
  });
 };

 group.addEventListener('change',refresh);
 refresh();
});
</script>
@endsection
