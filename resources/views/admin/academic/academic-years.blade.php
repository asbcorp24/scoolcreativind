@extends('layouts.app')
@section('title','Учебные годы · Админ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Система обучения</div>
  <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
   <div>
    <h1 class="display-3 fw-bold m-0">Учебные годы</h1>
    <p class="text-white-50 mt-2 mb-0">Разделяйте наборы, группы, расписание и отчётность по учебным годам.</p>
   </div>
   <a href="{{ route('admin.groups') }}" class="btn btn-ghost">Учебные группы</a>
  </div>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form method="post" action="{{ route('admin.academic-years.save') }}" class="form-shell mb-5">
   @csrf
   <div class="row g-3 align-items-end">
    <div class="col-md-3"><label class="form-label">Название</label><input class="form-control" name="name" placeholder="2027/2028" required></div>
    <div class="col-md-3"><label class="form-label">Начало</label><input type="date" class="form-control" name="starts_on" required></div>
    <div class="col-md-3"><label class="form-label">Окончание</label><input type="date" class="form-control" name="ends_on" required></div>
    <div class="col-md-2"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_current" value="1"><label class="form-check-label">Сделать текущим</label></div></div>
    <div class="col-md-1 d-grid"><button class="btn btn-neon">+</button></div>
   </div>
  </form>

  <div class="glass-card p-4">
   <div class="table-responsive">
    <table class="table admin-table align-middle">
     <thead><tr><th>Учебный год</th><th>Период</th><th>Группы</th><th>Статус</th><th></th></tr></thead>
     <tbody>
      @foreach($years as $year)
       <tr>
        <td><strong>{{ $year->name }}</strong></td>
        <td>{{ $year->starts_on->format('d.m.Y') }} — {{ $year->ends_on->format('d.m.Y') }}</td>
        <td>{{ $year->groups_count }}</td>
        <td>
         @if($year->is_current)<span class="badge-soft">Текущий</span>@endif
         @if($year->is_closed)<span class="badge bg-secondary">Архив</span>@elseif(!$year->is_current)<span class="text-white-50">Открыт</span>@endif
        </td>
        <td class="text-end">
         <div class="d-flex gap-2 justify-content-end">
          @if(!$year->is_current && !$year->is_closed)
           <form method="post" action="{{ route('admin.academic-years.current',$year) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-ghost">Сделать текущим</button></form>
          @endif
          <form method="post" action="{{ route('admin.academic-years.toggle-closed',$year) }}" onsubmit="return confirm('{{ $year->is_closed ? 'Открыть учебный год?' : 'Закрыть учебный год и отправить в архив?' }}')">@csrf @method('PATCH')
           <button class="btn btn-sm {{ $year->is_closed ? 'btn-ghost' : 'btn-outline-warning' }}">{{ $year->is_closed ? 'Открыть' : 'Закрыть год' }}</button>
          </form>
         </div>
        </td>
       </tr>
      @endforeach
     </tbody>
    </table>
   </div>
  </div>
 </div>
</section>
@endsection
