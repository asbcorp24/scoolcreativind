@extends('layouts.app')
@section('title','Медиабиблиотека · Админ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Админ / медиа</div>
  <h1 class="display-3 fw-bold">Медиабиблиотека</h1>
  <p class="text-white-50 col-lg-8">Все фото, 360° панорамы, видео, 3D-модели, аудио, файлы и ссылки сайта в одном каталоге.</p>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  <form method="get" class="glass-card p-3 mb-4">
   <div class="row g-3 align-items-end">
    <div class="col-lg-5"><label class="form-label">Поиск</label><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Название, подпись, имя файла"></div>
    <div class="col-md-3"><label class="form-label">Раздел</label><select class="form-select" name="section">
     <option value="">Все</option>
     <option value="studios" @selected(request('section')==='studios')>Студии</option>
     <option value="works" @selected(request('section')==='works')>Работы учеников</option>
     <option value="news" @selected(request('section')==='news')>Новости</option>
     <option value="pages" @selected(request('section')==='pages')>Страницы</option>
    </select></div>
    <div class="col-md-2"><label class="form-label">Тип</label><select class="form-select" name="type">
     <option value="">Все</option>
     @foreach(['photo'=>'Фото','panorama'=>'360°','video'=>'Видео','model'=>'3D','audio'=>'Аудио','file'=>'Файл','link'=>'Ссылка'] as $key=>$label)
      <option value="{{ $key }}" @selected(request('type')===$key)>{{ $label }}</option>
     @endforeach
    </select></div>
    <div class="col-md-2"><button class="btn btn-neon w-100">Фильтр</button></div>
   </div>
  </form>

  <div class="glass-card p-4">
   <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Материалы</h3><span class="badge-soft">{{ $items->total() }}</span>
   </div>
   <div class="table-responsive">
    <table class="table admin-table align-middle">
     <thead><tr><th>Тип</th><th>Материал</th><th>Используется в</th><th>Статус</th><th></th></tr></thead>
     <tbody>
     @forelse($items as $media)
      @php
       $owner=$media->attachable;
       $section=match($media->attachable_type){
        AppModelsStudio::class=>'Студия',
        AppModelsPortfolioItem::class=>'Работа',
        AppModelsNewsPost::class=>'Новость',
        AppModelsCustomPage::class=>'Страница',
        default=>'Раздел',
       };
       $ownerTitle=$owner?->title ?? 'Удалённый раздел';
      @endphp
      <tr>
       <td><span class="badge-soft">{{ ['photo'=>'Фото','panorama'=>'360°','video'=>'Видео','model'=>'3D','audio'=>'Аудио','file'=>'Файл','link'=>'Ссылка'][$media->type] ?? $media->type }}</span></td>
       <td><strong>{{ $media->title ?: ($media->file_name ?: 'Без названия') }}</strong><div class="small text-white-50">{{ $media->human_file_size ?: IlluminateSupportStr::limit($media->url,70) }}</div></td>
       <td><div class="small text-white-50">{{ $section }}</div><strong>{{ $ownerTitle }}</strong></td>
       <td><span class="{{ $media->is_visible ? 'text-success' : 'text-white-50' }}">{{ $media->is_visible ? 'На сайте' : 'Скрыто' }}</span>@if($media->is_featured)<div class="small text-info">Главное</div>@endif</td>
       <td class="text-end"><a class="btn btn-sm btn-ghost" href="{{ $media->display_url }}" target="_blank" rel="noopener">Открыть ↗</a></td>
      </tr>
     @empty
      <tr><td colspan="5" class="text-white-50">Медиа не найдено.</td></tr>
     @endforelse
     </tbody>
    </table>
   </div>
   <div class="mt-4">{{ $items->links() }}</div>
  </div>
 </div>
</section>
@endsection
