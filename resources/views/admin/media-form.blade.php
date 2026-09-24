@extends('layouts.app')
@section('title','Медиа · '.$studio->title)
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Админ / {{ $studio->title }}</div><div class="d-flex justify-content-between align-items-end flex-wrap gap-3"><h1 class="display-3 fw-bold m-0">Медиагалерея</h1><a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">← Админка</a></div></div></section>
<section class="pb-5"><div class="container">
@if($errors->any())<div class="alert alert-danger"><strong>Не удалось добавить медиа:</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form class="form-shell mb-5" method="post" enctype="multipart/form-data" action="{{ route('admin.media.add',$studio) }}">@csrf
 <div class="row g-3"><div class="col-md-3"><label class="form-label">Тип</label><select class="form-select" name="type" required><option value="photo">Фото</option><option value="panorama">360° панорама</option><option value="video">Rutube видео</option><option value="model">3D модель GLB/GLTF</option><option value="audio">Аудиотрек</option></select></div><div class="col-md-9"><label class="form-label">Название</label><input class="form-control" name="title"></div>
 <div class="col-lg-7"><label class="form-label">URL</label><input class="form-control" name="url" placeholder="https://..."><div class="form-text text-white-50">Rutube — ссылка на видео. Для фото/360/3D можно использовать URL или загрузку файла.</div></div>
<div class="col-lg-5"><label class="form-label">Или загрузить файл</label><input type="file" class="form-control" name="file" accept=".jpg,.jpeg,.png,.webp,.glb,.gltf,.mp3,.wav,.ogg,.m4a,.aac"><div class="form-text text-white-50">До 50 МБ. Для 3D: GLB или GLTF. Если файл больше лимита PHP upload_max_filesize/post_max_size, он не дойдёт до Laravel.</div></div>
 <div class="col-md-8"><label class="form-label">Описание</label><input class="form-control" name="caption"></div>
<div class="col-12">
 <label class="form-label">Hotspots для 360° (JSON)</label>
 <textarea class="form-control font-monospace" rows="5" name="hotspots_json" placeholder='[{"label":"К звукозаписи","yaw":35,"pitch":-5,"target_url":"/studios/sound"}]'></textarea>
 <div class="form-text text-white-50">Для панорамы: label — подпись, yaw/pitch — угол точки в градусах, target_url — куда перейти при клике.</div>
</div><div class="col-md-2"><label class="form-label">Порядок</label><input type="number" min="0" class="form-control" name="sort_order" value="0"></div><div class="col-md-2 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_visible" value="1" checked><label class="form-check-label">Показывать</label></div></div><div class="col-md-2 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_featured" value="1"><label class="form-check-label">На главную</label></div></div>
 <div class="col-12 text-end"><button class="btn btn-neon">Добавить</button></div></div>
</form>
<div class="glass-card p-4"><h3 class="mb-4">Добавленные материалы</h3><div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Тип</th><th>Название</th><th>URL</th><th>Показывать</th><th>Главная</th><th></th></tr></thead><tbody>
@forelse($studio->media()->orderBy('sort_order')->get() as $m)<tr><td><span class="badge-soft">{{ (['photo'=>'Фото','panorama'=>'360°','video'=>'Видео','model'=>'3D','audio'=>'Аудио'][$m->type] ?? $m->type) }}</span></td><td>{{ $m->title ?: 'Без названия' }}</td><td class="text-truncate" style="max-width:360px">{{ $m->url }}</td><td colspan="2">
<form method="post" action="{{ route('admin.media.flags',$m) }}" class="d-flex gap-3 align-items-center flex-wrap">@csrf @method('PATCH')
<div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="is_visible" value="1" @checked($m->is_visible) onchange="this.form.submit()"><label class="form-check-label small">Сайт</label></div>
<div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="is_featured" value="1" @checked($m->is_featured) onchange="this.form.submit()"><label class="form-check-label small">Главная</label></div>
</form>
</td><td class="text-end"><form method="post" action="{{ route('admin.media.delete',$m) }}" onsubmit="return confirm('Удалить материал?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form></td></tr>@empty<tr><td colspan="6" class="text-white-50">Пока пусто.</td></tr>@endforelse
</tbody></table></div></div></div></section>
@endsection
