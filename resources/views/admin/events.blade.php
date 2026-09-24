@extends('layouts.app')
@section('title','События · Админ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Админ / события</div><div class="d-flex justify-content-between align-items-end gap-3 flex-wrap"><h1 class="display-3 fw-bold m-0">События и мастер-классы</h1><a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">← Админка</a></div></div></section>
<section class="pb-5"><div class="container">
<form class="form-shell mb-5" method="post" action="{{ route('admin.events.save') }}">@csrf
<div class="row g-3">
<div class="col-md-8"><label class="form-label">Название</label><input class="form-control" name="title" required></div>
<div class="col-md-4"><label class="form-label">Slug</label><input class="form-control" name="slug"></div>
<div class="col-md-6"><label class="form-label">Дата и время</label><input type="datetime-local" class="form-control" name="starts_at" required></div>
<div class="col-md-6"><label class="form-label">Место</label><input class="form-control" name="location" value="г. Волжск, ул. Ленина, 32"></div>
<div class="col-md-6"><label class="form-label">URL обложки</label><input class="form-control" name="cover"></div>
<div class="col-md-6"><label class="form-label">Ссылка регистрации</label><input class="form-control" name="registration_url"></div>
<div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" rows="5" name="description"></textarea></div>
<div class="col-md-6 d-flex align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_published" value="1" checked id="pub"><label class="form-check-label" for="pub">Опубликовано</label></div></div>
<div class="col-md-6 text-end"><button class="btn btn-neon">Добавить событие</button></div>
</div></form>
<div class="glass-card p-4"><div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Дата</th><th>Событие</th><th>Место</th><th>Статус</th><th></th></tr></thead><tbody>
@forelse($events as $e)<tr><td>{{ $e->starts_at->format('d.m.Y H:i') }}</td><td><strong>{{ $e->title }}</strong></td><td>{{ $e->location ?: '—' }}</td><td>{{ $e->is_published?'Опубликовано':'Скрыто' }}</td><td class="text-end"><form method="post" action="{{ route('admin.events.delete',$e) }}" onsubmit="return confirm('Удалить событие?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form></td></tr>@empty<tr><td colspan="5" class="text-white-50">Событий пока нет.</td></tr>@endforelse
</tbody></table></div></div>
</div></section>
@endsection
