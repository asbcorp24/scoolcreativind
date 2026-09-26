@extends('layouts.app')
@section('title','Сотрудничество · Админ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Админ / сотрудничество</div><h1 class="display-3 fw-bold">Сотрудничество</h1></div></section>
<section class="pb-5"><div class="container">
 <div class="row g-4">
  <div class="col-xl-4">
   <form method="post" enctype="multipart/form-data" action="{{ route('admin.cooperation.items.save') }}" class="glass-card p-4">@csrf
    <h3>Добавить материал</h3>
    <div class="row g-3 mt-1">
     <div class="col-12"><label class="form-label">Тип</label><select class="form-select" name="type"><option value="proposal">Предложение</option><option value="partner">Партнёр</option><option value="project">Совместный проект</option></select></div>
     <div class="col-12"><label class="form-label">Название</label><input class="form-control" name="title" required></div>
     <div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" rows="4" name="description"></textarea></div>
     <div class="col-12"><label class="form-label">Ссылка</label><input type="url" class="form-control" name="url"></div>
     <div class="col-12"><label class="form-label">Изображение / логотип</label><input type="file" class="form-control" name="image" accept="image/*"></div>
     <div class="col-md-6"><label class="form-label">Порядок</label><input type="number" min="0" class="form-control" name="sort_order" value="0"></div>
     <div class="col-md-6 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_published" value="1" checked><label class="form-check-label">Опубликован</label></div></div>
     <div class="col-12 text-end"><button class="btn btn-neon">Добавить</button></div>
    </div>
   </form>
  </div>
  <div class="col-xl-8">
   <div class="glass-card p-4">
    <h3>Материалы</h3>
    <div class="table-responsive mt-3"><table class="table admin-table align-middle"><thead><tr><th>Тип</th><th>Название</th><th>Статус</th><th></th></tr></thead><tbody>
    @forelse($items as $item)
     <tr><td>{{ ['proposal'=>'Предложение','partner'=>'Партнёр','project'=>'Проект'][$item->type] ?? $item->type }}</td><td><strong>{{ $item->title }}</strong></td><td>{{ $item->is_published?'Опубликован':'Скрыт' }}</td><td class="text-end"><form method="post" action="{{ route('admin.cooperation.items.delete',$item) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form></td></tr>
    @empty<tr><td colspan="4" class="text-white-50">Материалов пока нет.</td></tr>@endforelse
    </tbody></table></div>
   </div>
  </div>
 </div>

 <div class="glass-card p-4 mt-4">
  <div class="d-flex justify-content-between align-items-center"><h3 class="mb-0">Заявки</h3><span class="badge-soft">{{ $applications->count() }}</span></div>
  <div class="table-responsive mt-3"><table class="table admin-table align-middle"><thead><tr><th>Дата</th><th>Тип</th><th>Контакт</th><th>Организация</th><th>Сообщение</th><th>Статус</th><th></th></tr></thead><tbody>
  @forelse($applications as $a)
   <tr>
    <td>{{ $a->created_at->format('d.m.Y H:i') }}</td>
    <td>{{ ['partner'=>'Партнёр','curator'=>'Куратор','teacher'=>'Преподаватель','other'=>'Другое'][$a->role] ?? $a->role }}</td>
    <td><strong>{{ $a->name }}</strong><div class="small text-white-50">{{ $a->phone }} {{ $a->email }}</div></td>
    <td>{{ $a->organization ?: '—' }}</td>
    <td style="max-width:320px">{{ $a->message ?: '—' }}</td>
    <td><form method="post" action="{{ route('admin.cooperation.applications.update',$a) }}">@csrf @method('PATCH')<select class="form-select form-select-sm" name="status" onchange="this.form.submit()">@foreach(['new'=>'Новая','processing'=>'В работе','accepted'=>'Принята','rejected'=>'Отклонена'] as $k=>$v)<option value="{{ $k }}" @selected($a->status===$k)>{{ $v }}</option>@endforeach</select></form></td>
    <td><form method="post" action="{{ route('admin.cooperation.applications.delete',$a) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">×</button></form></td>
   </tr>
  @empty<tr><td colspan="7" class="text-white-50">Заявок пока нет.</td></tr>@endforelse
  </tbody></table></div>
 </div>
</div></section>
@endsection
