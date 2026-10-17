@extends('layouts.app')
@section('title','Медиагалерея · '.$item->title)
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Портфолио преподавателя / медиа</div>
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
   <div>
    <h1 class="display-3 fw-bold m-0">Медиагалерея</h1>
    <p class="text-white-50 mt-2 mb-0">{{ $item->title }} · {{ $profile->user->name ?? '' }}</p>
   </div>
   <a href="{{ route('admin.teacher-portfolio.edit',auth()->user()->is_admin ? $profile->user_id : null) }}" class="btn btn-ghost">← Портфолио</a>
  </div>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())
   <div class="alert alert-danger"><strong>Не удалось добавить материал:</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <div class="alert alert-info">
   <strong>Ограничения:</strong>
   до 30 материалов на проект · фото {{ $limits['photo'] }} · 360° {{ $limits['panorama'] }} · видео {{ $limits['video'] }} · 3D {{ $limits['model'] }} · аудио {{ $limits['audio'] }} · документы {{ $limits['file'] }}.
  </div>

  <form class="form-shell mb-5" method="post" enctype="multipart/form-data" action="{{ route('admin.teacher-portfolio.media.add',[$profile,$item]) }}">
   @csrf
   <div class="row g-3">
    <div class="col-md-3">
     <label class="form-label">Тип</label>
     <select class="form-select" name="type" required>
      <option value="photo">Фото</option>
      <option value="panorama">360° панорама</option>
      <option value="video">Видео</option>
      <option value="model">3D модель</option>
      <option value="audio">Аудио</option>
      <option value="file">Документ / архив</option>
      <option value="link">Внешняя ссылка</option>
     </select>
    </div>
    <div class="col-md-9"><label class="form-label">Название</label><input class="form-control" name="title" value="{{ old('title') }}"></div>

    <div class="col-lg-7">
     <label class="form-label">URL</label>
     <input class="form-control" name="url" value="{{ old('url') }}" placeholder="https://...">
     <div class="form-text text-white-50">Можно использовать внешнюю ссылку вместо загрузки файла.</div>
    </div>
    <div class="col-lg-5">
     <label class="form-label">Или загрузить файл</label>
     <input type="file" class="form-control" name="file" accept=".jpg,.jpeg,.png,.webp,.mp4,.webm,.mov,.glb,.gltf,.stl,.mp3,.wav,.ogg,.m4a,.aac,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip">
    </div>

    <div class="col-md-8"><label class="form-label">Описание</label><input class="form-control" name="caption" value="{{ old('caption') }}"></div>
    <div class="col-md-4"><label class="form-label">URL превью</label><input class="form-control" name="thumbnail" value="{{ old('thumbnail') }}"></div>

    <div class="col-12">
     <label class="form-label">Hotspots для 360° (JSON)</label>
     <textarea class="form-control font-monospace" rows="4" name="hotspots_json" placeholder='[{"label":"Точка","yaw":35,"pitch":-5,"target_url":"/"}]'>{{ old('hotspots_json') }}</textarea>
    </div>

    <div class="col-md-2"><label class="form-label">Порядок</label><input type="number" min="0" max="9999" class="form-control" name="sort_order" value="{{ old('sort_order',0) }}"></div>
    <div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_visible" value="1" checked><label class="form-check-label">Показывать</label></div></div>
    <div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_featured" value="1"><label class="form-check-label">Главное медиа</label></div></div>
    <div class="col-md-4 text-end d-flex align-items-end justify-content-end"><button class="btn btn-neon">Добавить</button></div>
   </div>
  </form>

  <div class="glass-card p-4">
   <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Материалы проекта</h3>
    <span class="badge-soft">{{ $item->media->count() }} / 30</span>
   </div>
   <div class="table-responsive">
    <table class="table admin-table align-middle">
     <thead><tr><th>Тип</th><th>Название</th><th>Размер</th><th>Источник</th><th>Показывать</th><th>Главное</th><th></th></tr></thead>
     <tbody>
      @forelse($item->media as $m)
       <tr>
        <td><span class="badge-soft">{{ ['photo'=>'Фото','panorama'=>'360°','video'=>'Видео','model'=>'3D','audio'=>'Аудио','file'=>'Файл','link'=>'Ссылка'][$m->type] ?? $m->type }}</span></td>
        <td><strong>{{ $m->title ?: 'Без названия' }}</strong>@if($m->caption)<div class="small text-white-50">{{ mb_strimwidth($m->caption,0,90,'…') }}</div>@endif</td>
        <td class="text-nowrap">{{ $m->human_file_size ?? '—' }}</td>
        <td class="text-truncate" style="max-width:280px"><a href="{{ $m->display_url }}" target="_blank">{{ $m->file_name ?: $m->url }}</a></td>
        <td colspan="2">
         <form method="post" action="{{ route('admin.teacher-portfolio.media.flags',[$profile,$item,$m]) }}" class="d-flex gap-3 align-items-center flex-wrap">
          @csrf @method('PATCH')
          <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="is_visible" value="1" @checked($m->is_visible) onchange="this.form.submit()"><label class="form-check-label small">Сайт</label></div>
          <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="is_featured" value="1" @checked($m->is_featured) onchange="this.form.submit()"><label class="form-check-label small">Главное</label></div>
         </form>
        </td>
        <td class="text-end">
         <form method="post" action="{{ route('admin.teacher-portfolio.media.delete',[$profile,$item,$m]) }}" onsubmit="return confirm('Удалить материал?')">
          @csrf @method('DELETE')
          <button class="btn btn-sm btn-outline-danger">Удалить</button>
         </form>
        </td>
       </tr>
      @empty
       <tr><td colspan="7" class="text-white-50">Материалы ещё не добавлены.</td></tr>
      @endforelse
     </tbody>
    </table>
   </div>
  </div>
 </div>
</section>
@endsection
