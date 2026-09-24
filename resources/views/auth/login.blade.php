@extends('layouts.app')
@section('title','Вход · ШКИ')
@section('content')
<section class="page-top min-vh-100 d-flex align-items-center">
 <div class="container">
  <form class="form-shell" method="post" action="{{ route('login') }}">@csrf
   <div class="eyebrow">Личный кабинет</div><h1 class="display-4 fw-bold mt-2 mb-4">Вход</h1>
   @error('email')<div class="alert alert-danger">{{ $message }}</div>@enderror
   <div class="mb-3"><label class="form-label">E-mail</label><input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-lg" required autofocus></div>
   <div class="mb-3"><label class="form-label">Пароль</label><input type="password" name="password" class="form-control form-control-lg" required></div>
   <div class="form-check mb-4"><input class="form-check-input" type="checkbox" name="remember" id="remember"><label class="form-check-label" for="remember">Запомнить меня</label></div>
   <button class="btn btn-neon btn-lg w-100">Войти</button>
   <div class="text-center mt-4 text-white-50">Нет аккаунта? <a class="text-white" href="{{ route('register') }}">Зарегистрироваться</a></div>
  </form>
 </div>
</section>
@endsection
