@extends('layouts.app')
@section('title','Вход в админ-панель · ШКИ')
@section('content')
<section class="page-top min-vh-100 d-flex align-items-center">
 <div class="container">
  <form class="form-shell" method="post" action="{{ route('admin.login.submit') }}">@csrf
   <div class="eyebrow">Administration</div>
   <h1 class="display-4 fw-bold mt-2 mb-2">Вход в админ-панель</h1>
   <p class="text-white-50 mb-4">Используйте учётную запись администратора.</p>
   @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
   <div class="mb-3"><label class="form-label">E-mail администратора</label><input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-lg" required autofocus></div>
   <div class="mb-3"><label class="form-label">Пароль</label><input type="password" name="password" class="form-control form-control-lg" required></div>
   <div class="form-check mb-4"><input class="form-check-input" type="checkbox" name="remember" id="adminRemember"><label class="form-check-label" for="adminRemember">Запомнить меня</label></div>
   <button class="btn btn-neon btn-lg w-100">Войти в админку</button>
   <div class="text-center mt-4"><a href="{{ route('home') }}" class="text-white-50">← Вернуться на сайт</a></div>
  </form>
 </div>
</section>
@endsection
