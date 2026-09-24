@extends('layouts.app')
@section('title','Предметы · Админ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Админ / предметы</div><h1 class="display-3 fw-bold">Предметы</h1></div></section>
<section class="pb-5"><div class="container">
<form class="form-shell mb-5" method="post" action="{{ route('admin.subjects.save') }}">@csrf<div class="row g-3"><div class="col-md-7"><label class="form-label">Название</label><input class="form-control" name="title" required></div><div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" name="description"></textarea></div><div class="col-12 text-end"><button class="btn btn-neon">Добавить предмет</button></div></div></form>
@foreach($groups as $g)<div class="glass-card p-4 mb-3"><div class="d-flex justify-content-between align-items-center gap-3 flex-wrap"><div><strong>{{ $g->name }}</strong><div class="small text-white-50">{{ $g->study_year }} год</div></div><form class="d-flex gap-2" method="post" action="{{ route('admin.groups.subjects.attach',$g) }}">@csrf<select class="form-select" name="subject_id">@foreach($subjects as $s)<option value="{{ $s->id }}">{{ $s->title }}</option>@endforeach</select><select class="form-select" name="teacher_id"><option value="">Без преподавателя</option>@foreach($teachers as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select><button class="btn btn-ghost">Закрепить</button></form></div></div>@endforeach
</div></section>
@endsection
