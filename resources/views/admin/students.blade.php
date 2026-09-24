@extends('layouts.app')
@section('title','Ученики · Админ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Админ / ученики</div><div class="d-flex justify-content-between align-items-end flex-wrap gap-3"><h1 class="display-3 fw-bold m-0">Портфолио учеников</h1><a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">← Админка</a></div></div></section>
<section class="pb-5"><div class="container">
<form class="form-shell mb-5" method="post" enctype="multipart/form-data" action="{{ route('admin.students.save') }}">@csrf
<div class="row g-3">
<div class="col-md-6"><label class="form-label">Пользователь</label><select class="form-select" name="user_id" required>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }} · {{ $u->email }}</option>@endforeach</select></div>
<div class="col-md-6"><label class="form-label">Студия</label><select class="form-select" name="studio_id"><option value="">Не выбрана</option>@foreach($studios as $s)<option value="{{ $s->id }}">{{ $s->title }}</option>@endforeach</select></div>
<div class="col-md-6"><label class="form-label">Группа / класс</label><input class="form-control" name="class_name"></div>
<div class="col-md-6"><label class="form-label">Slug портфолио</label><input class="form-control" name="portfolio_slug"></div>
<div class="col-12"><label class="form-label">Аватар</label><input type="file" class="form-control" name="avatar" accept="image/*"></div>
<div class="col-12"><label class="form-label">О себе</label><textarea class="form-control" rows="4" name="bio"></textarea></div>
<div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_public" value="1"><label class="form-check-label">Публичное портфолио</label></div></div>
<div class="col-md-6 text-end"><button class="btn btn-neon">Создать профиль</button></div>
</div></form>
<div class="glass-card p-4"><div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Ученик</th><th>Студия</th><th>Группа</th><th>Публичность</th><th></th></tr></thead><tbody>@forelse($profiles as $p)<tr><td><strong>{{ $p->user->name }}</strong><div class="small text-white-50">{{ $p->user->email }}</div></td><td>{{ $p->studio->title ?? '—' }}</td><td>{{ $p->class_name ?? '—' }}</td><td>{{ $p->is_public?'Да':'Нет' }}</td><td class="text-end"><a href="{{ route('admin.students.portfolio',$p) }}" class="btn btn-sm btn-ghost">Портфолио</a>@if($p->is_public)<a href="{{ route('portfolio.show',$p) }}" class="btn btn-sm btn-ghost">Открыть</a>@endif</td></tr>@empty<tr><td colspan="5">Профилей пока нет.</td></tr>@endforelse</tbody></table></div></div>
</div></section>
@endsection
