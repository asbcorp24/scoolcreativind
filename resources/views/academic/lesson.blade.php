@extends('layouts.app')
@section('title',$lesson->topic.' · Урок')
@section('content')

<section class="page-top">
 <div class="container">
  <a href="{{ route('academic.dashboard') }}" class="backlink">← Моя учёба</a>
  <div class="eyebrow mt-4">
   {{ $lesson->subject->title ?? 'Урок' }} · {{ $lesson->lesson_date->format('d.m.Y') }}
   @if($lesson->planLesson) · Урок {{ $lesson->planLesson->lesson_number }} @endif
  </div>
  <h1 class="display-3 fw-bold">{{ $lesson->topic }}</h1>
  <div class="d-flex gap-3 flex-wrap text-white-50">
   <span>{{ $lesson->group->name ?? '' }}</span>
   @if($lesson->teacher)<span>· {{ $lesson->teacher->name }}</span>@endif
  </div>
  @if($lesson->notes)
   <p class="lead text-white-50 col-lg-9 mt-3">{{ $lesson->notes }}</p>
  @endif
 </div>
</section>

<section class="pb-5">
 <div class="container">
  @if($errors->any())
   <div class="alert alert-danger">
    <strong>Проверьте данные:</strong>
    <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
   </div>
  @endif

  @if($lesson->planLesson)
   <div class="row g-4 mb-5">
    <div class="col-lg-8">
     <article class="glass-card p-4 p-lg-5 h-100">
      <div class="eyebrow">Содержание урока</div>
      <h2 class="mt-2">Урок {{ $lesson->planLesson->lesson_number }} · {{ $lesson->planLesson->title }}</h2>
      @if($lesson->planLesson->content)
       <div class="studio-description mt-4">{!! nl2br(e($lesson->planLesson->content)) !!}</div>
      @else
       <div class="text-white-50 mt-4">Описание урока пока не добавлено.</div>
      @endif
     </article>
    </div>

    <div class="col-lg-4">
     <article class="glass-card p-4 h-100">
      <div class="eyebrow">Домашняя работа</div>
      @if($lesson->homeworkAssignment)
       <h3 class="mt-2">{{ $lesson->homeworkAssignment->title }}</h3>
       <p class="text-white-50">{{ $lesson->homeworkAssignment->description }}</p>
       @if($lesson->homeworkAssignment->due_at)
        <div class="small text-white-50 mb-3">Срок: {{ $lesson->homeworkAssignment->due_at->format('d.m.Y H:i') }}</div>
       @endif
       <a class="btn btn-neon" href="{{ route('academic.homework',$lesson->homeworkAssignment) }}">Выполнить домашнее задание</a>
      @elseif($lesson->planLesson->homework_description)
       <h3 class="mt-2">Домашнее задание</h3>
       <p class="text-white-50">{{ $lesson->planLesson->homework_description }}</p>
      @else
       <h3 class="mt-2">Домашнего задания нет</h3>
       <p class="text-white-50 mb-0">Для этого урока домашняя работа не предусмотрена.</p>
      @endif
     </article>
    </div>
   </div>

   @if($lesson->planLesson->media->count())
    <div class="section-head">
     <div><div class="eyebrow">Материалы</div><h2>Файлы и ссылки к уроку</h2></div>
    </div>
    <div class="row g-3 mb-5">
     @foreach($lesson->planLesson->media as $media)
      <div class="col-md-6 col-xl-4">
       <a href="{{ $media->display_url }}" target="_blank" rel="noopener" class="glass-card p-4 h-100 d-flex justify-content-between align-items-center gap-3 text-decoration-none">
        <div>
         <div class="small text-white-50">{{ ['file'=>'Файл','link'=>'Ссылка','photo'=>'Фото','video'=>'Видео','audio'=>'Аудио','model'=>'3D','panorama'=>'360°'][$media->type] ?? $media->type }}</div>
         <strong>{{ $media->title ?: ($media->file_name ?: 'Материал урока') }}</strong>
         @if($media->caption)<div class="small text-white-50 mt-1">{{ $media->caption }}</div>@endif
         @if($media->human_file_size)<div class="small text-white-50 mt-1">{{ $media->human_file_size }}</div>@endif
        </div>
        <span>↗</span>
       </a>
      </div>
     @endforeach
    </div>
   @endif
  @endif

  @if($work)
   <div class="glass-card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
     <div>
      <div class="eyebrow">Практическая работа отправлена</div>
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
     <h3 class="mt-2 mb-4">{{ $work ? 'Обновить работу' : 'Прикрепить практическую работу к уроку' }}</h3>

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
       <div class="form-text text-white-50">Изображение автоматически преобразуется в JPEG и уменьшается максимум до 1920×1080.</div>
      </div>

      <div class="col-12" data-practical-panel="stl">
       <label class="form-label">STL-файл</label>
       <input type="file" class="form-control" name="stl" accept=".stl,model/stl">
       <div class="form-text text-white-50">До 50 МБ. STL откроется в интерактивном 3D-просмотрщике.</div>
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
       <div class="small text-white-50">Практическая работа автоматически попадёт в портфолио и в работы студии.</div>
       <button class="btn btn-neon btn-lg">{{ $work ? 'Обновить работу' : 'Сохранить работу' }}</button>
      </div>
     </div>
    </form>
   </div>

   <div class="col-lg-4">
    <div class="glass-card p-4">
     <div class="eyebrow">Урок</div>
     <h4 class="mt-2">Все действия в одном месте</h4>
     <p class="text-white-50">Здесь ученик читает содержание урока, скачивает материалы, выполняет домашнее задание и сдаёт практическую работу.</p>
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
