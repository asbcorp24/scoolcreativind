@extends('layouts.app')
@section('title','Портфолио · '.$profile->user->name)
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Админ / портфолио</div><h1 class="display-3 fw-bold">{{ $profile->user->name }}</h1></div></section>
<section class="pb-5"><div class="container">
<form class="form-shell mb-5" enctype="multipart/form-data" method="post" action="{{ route('admin.students.portfolio.save',$profile) }}">@csrf
<div class="row g-3">
<div class="col-md-7"><label class="form-label">Название работы</label><input class="form-control" name="title" required></div>
<div class="col-md-5"><label class="form-label">Тип</label><select class="form-select" name="type"><option value="project">Проект</option><option value="video">Видео</option><option value="3d">3D</option><option value="audio">Аудио</option><option value="design">Дизайн</option></select></div>
<div class="col-md-6"><label class="form-label">Студия</label><select class="form-select" name="studio_id"><option value="">Не выбрана</option>@foreach($studios as $s)<option value="{{ $s->id }}">{{ $s->title }}</option>@endforeach</select></div>
<div class="col-md-6"><label class="form-label">Дата завершения</label><input type="date" class="form-control" name="completed_at"></div>
<div class="col-12"><label class="form-label">Обложка</label><input type="file" class="form-control" name="cover" accept="image/*"></div>
<div class="col-md-6"><label class="form-label">Ссылка на проект</label><input class="form-control" name="project_url"></div>
<div class="col-md-6"><label class="form-label">Ссылка на видео</label><input class="form-control" name="video_url"></div>
<div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" rows="4" name="description"></textarea></div>
<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_public" value="1" checked><label class="form-check-label">Публично</label></div></div>
<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" value="1"><label class="form-check-label">Избранное</label></div></div>
<div class="col-md-4 text-end"><button class="btn btn-neon">Добавить работу</button></div>
</div></form>
<div class="row g-4">@forelse($profile->portfolio as $item)<div class="col-md-6 col-xl-4"><article class="project-card"><div class="project-media">@if($item->cover_url)<img src="{{ $item->cover_url }}" alt="">@else<div class="project-noise"></div>@endif</div><div class="pt-3"><h4>{{ $item->title }}</h4><div class="small text-white-50">{{ $item->type }} · {{ optional($item->completed_at)->format('d.m.Y') }}</div><form class="mt-3" method="post" action="{{ route('admin.portfolio.delete',$item) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form></div></article></div>@empty<div class="text-white-50">Работ пока нет.</div>@endforelse</div>
</div></section>
@endsection
