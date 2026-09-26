@extends('layouts.app')
@section('title',($page?'Редактирование':'Новая страница').' · Админ')
@push('head')
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
@endpush
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">CMS / страница</div><h1 class="display-3 fw-bold">{{ $page ? 'Редактировать страницу' : 'Новая страница' }}</h1></div></section>

<section class="pb-5"><div class="container">
 <form method="post" enctype="multipart/form-data" action="{{ route('admin.pages.save',$page) }}" class="glass-card p-4 p-lg-5">@csrf
  <div class="row g-3">
   <div class="col-md-8"><label class="form-label">Название страницы</label><input class="form-control" name="title" value="{{ old('title',$page->title ?? '') }}" required></div>
   <div class="col-md-4"><label class="form-label">Slug</label><input class="form-control" name="slug" value="{{ old('slug',$page->slug ?? '') }}" placeholder="about-school"></div>
   <div class="col-md-6"><label class="form-label">Родительский раздел</label><select class="form-select" name="parent_id"><option value="">Верхний уровень</option>@foreach($parents as $parent)<option value="{{ $parent->id }}" @selected(old('parent_id',$page->parent_id ?? null)==$parent->id)>{{ $parent->title }}</option>@endforeach</select></div>
   <div class="col-md-6"><label class="form-label">Название в меню</label><input class="form-control" name="menu_title" value="{{ old('menu_title',$page->menu_title ?? '') }}" placeholder="Если отличается от заголовка"></div>
   <div class="col-12"><label class="form-label">Подзаголовок</label><input class="form-control" name="subtitle" value="{{ old('subtitle',$page->subtitle ?? '') }}"></div>

   <div class="col-12">
    <label class="form-label">Содержимое страницы</label>
    <div id="pageEditor" class="cms-editor">{!! old('body_html',$page->body_html ?? '') !!}</div>
    <textarea name="body_html" id="pageEditorValue" hidden>{{ old('body_html',$page->body_html ?? '') }}</textarea>
   </div>

   <div class="col-md-8"><label class="form-label">Обложка</label><input type="file" class="form-control" name="cover" accept="image/jpeg,image/png,image/webp">@if($page?->cover_url)<img src="{{ $page->cover_url }}" class="admin-clip-cover-preview mt-3" alt="">@endif</div>
   <div class="col-md-4"><label class="form-label">Порядок</label><input type="number" class="form-control" name="sort_order" min="0" value="{{ old('sort_order',$page->sort_order ?? 0) }}"></div>

   <div class="col-12">
    <div class="cms-publish-panel">
      <div class="cms-publish-head">
        <div>
          <div class="eyebrow">Публикация и меню</div>
          <h3>Где показывать эту страницу</h3>
          <p>Здесь отдельно включается публикация страницы и её отображение в меню сайта.</p>
        </div>
      </div>

      <label class="cms-switch-card">
        <div>
          <strong>Показывать в меню сайта</strong>
          <small>Добавляет раздел в публичное меню. Для подраздела он появится под родительским разделом.</small>
        </div>
        <span class="cms-switch">
          <input type="hidden" name="show_in_menu" value="0">
          <input type="checkbox" name="show_in_menu" value="1" @checked(old('show_in_menu',$page->show_in_menu ?? false))>
          <i></i>
        </span>
      </label>

      <label class="cms-switch-card">
        <div>
          <strong>Опубликовать страницу</strong>
          <small>Если выключено, посетители не увидят страницу даже при включённом пункте меню.</small>
        </div>
        <span class="cms-switch">
          <input type="hidden" name="is_published" value="0">
          <input type="checkbox" name="is_published" value="1" @checked(old('is_published',$page->is_published ?? true))>
          <i></i>
        </span>
      </label>
    </div>
   </div>

   <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-3 mt-4">
    <div class="text-white-50 small">URL страницы: <strong>/page/{{ $page->slug ?? '...' }}</strong></div>
    <button class="btn btn-neon btn-lg">Сохранить страницу</button>
   </div>
  </div>
 </form>

 @if($page)
 <div class="row g-4 mt-4">
  <div class="col-lg-5">
   <form method="post" enctype="multipart/form-data" action="{{ route('admin.pages.media.add',$page) }}" class="glass-card p-4">@csrf
    <div class="eyebrow">Медиа</div><h3 class="mt-2">Добавить материал</h3>
    <div class="row g-3 mt-1">
     <div class="col-md-6"><label class="form-label">Тип</label><select class="form-select" name="type"><option value="image">Изображение</option><option value="video">Видео</option><option value="audio">Аудио</option><option value="file">Файл</option><option value="link">Ссылка</option></select></div>
     <div class="col-md-6"><label class="form-label">Порядок</label><input type="number" class="form-control" name="sort_order" min="0" value="0"></div>
     <div class="col-12"><label class="form-label">Название / подпись</label><input class="form-control" name="title"></div>
     <div class="col-12"><label class="form-label">Файл</label><input type="file" class="form-control" name="file"></div>
     <div class="col-12"><label class="form-label">Или ссылка</label><input type="url" class="form-control" name="url" placeholder="https://..."></div>
     <div class="col-12 text-end"><button class="btn btn-neon">Добавить</button></div>
    </div>
   </form>
  </div>
  <div class="col-lg-7">
   <div class="glass-card p-4">
    <h3>Медиа страницы</h3>
    <div class="d-grid gap-3 mt-3">
    @forelse($page->media as $media)
     <div class="d-flex justify-content-between align-items-center gap-3 p-3 rounded-3" style="background:rgba(255,255,255,.025)">
      <div><div class="small text-white-50">{{ strtoupper($media->type) }} · {{ $media->sort_order }}</div><strong>{{ $media->title ?: ($media->file_name ?: $media->url) }}</strong></div>
      <div class="d-flex gap-2">@if($media->display_url)<a href="{{ $media->display_url }}" target="_blank" class="btn btn-sm btn-ghost">Открыть</a>@endif<form method="post" action="{{ route('admin.pages.media.delete',$media) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form></div>
     </div>
    @empty<div class="text-white-50">Медиа ещё не добавлено.</div>@endforelse
    </div>
   </div>
  </div>
 </div>
 @endif
</div></section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const editorEl=document.getElementById('pageEditor');
 const value=document.getElementById('pageEditorValue');
 if(!editorEl||!value||!window.Quill)return;
 const quill=new Quill(editorEl,{
   theme:'snow',
   modules:{toolbar:[
    [{header:[2,3,4,false]}],
    ['bold','italic','underline','strike'],
    [{list:'ordered'},{list:'bullet'}],
    ['blockquote','link','image'],
    [{align:[]}],
    ['clean']
   ]}
 });

 const toolbar=quill.getModule('toolbar');
 toolbar.addHandler('image',()=>{
   const input=document.createElement('input');
   input.type='file';
   input.accept='image/jpeg,image/png,image/webp,image/gif';
   input.click();
   input.onchange=async()=>{
     const file=input.files?.[0];
     if(!file)return;
     const fd=new FormData();
     fd.append('image',file);
     fd.append('_token',document.querySelector('meta[name="csrf-token"]')?.content||'');
     const res=await fetch('{{ route('admin.pages.editor-image') }}',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});
     if(!res.ok){
       alert('Не удалось загрузить изображение.');
       return;
     }
     const data=await res.json();
     const range=quill.getSelection(true);
     quill.insertEmbed(range?.index ?? quill.getLength(),'image',data.url,'user');
   };
 });

 editorEl.closest('form')?.addEventListener('submit',()=>{value.value=quill.root.innerHTML;});
});
</script>
@endpush
