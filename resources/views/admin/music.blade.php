@extends('layouts.app')
@section('title','Музыка сайта · Админ')
@section('content')
@php($editing=$editTrack ?? null)
<section class="page-top"><div class="container"><div class="eyebrow">Админ / музыка</div><div class="d-flex justify-content-between align-items-end gap-3 flex-wrap"><h1 class="display-3 fw-bold mb-0">Музыка сайта</h1><a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">← Админка</a></div></div></section>

<section class="pb-5"><div class="container">
 <div class="row g-4">
  <div class="col-xl-5">
   <form method="post" enctype="multipart/form-data" action="{{ route('admin.music.save',$editing) }}" class="glass-card p-4">@csrf
    <h3>{{ $editing ? 'Редактировать трек' : 'Добавить трек' }}</h3>
    <div class="row g-3 mt-1">
     <div class="col-12"><label class="form-label">Название</label><input class="form-control" name="title" value="{{ old('title',$editing->title ?? '') }}" required></div>
     <div class="col-12"><label class="form-label">Исполнитель</label><input class="form-control" name="artist" value="{{ old('artist',$editing->artist ?? '') }}"></div>
     <div class="col-12"><label class="form-label">Аудиофайл</label><input type="file" class="form-control" name="audio_file" accept=".mp3,.wav,.ogg,.m4a,.aac,audio/*" {{ $editing ? '' : 'required' }}>@if($editing)<div class="small text-white-50 mt-1">Текущий файл: {{ $editing->file_name }}</div>@endif</div>
     <div class="col-md-6"><label class="form-label">Порядок</label><input type="number" min="0" class="form-control" name="sort_order" value="{{ old('sort_order',$editing->sort_order ?? 0) }}"></div>
     <div class="col-md-6 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" {{ old('is_active',$editing->is_active ?? true) ? 'checked' : '' }}><label class="form-check-label">Активен</label></div></div>
     <div class="col-12 d-flex justify-content-end gap-2">@if($editing)<a href="{{ route('admin.music') }}" class="btn btn-ghost">Отмена</a>@endif<button class="btn btn-neon">{{ $editing ? 'Сохранить' : 'Добавить трек' }}</button></div>
    </div>
   </form>
  </div>

  <div class="col-xl-7">
   <div class="glass-card p-4">
    <h3 class="mb-4">Плейлист</h3>
    <div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Трек</th><th>Размер</th><th>Статус</th><th></th></tr></thead><tbody>
    @forelse($tracks as $track)
     <tr><td><strong>{{ $track->title }}</strong><div class="small text-white-50">{{ $track->artist ?: 'Без исполнителя' }}</div></td><td>{{ $track->human_file_size ?: '—' }}</td><td>{{ $track->is_active ? 'Активен' : 'Скрыт' }}</td><td class="text-end"><div class="d-flex gap-2 justify-content-end flex-wrap"><a href="{{ $track->file_url }}" target="_blank" class="btn btn-sm btn-ghost">Слушать</a><a href="{{ route('admin.music.edit',$track) }}" class="btn btn-sm btn-ghost">Редактировать</a><form method="post" action="{{ route('admin.music.delete',$track) }}" onsubmit="return confirm('Удалить трек?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form></div></td></tr>
    @empty<tr><td colspan="4" class="text-white-50">Нет загруженных треков.</td></tr>@endforelse
    </tbody></table></div>
   </div>
  </div>
 </div>
</div></section>
@endsection
