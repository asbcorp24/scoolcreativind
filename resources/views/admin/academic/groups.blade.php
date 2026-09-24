@extends('layouts.app')
@section('title','Учебные группы · Админ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Админ / учебные группы</div><div class="d-flex justify-content-between align-items-end flex-wrap gap-3"><h1 class="display-3 fw-bold m-0">Учебные группы</h1><a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">← Админка</a></div></div></section>
<section class="pb-5"><div class="container">
<form class="form-shell mb-5" method="post" action="{{ route('admin.groups.save') }}">@csrf
<div class="row g-3">
<div class="col-md-5"><label class="form-label">Название</label><input class="form-control" name="name" placeholder="Группа А" required></div>
<div class="col-md-2"><label class="form-label">Год обучения</label><select class="form-select" name="study_year"><option value="1">1 год</option><option value="2">2 год</option></select></div>
<div class="col-md-3"><label class="form-label">Код</label><input class="form-control" name="code" placeholder="Y1-A" required></div>
<div class="col-md-2 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked><label class="form-check-label">Активна</label></div></div>
<div class="col-md-6"><label class="form-label">Куратор</label><input class="form-control" name="curator_name"></div>
<div class="col-12 text-end"><button class="btn btn-neon">Создать группу</button></div>
</div></form>

@foreach($groups as $g)
<div class="glass-card p-4 mb-4">
 <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
  <div><div class="eyebrow">{{ $g->study_year }} год обучения · {{ $g->code }}</div><h3 class="display-6 fw-bold mt-2">{{ $g->name }}</h3><div class="text-white-50">{{ $g->curator_name }}</div></div>
  <form class="d-flex gap-2" method="post" action="{{ route('admin.groups.members.add',$g) }}">@csrf
   <select class="form-select" name="user_id">@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }} · {{ $u->email }}</option>@endforeach</select>
   <select class="form-select" name="role"><option value="student">Ученик</option><option value="teacher">Преподаватель</option></select>
   <button class="btn btn-ghost">Добавить</button>
  </form>
 </div>
 <div class="row g-4 mt-2">
  <div class="col-lg-6"><h5>Ученики</h5>@forelse($g->students as $u)<div class="d-flex justify-content-between py-2 border-bottom border-secondary-subtle"><span>{{ $u->name }}</span><form method="post" action="{{ route('admin.groups.members.remove',[$g,$u,'student']) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">×</button></form></div>@empty<div class="text-white-50">Нет учеников.</div>@endforelse</div>
  <div class="col-lg-6"><h5>Преподаватели</h5>@forelse($g->teachers as $u)<div class="d-flex justify-content-between py-2 border-bottom border-secondary-subtle"><span>{{ $u->name }}</span><form method="post" action="{{ route('admin.groups.members.remove',[$g,$u,'teacher']) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">×</button></form></div>@empty<div class="text-white-50">Нет преподавателей.</div>@endforelse</div>
 </div>
</div>
@endforeach
</div></section>
@endsection
