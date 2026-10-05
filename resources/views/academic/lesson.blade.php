@extends('layouts.app')
@section('title','Практическая работа · '.$lesson->topic)
@section('content')
<section class="page-top">
 <div class="container">
  <a href="{{ route('academic.dashboard') }}" class="backlink">← Моя учёба</a>
  <div class="eyebrow mt-4">{{ $lesson->subject->title ?? 'Урок' }} · {{ $lesson->lesson_date->format('d.m.Y') }}</div>
  <h1 class="display-3 fw-bold">{{ $lesson->topic }}</h1>
  <div class="d-flex gap-3 flex-wrap text-white-50">
   <span>{{ $lesson->group->name ?? '' }}</span>
   @if($lesson->teacher)<span>· {{ $lesson->teacher->name }}</span>@endif
  </div>
  @if($lesson->notes)<p class="lead text-white-50 col-lg-9 mt-3">{{ $lesson->notes }}</p>@endif
 </div>
</section>

<section class="pb-5">
 <div class="container">
  @if($errors->any())
   <div class="alert alert-danger">
    <strong>Не удалось сохранить работу:</strong>
    <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
   </div>
  @endif

  @if($work)
   <div class="glass-card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
     <div>
      <div class="eyebrow">Уже отправлено</div>
      <h3 class="mt-2 mb-1">{{ $work->title }}</h3>
      <div class="text-white-50">
       {{ ['drawing'=>'Рисунок','stl'=>'STL / 3D','text'=>'Текст'][$work->practical_kind] ?? 'Практическая работа' }}
       · {{ optional($work->updated_at)->format('d.m.Y H:i') }}
      </div>
     </div>
     <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-neon" href="{{ route('projects.show',$work) }}" target="_blank">Открыть работу ↗</a>
      <a class="btn btn-ghost" href="{{ route('portfolio.show',$profile) }}" target="_blank">Все мои работы ↗</a>
     </div>
    </div>
   </div>
  @endif

  <div class="row g-4">
   <div class="col-lg-8">
    <form class="form-shell" method="post" enctype="multipart/form-data" action="{{ route('academic.lesson.practical',$lesson) }}" data-practical-form>
     @csrf
     <div class="eyebrow">Практическая работа</div>
     <h3 class="mt-2 mb-4">{{ $work ? 'Обновить работу' : 'Прикрепить работу к уроку' }}</h3>

     <div class="row g-3">
      <div class="col-md-5">
       <label class="form-label">Тип работы</label>
       <select class="form-select" name="kind" data-practical-kind required>
        <option value="drawing" @selected(old('kind',$work->practical_kind ?? 'drawing')==='drawing')>Рисунок / изображение</option>
        <option value="stl" @selected(old('kind',$work->practical_kind ?? '')==='stl')>STL / 3D-модель</option>
        <option value="text" @selected(old('kind',$work->practical_kind ?? '')==='text')>Текстовая работа</option>
       </select>
      </div>
      <div class="col-md-7">
       <label class="form-label">Название</label>
       <input class="form-control" name="title" value="{{ old('title',$work->title ?? $lesson->topic) }}">
      </div>

      <div class="col-12" data-practical-panel="drawing">
       <label class="form-label">Изображение</label>
       <input type="file" class="form-control" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
       <div class="form-text text-white-50">JPG, PNG, WebP или GIF. После загрузки изображение автоматически преобразуется в JPEG и уменьшается максимум до 1920×1080.</div>
      </div>

      <div class="col-12" data-practical-panel="stl">
       <label class="form-label">STL-файл</label>
       <input type="file" class="form-control" name="stl" accept=".stl,model/stl">
       <div class="form-text text-white-50">До 50 МБ. Модель будет доступна в интерактивном 3D-просмотрщике.</div>
      </div>

      <div class="col-12" data-practical-panel="text">
       <label class="form-label">Текст работы</label>
       <textarea class="form-control" rows="12" name="text_content" placeholder="Введите текст практической работы...">{{ old('text_content',$work && $work->practical_kind==='text' ? $work->description : '') }}</textarea>
      </div>

      <div class="col-12" data-practical-note>
       <label class="form-label">Комментарий к работе</label>
       <textarea class="form-control" rows="4" name="description" placeholder="Что сделано, какие инструменты использовались...">{{ old('description',$work && $work->practical_kind!=='text' ? $work->description : '') }}</textarea>
      </div>

      <div class="col-12 d-flex justify-content-between align-items-center gap-3 flex-wrap mt-4">
       <div class="small text-white-50">После сохранения работа появится в портфолио и в разделе работ студии.</div>
       <button class="btn btn-neon btn-lg">{{ $work ? 'Обновить работу' : 'Сохранить работу' }}</button>
      </div>
     </div>
    </form>
   </div>

   <div class="col-lg-4">
    <div class="glass-card p-4">
     <div class="eyebrow">Как это работает</div>
     <h4 class="mt-2">Одна работа на один урок</h4>
     <p class="text-white-50">Повторная отправка обновит уже прикреплённую работу, а не создаст дубль.</p>
     <hr class="opacity-10">
     <div class="small text-white-50">Работа автоматически связывается с вашей группой, предметом и студией.</div>
    </div>
   </div>
  </div>
 </div>
</section>

<script>
document.addEventListener('DOMContentLoaded',()=>{
 const form=document.querySelector('[data-practical-form]');
 const select=form?.querySelector('[data-practical-kind]');
 if(!form||!select)return;

 const refresh=()=>{
  const kind=select.value;
  form.querySelectorAll('[data-practical-panel]').forEach(panel=>{
   panel.hidden=panel.dataset.practicalPanel!==kind;
  });
  const note=form.querySelector('[data-practical-note]');
  if(note)note.hidden=kind==='text';
 };
 select.addEventListener('change',refresh);
 refresh();
});
</script>
@endsection
