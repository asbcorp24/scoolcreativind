@extends('layouts.app')
@section('title','Расписание · Админ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Админ / расписание</div><div class="d-flex justify-content-between align-items-end flex-wrap gap-3"><h1 class="display-3 fw-bold m-0">Расписание</h1><a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">← Админка</a></div></div></section>
<section class="pb-5"><div class="container">
<form class="form-shell mb-5" method="post" action="{{ route('admin.schedule.save') }}">@csrf
<div class="row g-3">
<div class="col-md-7"><label class="form-label">Занятие</label><input class="form-control" name="title" required></div>
<div class="col-md-5"><label class="form-label">Студия</label><select class="form-select" name="studio_id"><option value="">Общее</option>@foreach($studios as $s)<option value="{{ $s->id }}">{{ $s->title }}</option>@endforeach</select></div>
<div class="col-md-4"><label class="form-label">Дата</label><input type="date" class="form-control" name="lesson_date" required></div>
<div class="col-md-2"><label class="form-label">Начало</label><input type="time" class="form-control" name="starts_at" required></div>
<div class="col-md-2"><label class="form-label">Конец</label><input type="time" class="form-control" name="ends_at" required></div>
<div class="col-md-4"><label class="form-label">Аудитория</label><input class="form-control" name="room"></div>
<div class="col-md-6"><label class="form-label">Преподаватель</label><input class="form-control" name="teacher_name"></div>
<div class="col-md-3"><label class="form-label">Цвет</label><input type="color" class="form-control form-control-color w-100" name="color" value="#8a5cff"></div>
<div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_published" value="1" checked><label class="form-check-label">Показывать</label></div></div>
<div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" rows="4" name="description"></textarea></div>
<div class="col-12 text-end"><button class="btn btn-neon">Добавить занятие</button></div>
</div></form>
<div class="glass-card p-4"><div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Дата</th><th>Время</th><th>Занятие</th><th>Студия</th><th></th></tr></thead><tbody>@forelse($lessons as $l)<tr><td>{{ $l->lesson_date->format('d.m.Y') }}</td><td>{{ substr($l->starts_at,0,5) }}–{{ substr($l->ends_at,0,5) }}</td><td><strong>{{ $l->title }}</strong><div class="small text-white-50">{{ $l->teacher_name }}</div></td><td>{{ $l->studio->title ?? 'Общее' }}</td><td class="text-end"><form method="post" action="{{ route('admin.schedule.delete',$l) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form></td></tr>@empty<tr><td colspan="5">Пока пусто.</td></tr>@endforelse</tbody></table></div></div>
</div></section>
@endsection
