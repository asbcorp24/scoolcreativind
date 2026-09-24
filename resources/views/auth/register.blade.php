@extends('layouts.app')
@section('title','Регистрация · ШКИ')
@section('content')
<section class="page-top">
 <div class="container">
  <form class="form-shell" method="post" action="{{ route('register') }}">@csrf
   <div class="eyebrow">Личный кабинет</div><h1 class="display-4 fw-bold mt-2 mb-4">Регистрация</h1>
   @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
   <div class="mb-3"><label class="form-label">Имя</label><input name="name" value="{{ old('name') }}" class="form-control form-control-lg" required></div>
   <div class="row g-3"><div class="col-md-6"><label class="form-label">E-mail</label><input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-lg" required></div><div class="col-md-6"><label class="form-label">Телефон</label><input name="phone" value="{{ old('phone') }}" class="form-control form-control-lg"></div></div>
   <div class="row g-3 mt-1"><div class="col-md-6"><label class="form-label">Пароль</label><input type="password" name="password" class="form-control form-control-lg" required></div><div class="col-md-6"><label class="form-label">Повторите пароль</label><input type="password" name="password_confirmation" class="form-control form-control-lg" required></div></div>
   <button class="btn btn-neon btn-lg w-100 mt-4">Создать аккаунт</button>
  </form>
 </div>
</section>
@endsection
