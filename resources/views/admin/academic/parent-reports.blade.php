@extends('layouts.app')
@section('title','Отчёты родителям · Админ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Админ / обучение</div>
  <h1 class="display-3 fw-bold">Отчёты родителям</h1>
  <p class="text-white-50 mb-0">Создавайте отдельные ссылки с посещаемостью и успеваемостью ученика по указанную дату.</p>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  @if(session('parent_report_url'))
   <div class="alert alert-success">
    <strong>Ссылка создана:</strong>
    <div class="d-flex gap-2 mt-2 flex-wrap">
     <input class="form-control" style="max-width:760px" value="{{ session('parent_report_url') }}" readonly onclick="this.select()">
     <a class="btn btn-neon" href="{{ session('parent_report_url') }}" target="_blank" rel="noopener">Открыть ↗</a>
    </div>
   </div>
  @endif

  @if($errors->any())
   <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <form class="glass-card p-4 mb-5" method="post" action="{{ route('admin.parent-reports.store') }}">
   @csrf
   <div class="row g-3 align-items-end">
    <div class="col-lg-7">
     <label class="form-label">Ученик</label>
     <select class="form-select" name="student_profile_id" required>
      <option value="">Выберите ученика</option>
      @foreach($students as $student)
       <option value="{{ $student->id }}" @selected(old('student_profile_id')==$student->id)>
        {{ $student->user->name ?? ('Ученик #'.$student->id) }}
        @if($student->class_name) · {{ $student->class_name }} @endif
       </option>
      @endforeach
     </select>
    </div>
    <div class="col-lg-3">
     <label class="form-label">Показывать данные по дату</label>
     <input type="date" class="form-control" name="report_until" value="{{ old('report_until',now()->toDateString()) }}" required>
    </div>
    <div class="col-lg-2 d-grid">
     <button class="btn btn-neon">Сгенерировать</button>
    </div>
   </div>
   <div class="form-text text-white-50 mt-3">Родитель увидит только посещаемость и оценки этого ученика, не позже указанной даты.</div>
  </form>

  <div class="glass-card p-4">
   <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
    <h3 class="mb-0">Созданные ссылки</h3>
    <span class="badge-soft">{{ $links->total() }}</span>
   </div>

   <div class="table-responsive">
    <table class="table admin-table align-middle">
     <thead><tr><th>Ученик</th><th>Данные по</th><th>Статус</th><th>Последнее открытие</th><th class="text-end">Действия</th></tr></thead>
     <tbody>
      @forelse($links as $link)
       <tr>
        <td><strong>{{ $link->student->user->name ?? 'Ученик' }}</strong></td>
        <td>{{ $link->report_until->format('d.m.Y') }}</td>
        <td>
         @if($link->is_active)<span class="text-success">Активна</span>@else<span class="text-white-50">Отозвана</span>@endif
        </td>
        <td>{{ $link->last_opened_at ? $link->last_opened_at->format('d.m.Y H:i') : 'Ещё не открывали' }}</td>
        <td class="text-end">
         <div class="d-flex gap-2 justify-content-end flex-wrap">
          @if($link->is_active)
           <a class="btn btn-sm btn-ghost" href="{{ route('parent-report.show',$link->token) }}" target="_blank" rel="noopener">Открыть ↗</a>
           <button type="button" class="btn btn-sm btn-ghost" onclick="navigator.clipboard.writeText('{{ route('parent-report.show',$link->token) }}')">Копировать</button>
           <form method="post" action="{{ route('admin.parent-reports.revoke',$link) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-danger">Отозвать</button></form>
          @else
           <form method="post" action="{{ route('admin.parent-reports.regenerate',$link) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-neon">Новая ссылка</button></form>
          @endif
         </div>
        </td>
       </tr>
      @empty
       <tr><td colspan="5" class="text-white-50">Ссылки ещё не создавались.</td></tr>
      @endforelse
     </tbody>
    </table>
   </div>

   <div class="mt-4">{{ $links->links() }}</div>
  </div>
 </div>
</section>
@endsection
