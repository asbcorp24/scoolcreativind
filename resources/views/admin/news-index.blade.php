@extends('layouts.app')
@section('title','Новости · Админ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Админ / контент</div>
  <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
   <div>
    <h1 class="display-3 fw-bold m-0">Новости</h1>
    <p class="text-white-50 mt-2 mb-0">Список всех новостей с редактированием, публикацией и медиагалереей.</p>
   </div>
   <a class="btn btn-neon" href="{{ route('admin.news.create') }}">＋ Новая новость</a>
  </div>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  <form method="get" class="glass-card p-3 mb-4">
   <div class="row g-3 align-items-end">
    <div class="col-lg-7">
     <label class="form-label">Поиск</label>
     <input class="form-control" name="q" value="{{ request('q') }}" placeholder="Название, краткое описание или slug">
    </div>
    <div class="col-md-3">
     <label class="form-label">Статус</label>
     <select class="form-select" name="status">
      <option value="">Все</option>
      <option value="published" @selected(request('status')==='published')>Опубликованные</option>
      <option value="draft" @selected(request('status')==='draft')>Черновики</option>
     </select>
    </div>
    <div class="col-md-2 d-grid">
     <button class="btn btn-ghost">Фильтр</button>
    </div>
   </div>
  </form>

  <div class="glass-card p-4">
   <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Все новости</h3>
    <span class="badge-soft">{{ $posts->total() }}</span>
   </div>

   <div class="table-responsive">
    <table class="table admin-table align-middle">
     <thead>
      <tr>
       <th style="width:96px">Обложка</th>
       <th>Новость</th>
       <th>Дата</th>
       <th>Медиа</th>
       <th>Статус</th>
       <th class="text-end">Действия</th>
      </tr>
     </thead>
     <tbody>
      @forelse($posts as $post)
       <tr>
        <td>
         @if($post->cover_url)
          <img src="{{ $post->cover_url }}" alt="" style="width:72px;height:52px;object-fit:cover;border-radius:10px">
         @else
          <div style="width:72px;height:52px;border:1px solid rgba(255,255,255,.08);border-radius:10px;background:rgba(255,255,255,.025)"></div>
         @endif
        </td>
        <td>
         <strong>{{ $post->title }}</strong>
         <div class="small text-white-50">/{{ $post->slug }}</div>
         @if($post->excerpt)<div class="small text-white-50 mt-1">{{ \Illuminate\Support\Str::limit($post->excerpt,90) }}</div>@endif
        </td>
        <td>
         @if($post->published_at)
          {{ $post->published_at->format('d.m.Y') }}
          <div class="small text-white-50">{{ $post->published_at->format('H:i') }}</div>
         @else
          <span class="text-white-50">Без даты</span>
         @endif
        </td>
        <td><span class="badge-soft">{{ $post->media_count }}</span></td>
        <td>
         @if($post->is_published)
          <span class="text-success">Опубликована</span>
         @else
          <span class="text-warning">Черновик</span>
         @endif
        </td>
        <td class="text-end">
         <div class="d-flex gap-2 justify-content-end flex-wrap">
          <a class="btn btn-sm btn-neon" href="{{ route('admin.news.edit',$post) }}">Редактировать</a>
          @if($post->is_published)
           <a class="btn btn-sm btn-ghost" href="{{ route('news.show',$post) }}" target="_blank" rel="noopener">Открыть ↗</a>
          @endif
          <form method="post" action="{{ route('admin.news.toggle',$post) }}">@csrf @method('PATCH')
           <button class="btn btn-sm btn-ghost">{{ $post->is_published ? 'Скрыть' : 'Опубликовать' }}</button>
          </form>
          <form method="post" action="{{ route('admin.news.delete',$post) }}" onsubmit="return confirm('Удалить новость и все её медиа?')">@csrf @method('DELETE')
           <button class="btn btn-sm btn-outline-danger">Удалить</button>
          </form>
         </div>
        </td>
       </tr>
      @empty
       <tr><td colspan="6" class="text-white-50">Новостей пока нет.</td></tr>
      @endforelse
     </tbody>
    </table>
   </div>

   <div class="mt-4">{{ $posts->links() }}</div>
  </div>
 </div>
</section>
@endsection
