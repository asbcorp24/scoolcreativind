@extends('layouts.app')
@section('title','Конкурсы и достижения · Админ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Админ / конкурсы</div><div class="d-flex justify-content-between align-items-end flex-wrap gap-3"><h1 class="display-3 fw-bold m-0">Конкурсы и достижения</h1><a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">← Админка</a></div></div></section>
<section class="pb-5"><div class="container">
<div class="row g-5">
<div class="col-xl-6"><form class="glass-card p-4" method="post" action="{{ route('admin.competitions.save') }}">@csrf
<h3 class="mb-4">Добавить конкурс</h3>
<div class="row g-3">
<div class="col-12"><label class="form-label">Название</label><input class="form-control" name="title" required></div>
<div class="col-12"><label class="form-label">Организатор</label><input class="form-control" name="organizer"></div>
<div class="col-md-6"><label class="form-label">Начало</label><input type="date" class="form-control" name="starts_on"></div>
<div class="col-md-6"><label class="form-label">Окончание</label><input type="date" class="form-control" name="ends_on"></div>
<div class="col-12"><label class="form-label">Место</label><input class="form-control" name="location"></div>
<div class="col-12"><label class="form-label">Ссылка</label><input class="form-control" name="url"></div>
<div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" rows="5" name="description"></textarea></div>
<div class="col-12"><label class="form-label">Какие документы должен загрузить участник</label><textarea class="form-control" rows="6" name="required_documents" placeholder="Согласие на обработку персональных данных&#10;Заявка участника&#10;Скан паспорта / свидетельства&#10;Согласие родителя"></textarea><div class="form-text text-white-50">Каждый документ — с новой строки. После записи участник увидит этот список в личном кабинете.</div></div>
<div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_published" value="1" checked><label class="form-check-label">Опубликовать</label></div></div>
<div class="col-md-6 text-end"><button class="btn btn-neon">Добавить конкурс</button></div>
</div></form></div>
<div class="col-xl-6"><form class="glass-card p-4" enctype="multipart/form-data" method="post" action="{{ route('admin.achievements.save') }}">@csrf
<h3 class="mb-4">Добавить достижение</h3>
<div class="row g-3">
<div class="col-12"><label class="form-label">Ученик</label><select class="form-select" name="student_profile_id"><option value="">Общее достижение школы</option>@foreach($profiles as $p)<option value="{{ $p->id }}">{{ $p->user->name }}</option>@endforeach</select></div>
<div class="col-12"><label class="form-label">Конкурс</label><select class="form-select" name="competition_id"><option value="">Без конкурса</option>@foreach($competitions as $c)<option value="{{ $c->id }}">{{ $c->title }}</option>@endforeach</select></div>
<div class="col-12"><label class="form-label">Название достижения</label><input class="form-control" name="title" required></div>
<div class="col-md-6"><label class="form-label">Результат</label><input class="form-control" name="result" placeholder="1 место / лауреат / финалист"></div>
<div class="col-md-6"><label class="form-label">Уровень</label><input class="form-control" name="level" placeholder="городской / региональный / всероссийский"></div>
<div class="col-md-6"><label class="form-label">Дата</label><input type="date" class="form-control" name="awarded_at"></div>
<div class="col-md-6"><label class="form-label">Фото диплома</label><input type="file" class="form-control" name="image" accept="image/*"></div>
<div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" rows="4" name="description"></textarea></div>
<div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_public" value="1" checked><label class="form-check-label">Публично</label></div></div>
<div class="col-md-6 text-end"><button class="btn btn-neon">Добавить достижение</button></div>
</div></form></div>
</div>

<div class="glass-card p-4 mt-5"><h3 class="mb-4">Конкурсы</h3><div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Название</th><th>Период</th><th>Организатор</th><th></th></tr></thead><tbody>
@forelse($competitions as $c)<tr><td><strong>{{ $c->title }}</strong><div class="small text-white-50">Участников: {{ $c->registrations_count }}</div></td><td>{{ optional($c->starts_on)->format('d.m.Y') }} @if($c->ends_on) — {{ $c->ends_on->format('d.m.Y') }} @endif</td><td>{{ $c->organizer ?: '—' }}</td><td class="text-end"><form method="post" action="{{ route('admin.competitions.delete',$c) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form></td></tr>
@if($c->registrations->count())<tr><td colspan="4"><div class="p-3 rounded-3" style="background:rgba(255,255,255,.025)"><strong>Участники</strong><div class="table-responsive mt-2"><table class="table admin-table table-sm"><thead><tr><th>Ученик</th><th>Статус</th><th>Документы</th><th>Работа</th></tr></thead><tbody>
@foreach($c->registrations as $r)
 @php($requirements=collect($c->required_documents_json ?: []))
 @php($docs=$r->documents->keyBy('document_key'))
 <tr>
  <td>{{ $r->user->name }}<div class="small text-white-50">{{ $r->user->email }}</div></td>
  <td>{{ ['registered'=>'Записан','submitted'=>'Работа отправлена','reviewed'=>'Проверено','cancelled'=>'Отменено'][$r->status] ?? $r->status }}</td>
  <td style="min-width:300px">
   @if($requirements->count())
    <div class="admin-doc-list">
    @foreach($requirements as $req)
     @php($doc=$docs->get($req['key']))
     <div class="admin-doc-row">
      <span>{{ $req['label'] }}</span>
      @if($doc)
       <a href="{{ $doc->file_url }}" target="_blank" class="badge-soft">Открыть ↗</a>
      @else
       <span class="small text-warning">Нет файла</span>
      @endif
     </div>
    @endforeach
    </div>
   @else
    <span class="small text-white-50">Документы не требуются</span>
   @endif
  </td>
  <td>@if($r->submission_url)<a href="{{ $r->submission_url }}" target="_blank">Ссылка ↗</a> @endif @if($r->file_url)<a href="{{ $r->file_url }}" target="_blank">Файл ↗</a>@endif @if($r->submission_text)<div class="small text-white-50">{{ $r->submission_text }}</div>@endif</td>
 </tr>
@endforeach
</tbody></table></div></div></td></tr>@endif
@empty<tr><td colspan="4">Пока пусто.</td></tr>@endforelse
</tbody></table></div></div>

<div class="glass-card p-4 mt-4"><h3 class="mb-4">Достижения</h3><div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Ученик</th><th>Достижение</th><th>Результат</th><th>Дата</th><th></th></tr></thead><tbody>
@forelse($achievements as $a)<tr><td>{{ $a->student->user->name ?? 'Школа' }}</td><td><strong>{{ $a->title }}</strong><div class="small text-white-50">{{ $a->competition->title ?? $a->level }}</div></td><td>{{ $a->result ?: '—' }}</td><td>{{ optional($a->awarded_at)->format('d.m.Y') }}</td><td class="text-end"><form method="post" action="{{ route('admin.achievements.delete',$a) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form></td></tr>@empty<tr><td colspan="5">Пока пусто.</td></tr>@endforelse
</tbody></table></div></div>
</div></section>
@endsection
