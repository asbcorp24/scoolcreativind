@extends('layouts.app')
@section('title','Страницы и разделы · Админ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">CMS / разделы</div><div class="d-flex justify-content-between align-items-end gap-3 flex-wrap"><h1 class="display-3 fw-bold mb-0">Страницы и разделы</h1><a href="{{ route('admin.pages.create') }}" class="btn btn-neon">+ Создать раздел</a></div></div></section>

<section class="pb-5"><div class="container">
 <div class="glass-card p-4">
  <div class="table-responsive">
   <table class="table admin-table align-middle">
    <thead><tr><th>Порядок</th><th>Название</th><th>Родитель</th><th>Меню</th><th>Статус</th><th></th></tr></thead>
    <tbody>
    @forelse($pages as $page)
     <tr>
      <td>{{ $page->sort_order }}</td>
      <td><strong>{{ $page->title }}</strong><div class="small text-white-50">/page/{{ $page->slug }}</div></td>
      <td>{{ $page->parent?->title ?: '—' }}</td>
      <td>{{ $page->show_in_menu ? 'Да' : 'Нет' }}</td>
      <td>{{ $page->is_published ? 'Опубликован' : 'Скрыт' }}</td>
      <td class="text-end"><div class="d-flex justify-content-end gap-2 flex-wrap">
       @if($page->is_published)<a href="{{ route('pages.show',$page) }}" target="_blank" class="btn btn-sm btn-ghost">Открыть</a>@endif
       <a href="{{ route('admin.pages.edit',$page) }}" class="btn btn-sm btn-ghost">Редактировать</a>
       <form method="post" action="{{ route('admin.pages.delete',$page) }}" onsubmit="return confirm('Удалить страницу?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form>
      </div></td>
     </tr>
    @empty
     <tr><td colspan="6" class="text-white-50">Пользовательских разделов ещё нет.</td></tr>
    @endforelse
    </tbody>
   </table>
  </div>
 </div>
</div></section>
@endsection
