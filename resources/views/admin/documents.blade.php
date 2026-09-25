@extends('layouts.app')
@section('title','Документы · Админ')
@section('content')
@php($editing=$editDocument ?? null)
<section class="page-top"><div class="container"><div class="eyebrow">Админ / документы</div><div class="d-flex justify-content-between align-items-end gap-3 flex-wrap"><h1 class="display-3 fw-bold mb-0">Официальные документы</h1><a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">← Админка</a></div></div></section>

<section class="pb-5"><div class="container">
 <div class="row g-4">
  <div class="col-xl-5">
   <form method="post" enctype="multipart/form-data" action="{{ route('admin.documents.save',$editing) }}" class="glass-card p-4">@csrf
    <h3>{{ $editing ? 'Редактировать документ' : 'Добавить документ' }}</h3>
    <div class="row g-3 mt-1">
     <div class="col-12"><label class="form-label">Название</label><input class="form-control" name="title" value="{{ old('title',$editing->title ?? '') }}" required></div>
     <div class="col-md-7"><label class="form-label">Категория</label><input class="form-control" name="category" value="{{ old('category',$editing->category ?? '') }}" placeholder="Локальные акты"></div>
     <div class="col-md-5"><label class="form-label">№ документа</label><input class="form-control" name="document_number" value="{{ old('document_number',$editing->document_number ?? '') }}"></div>
     <div class="col-md-7"><label class="form-label">Дата документа</label><input type="date" class="form-control" name="document_date" value="{{ old('document_date',optional($editing?->document_date)->format('Y-m-d')) }}"></div>
     <div class="col-md-5"><label class="form-label">Порядок</label><input type="number" min="0" class="form-control" name="sort_order" value="{{ old('sort_order',$editing->sort_order ?? 0) }}"></div>
     <div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" rows="4" name="description">{{ old('description',$editing->description ?? '') }}</textarea></div>
     <div class="col-12"><label class="form-label">PDF-файл</label><input type="file" class="form-control" name="document_file" accept=".pdf,application/pdf" {{ $editing ? '' : 'required' }}>@if($editing)<div class="small text-white-50 mt-1">Текущий файл: {{ $editing->file_name }}</div>@endif</div>
     <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_published" value="1" {{ old('is_published',$editing->is_published ?? true) ? 'checked' : '' }}><label class="form-check-label">Опубликовать</label></div></div>
     <div class="col-12 d-flex gap-2 justify-content-end">@if($editing)<a href="{{ route('admin.documents') }}" class="btn btn-ghost">Отмена</a>@endif<button class="btn btn-neon">{{ $editing ? 'Сохранить' : 'Добавить документ' }}</button></div>
    </div>
   </form>
  </div>

  <div class="col-xl-7">
   <div class="glass-card p-4"><h3 class="mb-4">Документы</h3><div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Документ</th><th>Дата</th><th>Статус</th><th></th></tr></thead><tbody>
   @forelse($documents as $doc)
    <tr><td><strong>{{ $doc->title }}</strong><div class="small text-white-50">{{ $doc->category ?: 'Без категории' }} @if($doc->document_number) · № {{ $doc->document_number }} @endif</div></td><td>{{ optional($doc->document_date)->format('d.m.Y') ?: '—' }}</td><td>{{ $doc->is_published ? 'Опубликован' : 'Скрыт' }}</td><td class="text-end"><div class="d-flex gap-2 justify-content-end flex-wrap"><a href="{{ $doc->file_url }}" target="_blank" class="btn btn-sm btn-ghost">PDF</a><a href="{{ route('admin.documents.edit',$doc) }}" class="btn btn-sm btn-ghost">Редактировать</a><form method="post" action="{{ route('admin.documents.delete',$doc) }}" onsubmit="return confirm('Удалить документ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form></div></td></tr>
   @empty<tr><td colspan="4" class="text-white-50">Нет документов.</td></tr>@endforelse
   </tbody></table></div></div>
  </div>
 </div>
</div></section>
@endsection
