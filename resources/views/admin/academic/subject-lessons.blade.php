@extends('layouts.app')
@section('title','Календарно-тематический план · '.$subject->title)
@section('content')
<section class="page-top">
 <div class="container">
  <a href="{{ route('admin.subjects') }}" class="backlink">← Предметы</a>
  <div class="eyebrow mt-4">Предмет / календарно-тематический план</div>
  <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
   <div>
    <h1 class="display-3 fw-bold mb-1">{{ $subject->title }}</h1>
    <p class="text-white-50 mb-0">{{ $subject->studio->title ?? 'Общий предмет' }}</p>
   </div>
   <a class="btn btn-ghost" target="_blank" href="{{ route('admin.ktp.print',$subject) }}">Печать КТП</a>
  </div>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  @if($errors->any())
   <div class="alert alert-danger">
    <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
   </div>
  @endif

  <div class="glass-card p-4 mb-4">
   <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
    <div>
     <div class="eyebrow">Массовое заполнение</div>
     <h3 class="mt-2 mb-1">Импорт КТП из JSON</h3>
     <div class="text-white-50">Можно загрузить файл или вставить JSON. Совпадение определяется по номеру урока.</div>
    </div>
    <span class="badge-soft">JSON</span>
   </div>

   <form method="post" enctype="multipart/form-data" action="{{ route('admin.subjects.lessons.import-json',$subject) }}">
    @csrf
    <div class="row g-3">
     <div class="col-lg-5">
      <label class="form-label">JSON-файл</label>
      <input type="file" class="form-control" name="json_file" accept=".json,application/json,text/json">
      <div class="form-text text-white-50">До 10 МБ. Если заполнено поле ниже, будет использован текст.</div>
     </div>
     <div class="col-lg-4">
      <label class="form-label">Если урок с таким № уже существует</label>
      <select class="form-select" name="existing_action">
       <option value="update" @selected(old('existing_action','update')==='update')>Обновить существующий</option>
       <option value="skip" @selected(old('existing_action')==='skip')>Пропустить</option>
      </select>
     </div>
     <div class="col-lg-3 d-flex align-items-end">
      <button class="btn btn-neon w-100">Импортировать КТП</button>
     </div>

     <div class="col-12">
      <label class="form-label">Или вставьте JSON</label>
      <textarea class="form-control font-monospace" rows="7" name="json_text" placeholder='{"lessons":[{"lesson_number":1,"title":"Введение в 3D","content":"..."}]}'>{{ old('json_text') }}</textarea>
     </div>
    </div>
   </form>

   <details class="mt-3">
    <summary class="text-white-50" style="cursor:pointer">Формат JSON и пример</summary>
    <pre class="mt-3 p-3 rounded-3 mb-0" style="white-space:pre-wrap;background:rgba(0,0,0,.25);font-size:.86rem"><code>{
  "version": 1,
  "lessons": [
    {
      "lesson_number": 1,
      "title": "Введение в 3D-графику",
      "content": "Теория и практическая работа...",
      "homework_description": "Создать композицию из примитивов",
      "homework_due_days": 7,
      "homework_max_score": 5,
      "sort_order": 1,
      "is_published": true,
      "media": [
        {
          "type": "link",
          "title": "Дополнительный материал",
          "url": "https://example.org/material",
          "caption": "Открыть после занятия",
          "sort_order": 0,
          "is_visible": true
        }
      ]
    }
  ]
}</code></pre>
    <div class="small text-white-50 mt-2">
     Поля <code>lesson_number</code> и <code>title</code> обязательны.
     Материалы из JSON добавляются по URL; локальные файлы по-прежнему загружаются в карточке урока.
    </div>
   </details>
  </div>

  <div class="glass-card p-4 mb-5">
   <div class="eyebrow">Новый пункт плана</div>
   <h3 class="mt-2 mb-4">Добавить урок</h3>
   <form method="post" action="{{ route('admin.subjects.lessons.save',$subject) }}">
    @csrf
    <div class="row g-3">
     <div class="col-md-2">
      <label class="form-label">№ урока</label>
      <input type="number" min="1" class="form-control" name="lesson_number" value="{{ old('lesson_number',$subject->lessons->count()+1) }}" required>
     </div>
     <div class="col-md-8">
      <label class="form-label">Название урока</label>
      <input class="form-control" name="title" value="{{ old('title') }}" required placeholder="Например: Основы композиции">
     </div>
     <div class="col-md-2">
      <label class="form-label">Порядок</label>
      <input type="number" min="0" class="form-control" name="sort_order" value="{{ old('sort_order',$subject->lessons->count()+1) }}">
     </div>

     <div class="col-12">
      <label class="form-label">Содержание урока</label>
      <textarea class="form-control" rows="8" name="content" placeholder="Теория, последовательность работы, пояснения, инструкции...">{{ old('content') }}</textarea>
     </div>

     <div class="col-lg-8">
      <label class="form-label">Домашнее задание</label>
      <textarea class="form-control" rows="5" name="homework_description" placeholder="Что ученик должен выполнить дома...">{{ old('homework_description') }}</textarea>
     </div>
     <div class="col-md-2">
      <label class="form-label">Срок, дней</label>
      <input type="number" min="0" max="365" class="form-control" name="homework_due_days" value="{{ old('homework_due_days',7) }}">
     </div>
     <div class="col-md-2">
      <label class="form-label">Макс. балл</label>
      <input type="number" min="1" max="100" class="form-control" name="homework_max_score" value="{{ old('homework_max_score',5) }}" required>
     </div>

     <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-3">
      <div class="form-check">
       <input class="form-check-input" type="checkbox" name="is_published" value="1" id="newLessonPublished" checked>
       <label class="form-check-label" for="newLessonPublished">Доступен преподавателю для выбора в журнале</label>
      </div>
      <button class="btn btn-neon">Добавить урок в план</button>
     </div>
    </div>
   </form>
  </div>

  <div class="section-head">
   <div><div class="eyebrow">Lesson plan</div><h2>Уроки предмета</h2></div>
   <span class="badge-soft">{{ $subject->lessons->count() }}</span>
  </div>

  <div class="d-grid gap-4">
   @forelse($subject->lessons as $lesson)
    <article class="glass-card p-4">
     <form method="post" action="{{ route('admin.subjects.lessons.save',[$subject,$lesson]) }}">
      @csrf
      <div class="row g-3">
       <div class="col-md-2">
        <label class="form-label">№ урока</label>
        <input type="number" min="1" class="form-control" name="lesson_number" value="{{ $lesson->lesson_number }}" required>
       </div>
       <div class="col-md-8">
        <label class="form-label">Название</label>
        <input class="form-control" name="title" value="{{ $lesson->title }}" required>
       </div>
       <div class="col-md-2">
        <label class="form-label">Порядок</label>
        <input type="number" min="0" class="form-control" name="sort_order" value="{{ $lesson->sort_order }}">
       </div>

       <div class="col-12">
        <label class="form-label">Содержание урока</label>
        <textarea class="form-control" rows="7" name="content">{{ $lesson->content }}</textarea>
       </div>

       <div class="col-lg-8">
        <label class="form-label">Домашнее задание</label>
        <textarea class="form-control" rows="4" name="homework_description">{{ $lesson->homework_description }}</textarea>
       </div>
       <div class="col-md-2">
        <label class="form-label">Срок, дней</label>
        <input type="number" min="0" max="365" class="form-control" name="homework_due_days" value="{{ $lesson->homework_due_days }}">
       </div>
       <div class="col-md-2">
        <label class="form-label">Макс. балл</label>
        <input type="number" min="1" max="100" class="form-control" name="homework_max_score" value="{{ $lesson->homework_max_score }}" required>
       </div>

       <div class="col-12 d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div class="form-check">
         <input class="form-check-input" type="checkbox" name="is_published" value="1" id="published{{ $lesson->id }}" @checked($lesson->is_published)>
         <label class="form-check-label" for="published{{ $lesson->id }}">Доступен в журнале</label>
        </div>
        <div class="d-flex gap-2">
         <button class="btn btn-ghost">Сохранить урок</button>
     </form>
         <form method="post" action="{{ route('admin.subjects.lessons.delete',[$subject,$lesson]) }}" onsubmit="return confirm('Удалить урок из плана?')">
          @csrf @method('DELETE')
          <button class="btn btn-outline-danger">Удалить</button>
         </form>
        </div>
       </div>
      </div>

     <div class="mt-4 pt-4 border-top border-secondary-subtle">
      <div class="d-flex justify-content-between align-items-center mb-3">
       <div>
        <div class="eyebrow">Материалы урока</div>
        <h4 class="mb-0">Файлы и ссылки</h4>
       </div>
       <span class="badge-soft">{{ $lesson->media->count() }}</span>
      </div>

      <form method="post" enctype="multipart/form-data" action="{{ route('admin.subjects.lessons.media.add',[$subject,$lesson]) }}" class="row g-2 align-items-end mb-3">
       @csrf
       <div class="col-md-2">
        <label class="form-label">Тип</label>
        <select class="form-select" name="type">
         <option value="file">Файл</option>
         <option value="link">Ссылка</option>
         <option value="photo">Фото</option>
         <option value="video">Видео</option>
         <option value="audio">Аудио</option>
         <option value="model">3D</option>
         <option value="panorama">360°</option>
        </select>
       </div>
       <div class="col-md-3"><label class="form-label">Название</label><input class="form-control" name="title"></div>
       <div class="col-md-3"><label class="form-label">URL</label><input class="form-control" name="url" placeholder="https://..."></div>
       <div class="col-md-3"><label class="form-label">Или файл</label><input type="file" class="form-control" name="file"></div>
       <div class="col-md-1 d-grid"><button class="btn btn-neon">+</button></div>
       <div class="col-md-10"><label class="form-label">Комментарий</label><input class="form-control" name="caption"></div>
       <div class="col-md-2"><label class="form-label">Порядок</label><input type="number" min="0" class="form-control" name="sort_order" value="0"></div>
       <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_visible" value="1" checked> <span class="form-check-label">Показывать ученикам</span></label></div>
      </form>

      <div class="d-grid gap-2">
       @foreach($lesson->media as $media)
        <div class="d-flex justify-content-between align-items-center gap-3 p-3 rounded-3" style="background:rgba(255,255,255,.025)">
         <div>
          <div class="small text-white-50">{{ ['file'=>'Файл','link'=>'Ссылка','photo'=>'Фото','video'=>'Видео','audio'=>'Аудио','model'=>'3D','panorama'=>'360°'][$media->type] ?? $media->type }}</div>
          <strong>{{ $media->title ?: ($media->file_name ?: $media->url) }}</strong>
          @if($media->caption)<div class="small text-white-50">{{ $media->caption }}</div>@endif
         </div>
         <div class="d-flex gap-2">
          <a class="btn btn-sm btn-ghost" href="{{ $media->display_url }}" target="_blank" rel="noopener">Открыть</a>
          <form method="post" action="{{ route('admin.subjects.lessons.media.delete',[$subject,$lesson,$media]) }}">
           @csrf @method('DELETE')
           <button class="btn btn-sm btn-outline-danger">Удалить</button>
          </form>
         </div>
        </div>
       @endforeach
      </div>
     </div>
    </article>
   @empty
    <div class="glass-card p-4 text-white-50">План пока пуст. Добавьте первый урок выше.</div>
   @endforelse
  </div>
 </div>
</section>
@endsection
