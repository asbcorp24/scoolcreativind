@extends('layouts.app')
@section('title','Викторины · Админ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Админ / викторины</div><h1 class="display-3 fw-bold">Викторины и сертификаты</h1></div></section>
<section class="pb-5"><div class="container">
@if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<form method="post" action="{{ route('admin.quizzes.save') }}" class="glass-card p-4 mb-5">@csrf
 <div class="row g-3">
  <div class="col-md-8"><label class="form-label">Название</label><input class="form-control" name="title" required></div>
  <div class="col-md-4"><label class="form-label">Проходной балл, %</label><input type="number" min="1" max="100" class="form-control" name="pass_score" value="70" required></div>
  <div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" name="description" rows="3"></textarea></div>
  <div class="col-12"><label class="form-label">Вопросы JSON</label><textarea class="form-control font-monospace" name="questions_json" rows="12" required>[
  {"question":"Что такое RGB?","options":{"a":"Цветовая модель","b":"Формат аудио","c":"Тип камеры"},"correct":"a"},
  {"question":"Что используется для 3D-сцен?","options":{"a":"GLB","b":"MP3","c":"CSV"},"correct":"a"}
]</textarea><div class="form-text text-white-50">Для каждого вопроса: question, options и correct.</div></div>
  <div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_published" value="1" checked><label class="form-check-label">Опубликовать</label></div></div>
  <div class="col-md-6 text-end"><button class="btn btn-neon">Создать викторину</button></div>
 </div>
</form>
<div class="glass-card p-4 mb-4"><h3>Викторины</h3><div class="table-responsive"><table class="table admin-table"><thead><tr><th>Название</th><th>Вопросов</th><th>Попыток</th><th>Статус</th><th></th></tr></thead><tbody>
@foreach($quizzes as $q)<tr><td><strong>{{ $q->title }}</strong></td><td>{{ count($q->questions_json ?? []) }}</td><td>{{ $q->attempts_count }}</td><td>{{ $q->is_published?'Опубликована':'Черновик' }}</td><td><form method="post" action="{{ route('admin.quizzes.delete',$q) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form></td></tr>@endforeach
</tbody></table></div></div>
<div class="glass-card p-4"><h3>Последние результаты</h3><div class="table-responsive"><table class="table admin-table"><thead><tr><th>Ученик</th><th>Викторина</th><th>Результат</th><th>Сертификат</th></tr></thead><tbody>
@foreach($attempts as $a)<tr><td>{{ $a->user->name }}</td><td>{{ $a->quiz->title }}</td><td>{{ $a->score }}%</td><td>@if($a->certificate_code)<a href="{{ route('quizzes.certificate',$a->certificate_code) }}" target="_blank">{{ $a->certificate_code }}</a>@else—@endif</td></tr>@endforeach
</tbody></table></div></div>
</div></section>
@endsection
