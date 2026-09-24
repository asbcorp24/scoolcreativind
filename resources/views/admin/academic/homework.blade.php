@extends('layouts.app')
@section('title','Домашние задания · Админ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Админ / домашние задания</div><h1 class="display-3 fw-bold">Домашние задания</h1></div></section>
<section class="pb-5"><div class="container">
<form class="form-shell mb-5" method="post" enctype="multipart/form-data" action="{{ route('admin.homework.save') }}">@csrf<div class="row g-3">
<div class="col-md-6"><label class="form-label">Группа</label><select class="form-select" name="study_group_id">@foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach</select></div>
<div class="col-md-6"><label class="form-label">Предмет</label><select class="form-select" name="subject_id">@foreach($subjects as $s)<option value="{{ $s->id }}">{{ $s->title }}</option>@endforeach</select></div>
<div class="col-12"><label class="form-label">Заголовок</label><input class="form-control" name="title" required></div>
<div class="col-12"><label class="form-label">Задание</label><textarea class="form-control" rows="6" name="description"></textarea></div>
<div class="col-md-6"><label class="form-label">Срок</label><input type="datetime-local" class="form-control" name="due_at"></div><div class="col-md-3"><label class="form-label">Макс. балл</label><input type="number" class="form-control" name="max_score" value="5"></div><div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_published" value="1" checked><label class="form-check-label">Опубликовать</label></div></div>
<div class="col-md-6"><label class="form-label">Файл</label><input type="file" class="form-control" name="attachment"></div><div class="col-md-6"><label class="form-label">Ссылка</label><input class="form-control" name="external_url"></div><div class="col-12 text-end"><button class="btn btn-neon">Создать задание</button></div>
</div></form>
<div class="row g-3">@foreach($assignments as $a)<div class="col-md-6"><article class="glass-card p-4 h-100"><div class="small text-white-50">{{ $a->group->name ?? '' }} · {{ $a->subject->title ?? '' }}</div><h3>{{ $a->title }}</h3><div class="d-flex justify-content-between align-items-center"><span>{{ $a->submissions->count() }} сдач</span><a class="btn btn-ghost btn-sm" href="{{ route('admin.homework.submissions',$a) }}">Проверить</a></div></article></div>@endforeach</div>
</div></section>
@endsection
