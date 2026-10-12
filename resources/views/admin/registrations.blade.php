@extends('layouts.app')
@section('title','Регистрации · Админ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Админ / система</div>
  <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
   <div>
    <h1 class="display-3 fw-bold m-0">Регистрации</h1>
    <p class="text-white-50 mt-2 mb-0">Пользователи, зарегистрировавшиеся самостоятельно через сайт.</p>
   </div>
   <span class="badge-soft">{{ $users->total() }}</span>
  </div>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  @if($errors->any())
   <div class="alert alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
  @endif

  <form method="get" class="glass-card p-3 mb-4">
   <div class="row g-3 align-items-end">
    <div class="col-md-10">
     <label class="form-label">Поиск</label>
     <input class="form-control" name="q" value="{{ request('q') }}" placeholder="Имя, email или телефон">
    </div>
    <div class="col-md-2 d-grid"><button class="btn btn-neon">Найти</button></div>
   </div>
  </form>

  <form method="post" action="{{ route('admin.registrations.bulk-delete') }}" onsubmit="return confirm('Удалить выбранные регистрации? Это действие нельзя отменить.')">
   @csrf @method('DELETE')

   <div class="glass-card p-4">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
     <div>
      <h3 class="mb-1">Саморегистрации</h3>
      <div class="small text-white-50">Учебные аккаунты с журналом, группами или работами автоматически защищены от удаления.</div>
     </div>
     <button class="btn btn-outline-danger" type="submit">Удалить выбранные</button>
    </div>

    <div class="table-responsive">
     <table class="table admin-table align-middle">
      <thead>
       <tr>
        <th style="width:42px"><input class="form-check-input" type="checkbox" data-select-all-registrations></th>
        <th>Пользователь</th>
        <th>Телефон</th>
        <th>Регистрация</th>
        <th>Статус</th>
        <th class="text-end"></th>
       </tr>
      </thead>
      <tbody>
       @forelse($users as $user)
        @php
         $application=$user->admissionApplications->first();
         $protected=
           $user->study_groups_count>0 ||
           $user->homework_submissions_count>0 ||
           $user->competition_registrations_count>0 ||
           $user->quiz_attempts_count>0;
        @endphp
        <tr>
         <td><input class="form-check-input" type="checkbox" name="user_ids[]" value="{{ $user->id }}" data-registration-checkbox></td>
         <td>
          <strong>{{ $user->name }}</strong>
          <div class="small text-white-50">{{ $user->email }}</div>
         </td>
         <td>{{ $user->phone ?: '—' }}</td>
         <td>
          {{ optional($user->created_at)->format('d.m.Y H:i') }}
          @if($application)<div class="small text-white-50">Заявка #{{ $application->id }}</div>@endif
         </td>
         <td>
          @if($protected)
           <span class="badge-soft">Используется</span>
          @else
           <span class="text-warning">Саморегистрация</span>
          @endif
         </td>
         <td class="text-end">
          <button
           class="btn btn-sm btn-outline-danger"
           type="submit"
           formaction="{{ route('admin.registrations.delete',$user) }}"
           formmethod="post"
           name="_method"
           value="DELETE"
           onclick="return confirm('Удалить регистрацию {{ addslashes($user->email) }}?')"
          >Удалить</button>
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
  </form>
 </div>
</section>

<script>
document.addEventListener('DOMContentLoaded',()=>{
 const all=document.querySelector('[data-select-all-registrations]');
 const boxes=[...document.querySelectorAll('[data-registration-checkbox]')];
 all?.addEventListener('change',()=>boxes.forEach(box=>box.checked=all.checked));
});
</script>
@endsection
