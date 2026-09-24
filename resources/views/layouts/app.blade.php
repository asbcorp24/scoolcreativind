<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title','Школа креативных индустрий')</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/site.css') }}">
@stack('head')
</head>
<body>
<div id="pageTransition"><span>ШКИ</span></div>
<div id="cursorGlow"></div>
<nav class="navbar navbar-expand-lg navbar-dark fixed-top sci-nav">
  <div class="container-fluid px-lg-5">
    <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
      <span class="brand-orbit"></span><span>ШКИ<span class="brand-dot">.</span></span>
    </a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div id="nav" class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
        <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#studios">Студии</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#works">Работы</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('news.index') }}">Новости</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#campus360">360°</a></li>
        <li class="nav-item ms-lg-2"><a class="btn btn-neon" href="{{ route('apply') }}">Поступить</a></li>
        @auth
          <li class="nav-item"><a class="nav-link" href="{{ route('cabinet') }}">Кабинет</a></li>
          @if(auth()->user()->is_admin)<li class="nav-item"><a class="nav-link" href="{{ route('admin.dashboard') }}">Админ</a></li>@endif
        @else
          <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Войти</a></li>
        @endauth
      </ul>
    </div>
  </div>
</nav>

@if(session('success'))<div class="toast-success">{{ session('success') }}</div>@endif
<main>@yield('content')</main>

<footer class="footer-shell">
 <div class="container py-5">
  <div class="row g-4 align-items-end">
   <div class="col-lg-7">
    <div class="eyebrow">Школа креативных индустрий · Волжск</div>
    <h2 class="display-5 fw-bold mt-2">Создавай то, чего ещё нет.</h2>
   </div>
   <div class="col-lg-5 text-lg-end">
    <div>г. Волжск, ул. Ленина, 32</div>
    <a href="tel:+78363164628">+7 (836) 316-46-28</a>
   </div>
  </div>
  <hr><div class="small opacity-50">© {{ date('Y') }} Школа креативных индустрий</div>
 </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.180.0/build/three.min.js"></script>
<script type="module" src="https://unpkg.com/@google/model-viewer/dist/model-viewer.min.js"></script>
<script src="{{ asset('js/site.js') }}"></script>
@stack('scripts')
</body>
</html>
