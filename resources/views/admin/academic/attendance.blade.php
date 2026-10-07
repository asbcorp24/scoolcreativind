@extends('layouts.app')
@section('title','Посещаемость · '.(auth()->user()->is_admin ? 'Админ' : 'Преподаватель'))
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">{{ auth()->user()->is_admin ? 'Админ' : 'Кабинет преподавателя' }} / посещаемость</div>
  <h1 class="display-3 fw-bold">Посещаемость</h1>
  <p class="text-white-50 mb-0">Отчёт по группе за выбранный период и предмет.</p>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  <div class="d-flex gap-2 flex-wrap mb-4">
   <a class="btn btn-ghost" href="{{ route('admin.journal') }}">Журнал</a>
   <a class="btn btn-neon" href="{{ route('admin.attendance') }}">Посещаемость</a>
   <a class="btn btn-ghost" href="{{ route('admin.homework') }}">Домашние задания</a>
   <a class="btn btn-ghost" href="{{ route('admin.schedule') }}">Расписание</a>
  </div>

  <form method="get" class="glass-card p-4 mb-4">
   <div class="row g-3 align-items-end">
    <div class="col-lg-4">
     <label class="form-label">Группа</label>
     <select class="form-select" name="group_id" required onchange="this.form.submit()">
      <option value="">Выберите группу</option>
      @foreach($groups as $g)
       <option value="{{ $g->id }}" @selected(optional($group)->id===$g->id)>{{ $g->name }} · {{ $g->study_year }} год</option>
      @endforeach
     </select>
    </div>
    <div class="col-lg-3">
     <label class="form-label">Предмет</label>
     <select class="form-select" name="subject_id" {{ $group ? '' : 'disabled' }}>
      <option value="">Все предметы</option>
      @foreach($subjects as $subject)
       <option value="{{ $subject->id }}" @selected((int)$subjectId===$subject->id)>{{ $subject->title }}</option>
      @endforeach
     </select>
    </div>
    <div class="col-md-2">
     <label class="form-label">С даты</label>
     <input type="date" class="form-control" name="date_from" value="{{ $dateFrom }}">
    </div>
    <div class="col-md-2">
     <label class="form-label">По дату</label>
     <input type="date" class="form-control" name="date_to" value="{{ $dateTo }}">
    </div>
    <div class="col-md-1 d-grid">
     <button class="btn btn-neon">OK</button>
    </div>
   </div>
  </form>

  @if($group)
   <div class="row g-3 mb-4">
    <div class="col-6 col-lg"><div class="glass-card p-3 h-100"><div class="small text-white-50">Всего отметок</div><strong class="fs-3">{{ $summary['total'] }}</strong></div></div>
    <div class="col-6 col-lg"><div class="glass-card p-3 h-100"><div class="small text-white-50">Присутствовал</div><strong class="fs-3 text-success">{{ $summary['present'] }}</strong></div></div>
    <div class="col-6 col-lg"><div class="glass-card p-3 h-100"><div class="small text-white-50">Опоздал</div><strong class="fs-3 text-warning">{{ $summary['late'] }}</strong></div></div>
    <div class="col-6 col-lg"><div class="glass-card p-3 h-100"><div class="small text-white-50">Отсутствовал</div><strong class="fs-3 text-danger">{{ $summary['absent'] }}</strong></div></div>
    <div class="col-6 col-lg"><div class="glass-card p-3 h-100"><div class="small text-white-50">Уваж. причина</div><strong class="fs-3">{{ $summary['excused'] }}</strong></div></div>
    <div class="col-6 col-lg"><div class="glass-card p-3 h-100"><div class="small text-white-50">Посещаемость</div><strong class="fs-3">{{ $summary['rate'] !== null ? $summary['rate'].'%' : '—' }}</strong></div></div>
   </div>

   <div class="glass-card p-4">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
     <div>
      <div class="eyebrow">Группа</div>
      <h3 class="mb-0">{{ $group->name }}</h3>
     </div>
     <div class="small text-white-50">{{ date('d.m.Y',strtotime($dateFrom)) }} — {{ date('d.m.Y',strtotime($dateTo)) }}</div>
    </div>

    <div class="table-responsive">
     <table class="table admin-table align-middle">
      <thead>
       <tr>
        <th>Ученик</th>
        <th>Всего</th>
        <th>Был</th>
        <th>Опоздал</th>
        <th>Отсутствовал</th>
        <th>Уваж.</th>
        <th>Посещаемость</th>
       </tr>
      </thead>
      <tbody>
       @forelse($rows as $row)
        <tr>
         <td><strong>{{ $row['student']->name }}</strong></td>
         <td>{{ $row['total'] }}</td>
         <td class="text-success">{{ $row['present'] }}</td>
         <td class="text-warning">{{ $row['late'] }}</td>
         <td class="text-danger">{{ $row['absent'] }}</td>
         <td>{{ $row['excused'] }}</td>
         <td><strong>{{ $row['rate'] !== null ? $row['rate'].'%' : '—' }}</strong></td>
        </tr>
       @empty
        <tr><td colspan="7" class="text-white-50">В этой группе нет учеников.</td></tr>
       @endforelse
      </tbody>
     </table>
    </div>
   </div>
  @else
   <div class="glass-card p-4 text-white-50">Выберите группу, чтобы построить отчёт по посещаемости.</div>
  @endif
 </div>
</section>
@endsection
