@extends('layouts.app')
@section('title','Новость · Админ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Админ / новости</div><h1 class="display-3 fw-bold">{{ $post?'Редактировать новость':'Новая новость' }}</h1></div></section>

<section class="pb-5">
 <div class="container">
  <form class="form-shell" method="post" enctype="multipart/form-data" action="{{ route('admin.news.save',$post) }}">@csrf
   <div class="mb-3"><label class="form-label">Заголовок</label><input class="form-control" name="title" value="{{ old('title',$post->title ?? '') }}" required></div>
   <div class="mb-3"><label class="form-label">Slug</label><input class="form-control" name="slug" value="{{ old('slug',$post->slug ?? '') }}"></div>
   <div class="mb-3"><label class="form-label">Краткое описание</label><textarea class="form-control" rows="3" name="excerpt">{{ old('excerpt',$post->excerpt ?? '') }}</textarea></div>
   <div class="mb-3"><label class="form-label">Текст</label><textarea class="form-control" rows="12" name="body" required>{{ old('body',$post->body ?? '') }}</textarea></div>

   <div class="glass-card p-4 mb-4">
    <h3 class="mb-3">Главное изображение</h3>
    @if($post?->cover_url)
      <img src="{{ $post->cover_url }}" class="w-100 rounded-4 mb-3" style="max-height:360px;object-fit:cover" alt="">
    @endif
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Загрузить изображение</label><input type="file" class="form-control" name="cover_file" accept="image/jpeg,image/png,image/webp"></div>
      <div class="col-md-6"><label class="form-label">Или URL изображения</label><input class="form-control" name="cover_url" value="{{ old('cover_url', preg_match('~^(https?:)?//~i',$post->cover ?? '') ? ($post->cover ?? '') : '') }}"></div>
    </div>
   </div>

   <div class="row g-3">
    <div class="col-md-4"><label class="form-label">Дата публикации</label><input type="datetime-local" class="form-control" name="published_at" value="{{ old('published_at',isset($post)&&$post->published_at?$post->published_at->format('Y-m-d\TH:i'):'') }}"></div>
    <div class="col-md-2 d-flex align-items-end"><div class="form-check mb-2"><input type="hidden" name="is_published" value="0"><input class="form-check-input" type="checkbox" name="is_published" value="1" @checked(old('is_published',$post->is_published ?? true))><label class="form-check-label">Опубликовать</label></div></div>
   </div>

   <div class="text-end mt-4"><button class="btn btn-neon btn-lg">Сохранить новость</button></div>
  </form>

  @if($post)
  <div class="row g-4 mt-4">
   <div class="col-lg-5">
    <form method="post" enctype="multipart/form-data" action="{{ route('admin.news.media.add',$post) }}" class="glass-card p-4">@csrf
      <div class="eyebrow">Дополнительные материалы</div>
      <h3 class="mt-2">Добавить медиа</h3>
      <div class="row g-3 mt-1">
       <div class="col-md-6"><label class="form-label">Тип</label><select class="form-select" name="type" required><option value="image">Изображение</option><option value="video">Видео</option><option value="audio">Аудио</option><option value="file">Файл</option><option value="link">Ссылка</option></select></div>
       <div class="col-md-6"><label class="form-label">Порядок</label><input type="number" min="0" class="form-control" name="sort_order" value="0"></div>
       <div class="col-12"><label class="form-label">Название / подпись</label><input class="form-control" name="title"></div>
       <div class="col-12"><label class="form-label">Файл</label><input type="file" class="form-control" name="file"><div class="form-text text-white-50">Фото: JPG/PNG/WebP/GIF · видео: MP4/WebM · аудио: MP3/WAV/OGG/M4A/AAC · документы: PDF/DOCX/XLSX/PPTX/ZIP.</div></div>
       <div class="col-12"><label class="form-label">Или ссылка</label><input type="url" class="form-control" name="url" placeholder="https://..."></div>
       <div class="col-12 text-end"><button class="btn btn-neon">Добавить медиа</button></div>
      </div>
    </form>
   </div>

   <div class="col-lg-7">
    <div class="glass-card p-4">
      <div class="d-flex justify-content-between align-items-center mb-3"><h3 class="mb-0">Медиа новости</h3><span class="badge-soft">{{ $post->media->count() }}</span></div>
      <div class="d-grid gap-3">
      @forelse($post->media as $media)
       <div class="d-flex align-items-center justify-content-between gap-3 p-3 rounded-3" style="background:rgba(255,255,255,.025)">
        <div class="min-w-0">
          <div class="small text-white-50">{{ strtoupper($media->type) }} · порядок {{ $media->sort_order }}</div>
          <strong>{{ $media->title ?: ($media->file_name ?: $media->url) }}</strong>
          @if($media->human_file_size)<div class="small text-white-50">{{ $media->human_file_size }}</div>@endif
        </div>
        <div class="d-flex gap-2 flex-shrink-0">
          @if($media->display_url)<a class="btn btn-sm btn-ghost" href="{{ $media->display_url }}" target="_blank">Открыть</a>@endif
          <form method="post" action="{{ route('admin.news.media.delete',$media) }}" onsubmit="return confirm('Удалить медиа?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form>
        </div>
       </div>
      @empty
       <div class="text-white-50">Дополнительных медиа пока нет.</div>
      @endforelse
      </div>
    </div>
   </div>
  </div>
  @endif
 </div>
</section>
@endsection
