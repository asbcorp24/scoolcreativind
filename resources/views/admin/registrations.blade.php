@extends('layouts.app')
@section('title','Активация пользователей · Админ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Админ / система</div>
  <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
   <div>
    <h1 class="display-3 fw-bold m-0">Активация пользователей</h1>
    <p class="text-white-50 mt-2 mb-0">Самостоятельные регистрации: назначьте роль и учебную группу перед активацией.</p>
   </div>
   <div class="d-flex gap-2 flex-wrap">
    <span class="badge-soft">Всего: {{ $users->total() }}</span>
    @if($pendingCount)<span class="badge bg-warning text-dark">Ожидают: {{ $pendingCount }}</span>@endif
   </div>
  </div>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())
   <div class="alert alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
  @endif

  <form method="get" class="glass-card p-3 mb-4">
   <div class="row g-3 align-items-end">
    <div class="col-lg-7">
     <label class="form-label">Поиск</label>
     <input class="form-control" name="q" value="{{ request('q') }}" placeholder="Имя, email или телефон">
    </div>
    <div class="col-lg-3">
     <label class="form-label">Статус</label>
     <select class="form-select" name="status">
      <option value="">Все</option>
      <option value="pending" @selected(request('status')==='pending')>Ожидает активации</option>
      <option value="active" @selected(request('status')==='active')>Активен</option>
      <option value="rejected" @selected(request('status')==='rejected')>Отклонён</option>
      <option value="blocked" @selected(request('status')==='blocked')>Заблокирован</option>
     </select>
    </div>
    <div class="col-lg-2 d-grid"><button class="btn btn-neon">Показать</button></div>
   </div>
  </form>

  <form id="bulk-registration-form" method="post" class="glass-card p-3 mb-4">
   @csrf
   <div id="bulkSelectedUsers"></div>
   <div class="row g-3 align-items-end">
    <div class="col-lg-5">
     <label class="form-label">Группа для выбранных</label>
     <select class="form-select" name="group_id" required>
      <option value="">Выберите группу</option>
      @foreach($groups as $group)
       <option value="{{ $group->id }}">{{ $group->name }}{{ $group->study_year ? ' · '.$group->study_year.' год' : '' }}</option>
      @endforeach
     </select>
    </div>
    <div class="col-lg-3">
     <label class="form-label">Роль</label>
     <select class="form-select" name="role" required>
      <option value="student">Ученик</option>
      <option value="teacher">Преподаватель</option>
     </select>
    </div>
    <div class="col-lg-4 d-flex gap-2 flex-wrap">
     <button class="btn btn-neon flex-grow-1" type="submit" formaction="{{ route('admin.registrations.bulk-activate') }}">
      Активировать выбранных
     </button>
     <button class="btn btn-outline-danger" type="submit"
      formaction="{{ route('admin.registrations.bulk-delete') }}"
      name="_method" value="DELETE" formnovalidate
      onclick="return confirm('Удалить выбранные регистрации? Это действие нельзя отменить.')">
      Удалить
     </button>
    </div>
   </div>
  </form>

  <div class="glass-card p-4">
   <div class="table-responsive">
    <table class="table admin-table align-middle">
     <thead>
      <tr>
       <th style="width:42px"><input class="form-check-input" type="checkbox" data-select-all-registrations></th>
       <th>Пользователь</th>
       <th>Регистрация</th>
       <th>Статус</th>
       <th>Группы</th>
       <th style="min-width:360px">Активация</th>
      </tr>
     </thead>
     <tbody>
      @forelse($users as $user)
       @php
        $application=$user->admissionApplications->first();
        $statusLabels=[
         'pending'=>'Ожидает активации',
         'active'=>'Активен',
         'rejected'=>'Отклонён',
         'blocked'=>'Заблокирован',
        ];
       @endphp
       <tr>
        <td><input class="form-check-input" type="checkbox" value="{{ $user->id }}" data-registration-checkbox></td>
        <td>
         <strong>{{ $user->name }}</strong>
         <div class="small text-white-50">{{ $user->email }}</div>
         <div class="small text-white-50">{{ $user->phone ?: 'Телефон не указан' }}</div>
        </td>
        <td>
         {{ optional($user->created_at)->format('d.m.Y H:i') }}
         @if($application)<div class="small text-white-50">Заявка #{{ $application->id }}</div>@endif
        </td>
        <td>
         @if($user->registration_status==='active')
          <span class="badge bg-success">{{ $statusLabels[$user->registration_status] }}</span>
         @elseif($user->registration_status==='pending')
          <span class="badge bg-warning text-dark">{{ $statusLabels[$user->registration_status] }}</span>
         @elseif($user->registration_status==='rejected')
          <span class="badge bg-danger">{{ $statusLabels[$user->registration_status] }}</span>
         @else
          <span class="badge-soft">{{ $statusLabels[$user->registration_status] ?? $user->registration_status }}</span>
         @endif
         @if($user->activated_at)
          <div class="small text-white-50 mt-1">{{ $user->activated_at->format('d.m.Y H:i') }}</div>
         @endif
        </td>
        <td>
         @forelse($user->studyGroups as $group)
          <div class="small">
           <strong>{{ $group->name }}</strong>
           <span class="text-white-50">· {{ $group->pivot->role==='teacher' ? 'преподаватель' : 'ученик' }}</span>
          </div>
         @empty
          <span class="text-warning">Не назначен</span>
         @endforelse
        </td>
        <td>
         <form method="post" action="{{ route('admin.registrations.activate',$user) }}" class="row g-2 align-items-end">
          @csrf
          <div class="col-7">
           <select class="form-select form-select-sm" name="group_id" required>
            <option value="">Группа</option>
            @foreach($groups as $group)
             <option value="{{ $group->id }}">{{ $group->name }}{{ $group->study_year ? ' · '.$group->study_year.' год' : '' }}</option>
            @endforeach
           </select>
          </div>
          <div class="col-5">
           <select class="form-select form-select-sm" name="role" required>
            <option value="student">Ученик</option>
            <option value="teacher">Преподаватель</option>
           </select>
          </div>
          <div class="col-12 d-flex gap-2 flex-wrap">
           <button class="btn btn-sm btn-neon">{{ $user->registration_status==='active' ? 'Назначить / активировать' : 'Активировать' }}</button>
         </form>

           @if($user->registration_status!=='rejected')
            <form method="post" action="{{ route('admin.registrations.reject',$user) }}" onsubmit="return confirm('Отклонить регистрацию?')">
             @csrf @method('PATCH')
             <button class="btn btn-sm btn-outline-warning">Отклонить</button>
            </form>
           @endif

           <form method="post" action="{{ route('admin.registrations.delete',$user) }}" onsubmit="return confirm('Удалить регистрацию {{ addslashes($user->email) }}?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger">Удалить</button>
           </form>
          </div>
        </td>
       </tr>
      @empty
       <tr><td colspan="6" class="text-white-50">Саморегистраций не найдено.</td></tr>
      @endforelse
     </tbody>
    </table>
   </div>

   <div class="mt-4">{{ $users->links() }}</div>
  </div>
 </div>
</section>

<script>
document.addEventListener('DOMContentLoaded',()=>{
 const all=document.querySelector('[data-select-all-registrations]');
 const boxes=[...document.querySelectorAll('[data-registration-checkbox]')];
 const hidden=document.getElementById('bulkSelectedUsers');

 const sync=()=>{
  hidden.innerHTML='';
  boxes.filter(box=>box.checked).forEach(box=>{
   const input=document.createElement('input');
   input.type='hidden';
   input.name='user_ids[]';
   input.value=box.value;
   hidden.appendChild(input);
  });
 };

 all?.addEventListener('change',()=>{
  boxes.forEach(box=>box.checked=all.checked);
  sync();
 });

 boxes.forEach(box=>box.addEventListener('change',sync));
 sync();
});
</script>
@endsection
