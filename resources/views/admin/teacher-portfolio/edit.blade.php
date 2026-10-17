@extends('layouts.app')
@section('title','Портфолио преподавателя · '.$teacher->name)
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Кабинет преподавателя / портфолио</div><div class="d-flex justify-content-between align-items-end gap-3 flex-wrap"><div><h1 class="display-3 fw-bold m-0">{{ $teacher->name }}</h1><p class="text-white-50 mt-2 mb-0">Публичный профессиональный профиль и проекты.</p></div>@if($profile->is_public)<a class="btn btn-ghost" target="_blank" href="{{ route('teachers.show',$profile) }}">Открыть публичную страницу ↗</a>@endif</div></div></section>
<section class="pb-5"><div class="container">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<form class="form-shell mb-5" method="post" enctype="multipart/form-data" action="{{ route('admin.teacher-portfolio.save',auth()->user()->is_admin ? $teacher : null) }}">@csrf
<div class="row g-3">
 <div class="col-md-6"><label class="form-label">Заголовок / должность</label><input class="form-control" name="headline" value="{{ old('headline',$profile->headline) }}"></div>
 <div class="col-md-3"><label class="form-label">Студия</label><select class="form-select" name="studio_id"><option value="">Вся школа</option>@foreach($studios as $s)<option value="{{ $s->id }}" @selected((int)$profile->studio_id===$s->id)>{{ $s->title }}</option>@endforeach</select></div>
 <div class="col-md-3"><label class="form-label">URL</label><input class="form-control" name="slug" value="{{ old('slug',$profile->slug) }}"></div>
 <div class="col-md-6"><label class="form-label">Фото</label><input type="file" class="form-control" name="avatar" accept="image/*"></div>
 <div class="col-md-6 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_public" value="1" @checked($profile->is_public)><label class="form-check-label">Публичное портфолио</label></div></div>
 <div class="col-12"><label class="form-label">О себе</label><textarea class="form-control" rows="4" name="bio">{{ old('bio',$profile->bio) }}</textarea></div>
 @foreach(['education'=>'Образование','experience'=>'Опыт работы','competencies'=>'Компетенции и навыки','courses'=>'Курсы и повышение квалификации','publications'=>'Публикации','professional_achievements'=>'Профессиональные достижения'] as $field=>$label)
 <div class="col-md-6"><label class="form-label">{{ $label }}</label><textarea class="form-control" rows="4" name="{{ $field }}">{{ old($field,$profile->$field) }}</textarea></div>
 @endforeach
 <div class="col-md-4"><label class="form-label">Сайт</label><input class="form-control" name="website_url" value="{{ $profile->website_url }}"></div>
 <div class="col-md-4"><label class="form-label">VK</label><input class="form-control" name="vk_url" value="{{ $profile->vk_url }}"></div>
 <div class="col-md-4"><label class="form-label">Telegram</label><input class="form-control" name="telegram_url" value="{{ $profile->telegram_url }}"></div>
 <div class="col-12 text-end"><button class="btn btn-neon">Сохранить профиль</button></div>
</div></form>

<div class="section-head"><div><div class="eyebrow">Projects</div><h2>Мои проекты</h2></div></div>
<form class="glass-card p-4 mb-4" method="post" enctype="multipart/form-data" action="{{ route('admin.teacher-portfolio.projects.save',$profile) }}">@csrf
<div class="row g-3">
 <div class="col-md-7"><label class="form-label">Название</label><input class="form-control" name="title" required></div>
 <div class="col-md-5"><label class="form-label">Тип</label><select class="form-select" name="type"><option value="project">Проект</option><option value="publication">Публикация</option><option value="course">Курс</option><option value="research">Исследование</option><option value="video">Видео</option><option value="other">Другое</option></select></div>
 <div class="col-md-4"><label class="form-label">Студия</label><select class="form-select" name="studio_id"><option value="">Не выбрана</option>@foreach($studios as $s)<option value="{{ $s->id }}">{{ $s->title }}</option>@endforeach</select></div>
 <div class="col-md-4"><label class="form-label">Дата</label><input type="date" class="form-control" name="completed_at"></div>
 <div class="col-md-4"><label class="form-label">Обложка</label><input type="file" class="form-control" name="cover" accept="image/*"></div>
 <div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" rows="3" name="description"></textarea></div>
 <div class="col-md-6"><label class="form-label">Ссылка на проект</label><input class="form-control" name="project_url"></div>
 <div class="col-md-6"><label class="form-label">Видео</label><input class="form-control" name="video_url"></div>
 <div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_public" value="1" checked><label class="form-check-label">Публично</label></div></div>
 <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" value="1"><label class="form-check-label">Избранное</label></div></div>
 <div class="col-md-3 text-end"><button class="btn btn-neon">Добавить проект</button></div>
</div></form>
<div class="row g-4">
@forelse($profile->portfolio as $item)<div class="col-md-6 col-xl-4"><div class="project-card h-100">
 <div class="project-media">@if($item->cover_url)<img src="{{ $item->cover_url }}" alt="{{ $item->title }}">@else<div class="project-noise"></div>@endif</div>
 <div class="pt-3"><h4>{{ $item->title }}</h4><div class="small text-white-50">{{ $item->type }} · {{ optional($item->completed_at)->format('d.m.Y') }} · {{ $item->media->count() }} медиа</div><p class="text-white-50">{{ $item->description }}</p>
 <div class="d-flex gap-2 flex-wrap">
  <a class="btn btn-sm btn-neon" href="{{ route('admin.teacher-portfolio.media',[$profile,$item]) }}">Медиагалерея</a>
  <form method="post" action="{{ route('admin.teacher-portfolio.projects.delete',[$profile,$item]) }}" onsubmit="return confirm('Удалить проект?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form>
 </div></div>
</div></div>@empty<div class="text-white-50">Проектов пока нет.</div>@endforelse
</div>
</div></section>
@endsection
