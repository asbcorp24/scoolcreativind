@extends('layouts.app')
@section('title','Администраторы разделов · ШКИ')
@section('content')
@php($editing=$editAdmin ?? null)
<section class="page-top"><div class="container">
 <div class="eyebrow">Access control</div>
 <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
  <div><h1 class="display-3 fw-bold mb-1">Администраторы разделов</h1><p class="text-white-50 mb-0">Создавайте сотрудников и выдавайте доступ только к нужным разделам.</p></div>
  <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">← Админка</a>
 </div>
</div></section>

<section class="pb-5"><div class="container">
 @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

 <div class="row g-4">
  <div class="col-xl-5">
   <form class="glass-card p-4" method="post" action="{{ route('admin.access-admins.save',$editing) }}">@csrf
    <h3>{{ $editing ? 'Редактировать администратора' : 'Новый администратор' }}</h3>
    <div class="row g-3 mt-1">
     <div class="col-12"><label class="form-label">Имя</label><input class="form-control" name="name" value="{{ old('name',$editing->name ?? '') }}" required></div>
     <div class="col-12"><label class="form-label">Email / логин</label><input type="email" class="form-control" name="email" value="{{ old('email',$editing->email ?? '') }}" required></div>
     <div class="col-12"><label class="form-label">Телефон</label><input class="form-control" name="phone" value="{{ old('phone',$editing->phone ?? '') }}"></div>
     <div class="col-12"><label class="form-label">{{ $editing ? 'Новый пароль (если нужно изменить)' : 'Пароль' }}</label><input type="password" class="form-control" name="password" {{ $editing ? '' : 'required' }}></div>

     <div class="col-12">
      <label class="form-label">Доступ к разделам</label>
      <div class="admin-section-picker">
       @foreach($sections as $key=>$label)
        <label class="admin-section-option">
         <input type="checkbox" name="sections[]" value="{{ $key }}" @checked(in_array($key,old('sections',$editing->admin_sections ?? []),true))>
         <span><strong>{{ $label }}</strong><small>{{ $key }}</small></span>
        </label>
       @endforeach
      </div>
     </div>

     <div class="col-12 d-flex gap-2 justify-content-end">
      @if($editing)<a href="{{ route('admin.access-admins') }}" class="btn btn-ghost">Отмена</a>@endif
      <button class="btn btn-neon">{{ $editing ? 'Сохранить права' : 'Создать администратора' }}</button>
     </div>
    </div>
   </form>
  </div>

  <div class="col-xl-7">
   <div class="glass-card p-4">
    <h3 class="mb-4">Администраторы</h3>
    <div class="table-responsive">
     <table class="table admin-table align-middle">
      <thead><tr><th>Пользователь</th><th>Права</th><th></th></tr></thead>
      <tbody>
       @foreach($admins as $admin)
        <tr>
         <td><strong>{{ $admin->name }}</strong><div class="small text-white-50">{{ $admin->email }}</div></td>
         <td>
          @if($admin->is_admin)
           <span class="badge-soft">Супер-администратор · все разделы</span>
          @else
           <div class="d-flex gap-1 flex-wrap">
            @foreach($admin->admin_sections ?? [] as $section)
             <span class="badge-soft">{{ $sections[$section] ?? $section }}</span>
            @endforeach
           </div>
          @endif
         </td>
         <td class="text-end">
          @unless($admin->is_admin)
           <div class="d-flex gap-2 justify-content-end">
            <a href="{{ route('admin.access-admins.edit',$admin) }}" class="btn btn-sm btn-ghost">Изменить</a>
            <form method="post" action="{{ route('admin.access-admins.delete',$admin) }}" onsubmit="return confirm('Снять права администратора у этого пользователя?')">@csrf @method('DELETE')
             <button class="btn btn-sm btn-outline-danger">Снять права</button>
            </form>
           </div>
          @endunless
         </td>
        </tr>
       @endforeach
      </tbody>
     </table>
    </div>
   </div>
  </div>
 </div>
</div></section>
@endsection
