@extends('layouts.app')
@section('title','Медиагалерея · '.$project->title)
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Админ / работы учеников</div>
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
   <div>
    <h1 class="display-3 fw-bold m-0">Медиагалерея</h1>
    <p class="text-white-50 mt-2 mb-0">{{ $project->title }} · {{ $project->student->user->name ?? 'Ученик ШКИ' }}</p>
   </div>
   <a href="{{ route('admin.projects') }}" class="btn btn-ghost">← Работы учеников</a>
  </div>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  @if($errors->any())
   <div class="alert alert-danger">
    <strong>Не удалось добавить медиа:</strong>
    <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
   </div>
  @endif

  <form class="form-shell mb-5" method="post" enctype="multipart/form-data" action="{{ route('admin.projects.media.add',$project) }}">
   @csrf
   <div class="row g-3">
    <div class="col-md-3">
     <label class="form-label">Тип</label>
     <select class="form-select" name="type" required>
      <option value="photo">Фото</option>
      <option value="panorama">360° панорама</option>
      <option value="video">Видео</option>
      <option value="model">3D модель GLB/GLTF/STL</option>
      <option value="audio">Аудиотрек</option>
      <option value="file">Файл</option>
      <option value="link">Ссылка</option>
     </select>
    </div>
    <div class="col-md-9"><label class="form-label">Название</label><input class="form-control" name="title" value="{{ old('title') }}"></div>

    <div class="col-lg-7">
     <label class="form-label">URL</label>
     <input class="form-control" name="url" value="{{ old('url') }}" placeholder="https://...">
     <div class="form-text text-white-50">Можно указать внешнюю ссылку вместо загрузки файла.</div>
    </div>
    <div class="col-lg-5">
     <label class="form-label">Или загрузить файл</label>
     <input type="file" class="form-control" name="file" accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.mov,.glb,.gltf,.stl,.mp3,.wav,.ogg,.m4a,.aac,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip">
     <div class="form-text text-white-50">До 100 МБ.</div>
    </div>

    <div class="col-md-8"><label class="form-label">Описание</label><input class="form-control" name="caption" value="{{ old('caption') }}"></div>
    <div class="col-md-4"><label class="form-label">Превью / thumbnail URL</label><input class="form-control" name="thumbnail" value="{{ old('thumbnail') }}"></div>

    <div class="col-12">
     <label class="form-label">Hotspots для 360° (JSON)</label>
     <textarea class="form-control font-monospace" rows="4" name="hotspots_json" placeholder='[{"label":"Точка","yaw":35,"pitch":-5,"target_url":"/"}]'>{{ old('hotspots_json') }}</textarea>
    </div>

    <div class="col-md-2"><label class="form-label">Порядок</label><input type="number" min="0" class="form-control" name="sort_order" value="{{ old('sort_order',0) }}"></div>
    <div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_visible" value="1" checked><label class="form-check-label">Показывать</label></div></div>
    <div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_featured" value="1"><label class="form-check-label">Главное медиа работы</label></div></div>
    <div class="col-md-4 text-end d-flex align-items-end justify-content-end"><button class="btn btn-neon">Добавить медиа</button></div>
   </div>
  </form>

  <div class="glass-card p-4">
   <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Добавленные материалы</h3>
    <span class="badge-soft">{{ $project->media->count() }}</span>
   </div>
   <div class="table-responsive">
    <table class="table admin-table align-middle">
     <thead><tr><th>Тип</th><th>Название</th><th>Источник</th><th>Показывать</th><th>Главное</th><th></th></tr></thead>
     <tbody>
      @forelse($project->media as $m)
       <tr>
        <td><span class="badge-soft">{{ ['photo'=>'Фото','panorama'=>'360°','video'=>'Видео','model'=>'3D','audio'=>'Аудио','file'=>'Файл','link'=>'Ссылка'][$m->type] ?? $m->type }}</span></td>
        <td><strong>{{ $m->title ?: 'Без названия' }}</strong>@if($m->caption)<div class="small text-white-50">{{ IlluminateSupportStr::limit($m->caption,90) }}</div>@endif</td>
        <td class="text-truncate" style="max-width:320px">{{ $m->url }}</td>
        <td colspan="2">
         <form method="post" action="{{ route('admin.projects.media.flags',$m) }}" class="d-flex gap-3 align-items-center flex-wrap">
          @csrf @method('PATCH')
          <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="is_visible" value="1" @checked($m->is_visible) onchange="this.form.submit()"><label class="form-check-label small">Сайт</label></div>
          <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="is_featured" value="1" @checked($m->is_featured) onchange="this.form.submit()"><label class="form-check-label small">Главное</label></div>
         </form>
        </td>
        <td class="text-end">
         <form method="post" action="{{ route('admin.projects.media.delete',$m) }}" onsubmit="return confirm('Удалить материал?')">
          @csrf @method('DELETE')
          <button class="btn btn-sm btn-outline-danger">Удалить</button>
         </form>
        </td>
       </tr>
      @empty
       <tr><td colspan="6" class="text-white-50">Медиа ещё не добавлено.</td></tr>
      @endforelse
     </tbody>
    </table>
   </div>
  </div>
 </div>
</section>
@endsection
