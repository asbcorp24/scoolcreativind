@extends('layouts.app')
@section('title','История действий · Админ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Система / аудит</div>
  <h1 class="display-3 fw-bold">История действий</h1>
  <p class="text-white-50 mb-0">Кто, когда и что изменял в учебной части сайта.</p>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  <form method="get" class="glass-card p-4 mb-4">
   <div class="row g-3 align-items-end">
    <div class="col-md-3"><label class="form-label">Действие</label><input class="form-control" name="action" value="{{ request('action') }}" placeholder="schedule, journal..."></div>
    <div class="col-md-3"><label class="form-label">Пользователь</label><select class="form-select" name="user_id"><option value="">Все</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected((int)request('user_id')===$u->id)>{{ $u->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">С даты</label><input type="date" class="form-control" name="date_from" value="{{ request('date_from') }}"></div>
    <div class="col-md-2"><label class="form-label">По дату</label><input type="date" class="form-control" name="date_to" value="{{ request('date_to') }}"></div>
    <div class="col-md-2 d-grid"><button class="btn btn-neon">Фильтр</button></div>
   </div>
  </form>

  <div class="glass-card p-4">
   <div class="table-responsive">
    <table class="table admin-table align-middle">
     <thead><tr><th>Время</th><th>Пользователь</th><th>Действие</th><th>Описание</th><th>Объект</th><th>IP</th></tr></thead>
     <tbody>
      @forelse($logs as $log)
       <tr>
        <td class="text-nowrap">{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
        <td>{{ $log->user->name ?? 'Система' }}</td>
        <td><code>{{ $log->action }}</code></td>
        <td>{{ $log->description }}</td>
        <td class="small text-white-50">{{ class_basename($log->entity_type) }}@if($log->entity_id) #{{ $log->entity_id }}@endif</td>
        <td class="small text-white-50">{{ $log->ip_address }}</td>
       </tr>
      @empty
       <tr><td colspan="6" class="text-white-50">Записей пока нет.</td></tr>
      @endforelse
     </tbody>
    </table>
   </div>
   @if($logs->hasPages())<div class="mt-4">{{ $logs->links() }}</div>@endif
  </div>
 </div>
</section>
@endsection
