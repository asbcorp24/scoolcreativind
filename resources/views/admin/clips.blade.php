@extends('layouts.app')
@section('title','Клипы · Админ')
@section('content')
@php($editing=$editClip ?? null)
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Админ / клипы</div>
  <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
   <h1 class="display-3 fw-bold mb-0">Клипы</h1>
   <a href="{{ route('clips.index') }}" class="btn btn-ghost" target="_blank">Открыть раздел ↗</a>
  </div>
 </div>
</section>

<section class="pb-5"><div class="container">
 <div class="row g-4">
  <div class="col-xl-5">
   <form method="post" enctype="multipart/form-data" action="{{ route('admin.clips.save',$editing) }}" class="glass-card p-4">@csrf
    <h3>{{ $editing ? 'Редактировать клип' : 'Загрузить клип' }}</h3>
    <p class="text-white-50 small">ZIP должен содержать готовый клип с файлом <code>index.html</code>. Можно включать CSS, JS, Three.js, MP3, изображения, GLB/GLTF, видео и шрифты.</p>
    <div class="row g-3 mt-1">
      <div class="col-12"><label class="form-label">Название</label><input class="form-control" name="title" value="{{ old('title',$editing->title ?? '') }}" required></div>
      <div class="col-12"><label class="form-label">URL slug</label><input class="form-control" name="slug" value="{{ old('slug',$editing->slug ?? '') }}" placeholder="design-feeling"><div class="form-text text-white-50">Только латиница, цифры и дефис. Можно оставить пустым.</div></div>
      <div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" rows="4" name="description">{{ old('description',$editing->description ?? '') }}</textarea></div>
      <div class="col-12"><label class="form-label">{{ $editing ? 'Новый ZIP-архив (необязательно)' : 'ZIP-архив клипа' }}</label><input type="file" class="form-control" name="archive" accept=".zip,application/zip" {{ $editing ? '' : 'required' }}>@if($editing)<div class="small text-white-50 mt-1">Текущий: {{ $editing->archive_name ?: 'архив загружен' }}</div>@endif</div>
      <div class="col-12"><label class="form-label">Обложка JPG/PNG/WebP</label><input type="file" class="form-control" name="cover" accept="image/jpeg,image/png,image/webp">@if($editing?->cover_url)<img src="{{ $editing->cover_url }}" class="admin-clip-cover-preview mt-3" alt="">@endif</div>
      <div class="col-md-6"><label class="form-label">Порядок</label><input type="number" min="0" class="form-control" name="sort_order" value="{{ old('sort_order',$editing->sort_order ?? 0) }}"></div>
      <div class="col-md-6 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_published" value="1" {{ old('is_published',$editing->is_published ?? true) ? 'checked' : '' }}><label class="form-check-label">Опубликован</label></div></div>
      <div class="col-12 d-flex justify-content-end gap-2">@if($editing)<a href="{{ route('admin.clips') }}" class="btn btn-ghost">Отмена</a>@endif<button class="btn btn-neon">{{ $editing ? 'Сохранить' : 'Загрузить и распаковать' }}</button></div>
    </div>
   </form>
  </div>

  <div class="col-xl-7">
   <div class="glass-card p-4">
    <div class="d-flex justify-content-between gap-3 align-items-center mb-4"><h3 class="mb-0">Загруженные клипы</h3><span class="badge-soft">{{ $clips->count() }}</span></div>
    <div class="table-responsive">
     <table class="table admin-table align-middle">
      <thead><tr><th>Клип</th><th>Статус</th><th>Порядок</th><th></th></tr></thead>
      <tbody>
      @forelse($clips as $clip)
       <tr>
        <td><strong>{{ $clip->title }}</strong><div class="small text-white-50">/clips/{{ $clip->slug }}</div><div class="small text-white-50">{{ $clip->archive_name }}</div></td>
        <td>{{ $clip->is_published ? 'Опубликован' : 'Скрыт' }}</td>
        <td>{{ $clip->sort_order }}</td>
        <td class="text-end"><div class="d-flex justify-content-end gap-2 flex-wrap">
          @if($clip->is_published)<a class="btn btn-sm btn-ghost" href="{{ route('clips.show',$clip) }}" target="_blank">Смотреть</a>@endif
          <a class="btn btn-sm btn-ghost" href="{{ route('admin.clips.edit',$clip) }}">Изменить</a>
          <form method="post" action="{{ route('admin.clips.delete',$clip) }}" onsubmit="return confirm('Удалить клип и все распакованные файлы?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form>
        </div></td>
       </tr>
      @empty
       <tr><td colspan="4" class="text-white-50">Клипы ещё не загружены.</td></tr>
      @endforelse
      </tbody>
     </table>
    </div>
   </div>
  </div>
 </div>
</div></section>
@endsection
