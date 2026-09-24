@extends('layouts.app')
@section('title','Команда · Админ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Админ / команда</div><div class="d-flex justify-content-between align-items-end gap-3 flex-wrap"><h1 class="display-3 fw-bold m-0">Команда</h1><a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">← Админка</a></div></div></section>
<section class="pb-5"><div class="container">
<form class="form-shell mb-5" method="post" enctype="multipart/form-data" action="{{ route('admin.team.save') }}">@csrf
<div class="row g-3">
<div class="col-md-7"><label class="form-label">ФИО</label><input class="form-control" name="name" required></div>
<div class="col-md-5"><label class="form-label">Должность</label><input class="form-control" name="role"></div>
<div class="col-md-6"><label class="form-label">Студия</label><select class="form-select" name="studio_id"><option value="">Вся школа</option>@foreach($studios as $s)<option value="{{ $s->id }}">{{ $s->title }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label">Порядок</label><input type="number" class="form-control" name="sort_order" value="0"></div>
<div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked id="active"><label class="form-check-label" for="active">Показывать</label></div></div>
<div class="col-12"><label class="form-label">Фото</label><input type="file" class="form-control" name="photo" accept="image/*"></div>
<div class="col-12"><label class="form-label">О специалисте</label><textarea class="form-control" rows="5" name="bio"></textarea></div>
<div class="col-md-4"><label class="form-label">Email</label><input type="email" class="form-control" name="email"></div>
<div class="col-md-4"><label class="form-label">VK</label><input class="form-control" name="vk_url"></div>
<div class="col-md-4"><label class="form-label">Telegram</label><input class="form-control" name="telegram_url"></div>
<div class="col-12 text-end"><button class="btn btn-neon">Добавить в команду</button></div>
</div></form>
<div class="glass-card p-4"><div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Имя</th><th>Должность</th><th>Студия</th><th>Статус</th><th></th></tr></thead><tbody>
@forelse($members as $m)<tr><td><strong>{{ $m->name }}</strong></td><td>{{ $m->role ?: '—' }}</td><td>{{ $m->studio->title ?? 'Вся школа' }}</td><td>{{ $m->is_active?'Показывается':'Скрыт' }}</td><td class="text-end"><form method="post" action="{{ route('admin.team.delete',$m) }}" onsubmit="return confirm('Удалить сотрудника?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form></td></tr>@empty<tr><td colspan="5" class="text-white-50">Команда пока не заполнена.</td></tr>@endforelse
</tbody></table></div></div>
</div></section>
@endsection
