@extends('layouts.app')
@section('title','Оборудование · Админ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Админ / оборудование</div><div class="d-flex justify-content-between align-items-end gap-3 flex-wrap"><h1 class="display-3 fw-bold m-0">Оборудование</h1><a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">← Админка</a></div></div></section>
<section class="pb-5"><div class="container">
<form class="form-shell mb-5" method="post" enctype="multipart/form-data" action="{{ route('admin.equipment.save') }}">@csrf
<div class="row g-3">
<div class="col-md-7"><label class="form-label">Название</label><input class="form-control" name="title" required></div>
<div class="col-md-5"><label class="form-label">Категория</label><input class="form-control" name="category" placeholder="Камера, микрофон, VR..."></div>
<div class="col-md-4"><label class="form-label">Бренд</label><input class="form-control" name="brand"></div>
<div class="col-md-4"><label class="form-label">Модель</label><input class="form-control" name="model"></div>
<div class="col-md-4"><label class="form-label">Студия</label><select class="form-select" name="studio_id"><option value="">Общее оборудование</option>@foreach($studios as $s)<option value="{{ $s->id }}">{{ $s->title }}</option>@endforeach</select></div>
<div class="col-12"><label class="form-label">Фото</label><input type="file" class="form-control" name="image" accept="image/*"></div>
<div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" rows="4" name="description"></textarea></div>
<div class="col-12"><label class="form-label">Характеристики JSON</label><textarea class="form-control font-monospace" rows="5" name="specs_json" placeholder='{"Разрешение":"4K","Матрица":"Full Frame","FPS":"120"}'></textarea></div>
<div class="col-md-4"><label class="form-label">Порядок</label><input type="number" class="form-control" name="sort_order" value="0"></div>
<div class="col-md-4 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_featured" value="1" id="featuredEq"><label class="form-check-label" for="featuredEq">На главную</label></div></div>
<div class="col-md-4 text-end d-flex align-items-end justify-content-end"><button class="btn btn-neon">Добавить</button></div>
</div></form>
<div class="glass-card p-4"><div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Название</th><th>Категория</th><th>Студия</th><th>Главная</th><th></th></tr></thead><tbody>
@forelse($items as $i)<tr><td><strong>{{ $i->title }}</strong><div class="small text-white-50">{{ trim(($i->brand ?? '').' '.($i->model ?? '')) }}</div></td><td>{{ $i->category ?: '—' }}</td><td>{{ $i->studio->title ?? 'Общее' }}</td><td>{{ $i->is_featured?'Да':'—' }}</td><td class="text-end"><form method="post" action="{{ route('admin.equipment.delete',$i) }}" onsubmit="return confirm('Удалить оборудование?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form></td></tr>@empty<tr><td colspan="5" class="text-white-50">Каталог пока пуст.</td></tr>@endforelse
</tbody></table></div></div>
</div></section>
@endsection
