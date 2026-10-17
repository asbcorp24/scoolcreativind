@extends('layouts.app')
@section('title','Документы и файлы · Админ')
@section('content')
@php($editing=$editDocument ?? null)
<section class="page-top"><div class="container"><div class="eyebrow">Админ / материалы</div><div class="d-flex justify-content-between align-items-end gap-3 flex-wrap"><h1 class="display-3 fw-bold mb-0">Документы, файлы и ссылки</h1><a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">← Админка</a></div></div></section>

<section class="pb-5"><div class="container">
 @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
 <div class="row g-4">
  <div class="col-xl-5">
   <form method="post" enctype="multipart/form-data" action="{{ route('admin.documents.save',$editing) }}" class="glass-card p-4">@csrf
    <h3>{{ $editing ? 'Редактировать материал' : 'Добавить материал' }}</h3>
    <div class="row g-3 mt-1">
     <div class="col-12"><label class="form-label">Название</label><input class="form-control" name="title" value="{{ old('title',$editing->title ?? '') }}" required></div>
     <div class="col-md-6"><label class="form-label">Тип</label><select class="form-select" name="item_type" required>
      @foreach(['document'=>'Документ','application'=>'Приложение','file'=>'Файл','link'=>'Ссылка'] as $value=>$label)
       <option value="{{ $value }}" {{ old('item_type',$editing->item_type ?? 'document')===$value ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
     </select></div>
     <div class="col-md-6"><label class="form-label">Категория</label><input class="form-control" name="category" value="{{ old('category',$editing->category ?? '') }}" placeholder="Программы / Инструкции"></div>
     <div class="col-md-6"><label class="form-label">№ документа</label><input class="form-control" name="document_number" value="{{ old('document_number',$editing->document_number ?? '') }}"></div>
     <div class="col-md-6"><label class="form-label">Дата</label><input type="date" class="form-control" name="document_date" value="{{ old('document_date',optional($editing?->document_date)->format('Y-m-d')) }}"></div>
     <div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" rows="4" name="description">{{ old('description',$editing->description ?? '') }}</textarea></div>

     <div class="col-12">
      <label class="form-label">Загрузить файл</label>
      <input type="file" class="form-control" name="document_file">
      <div class="small text-white-50 mt-1">До 200 МБ. Поддерживаются PDF/Office, архивы, EXE/MSI, APK, изображения, аудио и видео.</div>
      @if($editing?->file_name)<div class="small mt-1">Текущий файл: <strong>{{ $editing->file_name }}</strong> @if($editing->human_file_size)· {{ $editing->human_file_size }}@endif</div>@endif
     </div>

     <div class="col-12"><div class="text-center text-white-50 small">— или —</div></div>

     <div class="col-12"><label class="form-label">Внешняя ссылка</label><input type="url" class="form-control" name="external_url" value="{{ old('external_url',$editing->external_url ?? '') }}" placeholder="https://..."><div class="small text-white-50 mt-1">Можно использовать вместо файла: сайт программы, облако, GitHub Release и т. п.</div></div>
     <div class="col-md-7"><label class="form-label">Текст кнопки</label><input class="form-control" name="button_label" value="{{ old('button_label',$editing->button_label ?? '') }}" placeholder="Скачать / Открыть / Перейти"></div>
     <div class="col-md-5"><label class="form-label">Порядок</label><input type="number" min="0" class="form-control" name="sort_order" value="{{ old('sort_order',$editing->sort_order ?? 0) }}"></div>
     <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_published" value="1" {{ old('is_published',$editing->is_published ?? true) ? 'checked' : '' }}><label class="form-check-label">Опубликовать</label></div></div>
     <div class="col-12 d-flex gap-2 justify-content-end">@if($editing)<a href="{{ route('admin.documents') }}" class="btn btn-ghost">Отмена</a>@endif<button class="btn btn-neon">{{ $editing ? 'Сохранить' : 'Добавить' }}</button></div>
    </div>
   </form>
  </div>

  <div class="col-xl-7">
   <div class="glass-card p-4"><div class="d-flex justify-content-between gap-3 align-items-center mb-4"><h3 class="mb-0">Материалы</h3><span class="text-white-50">{{ $documents->count() }}</span></div><div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Материал</th><th>Тип</th><th>Статус</th><th></th></tr></thead><tbody>
   @forelse($documents as $doc)
    <tr>
     <td><strong>{{ $doc->title }}</strong><div class="small text-white-50">{{ $doc->category ?: 'Без категории' }} @if($doc->file_name) · {{ $doc->file_name }}@elseif($doc->external_url) · внешняя ссылка@endif</div></td>
     <td>{{ $doc->type_label }}</td>
     <td>{{ $doc->is_published ? 'Опубликован' : 'Скрыт' }}</td>
     <td class="text-end"><div class="d-flex gap-2 justify-content-end flex-wrap">
      @if($doc->target_url)<a href="{{ $doc->target_url }}" target="_blank" rel="noopener" class="btn btn-sm btn-ghost">Открыть</a>@endif
      <a href="{{ route('admin.documents.edit',$doc) }}" class="btn btn-sm btn-ghost">Редактировать</a>
      <form method="post" action="{{ route('admin.documents.delete',$doc) }}" onsubmit="return confirm('Удалить материал?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form>
     </div></td>
    </tr>
   @empty<tr><td colspan="4" class="text-white-50">Материалов пока нет.</td></tr>@endforelse
   </tbody></table></div></div>
  </div>
 </div>
</div></section>
@endsection
