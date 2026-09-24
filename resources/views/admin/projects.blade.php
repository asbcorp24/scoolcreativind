@extends('layouts.app')
@section('title','Проекты учеников · Админ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Админ / портфолио</div><div class="d-flex justify-content-between align-items-end gap-3 flex-wrap"><h1 class="display-3 fw-bold m-0">Проекты учеников</h1><a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">← Админка</a></div></div></section>
<section class="pb-5"><div class="container">
<form class="form-shell mb-5" method="post" action="{{ route('admin.projects.save') }}">@csrf
<div class="row g-3">
<div class="col-md-6"><label class="form-label">Название проекта</label><input class="form-control" name="title" required></div>
<div class="col-md-6"><label class="form-label">Студия</label><select class="form-select" name="studio_id"><option value="">Без привязки</option>@foreach($studios as $s)<option value="{{ $s->id }}">{{ $s->title }}</option>@endforeach</select></div>
<div class="col-md-6"><label class="form-label">Автор</label><input class="form-control" name="author"></div>
<div class="col-md-2"><label class="form-label">Год</label><input class="form-control" name="year" value="{{ date('Y') }}"></div>
<div class="col-md-4"><label class="form-label">URL проекта</label><input class="form-control" name="project_url"></div>
<div class="col-12"><label class="form-label">URL обложки</label><input class="form-control" name="cover" placeholder="https://..."></div>
<div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" rows="4" name="description"></textarea></div>
<div class="col-md-6 d-flex align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" value="1" id="featured"><label class="form-check-label" for="featured">Показывать на главной</label></div></div>
<div class="col-md-6 text-end"><button class="btn btn-neon">Добавить проект</button></div>
</div></form>
<div class="glass-card p-4"><div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Проект</th><th>Автор</th><th>Студия</th><th>Главная</th><th></th></tr></thead><tbody>
@forelse($projects as $p)<tr><td><strong>{{ $p->title }}</strong><div class="small text-white-50">{{ $p->year }}</div></td><td>{{ $p->author ?: '—' }}</td><td>{{ $p->studio->title ?? '—' }}</td><td>{{ $p->is_featured?'Да':'—' }}</td><td class="text-end"><form method="post" action="{{ route('admin.projects.delete',$p) }}" onsubmit="return confirm('Удалить проект?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form></td></tr>@empty<tr><td colspan="5" class="text-white-50">Проектов пока нет.</td></tr>@endforelse
</tbody></table></div></div>
</div></section>
@endsection
