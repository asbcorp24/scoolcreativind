<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
@php
$seoTitle=$siteSettings['seo_title'] ?? 'Школа креативных индустрий · Волжск';
$seoDescription=$siteSettings['seo_description'] ?? 'Школа креативных индустрий в Волжске: анимация, 3D, дизайн, звук, электронная музыка, фото, видео, VR и AR.';
$seoKeywords=$siteSettings['seo_keywords'] ?? '';
$seoRobots=$siteSettings['seo_robots'] ?? 'index,follow';
$seoCanonical=$siteSettings['seo_canonical'] ?? '';
$ogTitle=$siteSettings['seo_og_title'] ?? $seoTitle;
$ogDescription=$siteSettings['seo_og_description'] ?? $seoDescription;
$ogImage=$siteSettings['seo_og_image'] ?? '';
$twitterCard=$siteSettings['seo_twitter_card'] ?? 'summary_large_image';
@endphp
<title>@yield('title',$seoTitle)</title>
<meta name="description" content="@yield('meta_description',$seoDescription)">
@if($seoKeywords)<meta name="keywords" content="{{ $seoKeywords }}">@endif
<meta name="robots" content="{{ $seoRobots }}">
@if($seoCanonical)<link rel="canonical" href="{{ $seoCanonical }}">@endif
<meta property="og:type" content="website">
<meta property="og:title" content="@yield('og_title',$ogTitle)">
<meta property="og:description" content="@yield('og_description',$ogDescription)">
<meta property="og:url" content="{{ url()->current() }}">
@if($ogImage)<meta property="og:image" content="{{ $ogImage }}">@endif
<meta name="twitter:card" content="{{ $twitterCard }}">
<meta name="theme-color" content="#0b0f17">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="ШКИ Волжск">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="icon" href="{{ asset('icons/pwa.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/site.css') }}">
@stack('head')
</head>
<body>
<div id="pageTransition"><span>ШКИ</span></div>
<div id="cursorGlow"></div>
<nav class="navbar navbar-expand-lg navbar-dark fixed-top sci-nav desktop-nav">
  <div class="container-fluid px-lg-5">
    <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
      <span class="brand-orbit"></span>
      <span class="brand-copy"><strong>ШКИ<span class="brand-dot">.</span></strong><small>Волжск</small></span>
    </a>
    <button type="button" class="accessibility-toggle" data-accessibility-toggle aria-pressed="false" title="Версия для слабовидящих">
      <span>◉</span><small>Версия для слабовидящих</small>
    </button>

    <div class="desktop-nav-shell ms-auto">
      <a class="desktop-nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}"><span>01</span>Главная</a>
      <a class="desktop-nav-link {{ request()->routeIs('projects.*') ? 'active' : '' }}" href="{{ route('projects.index') }}"><span>02</span>Работы</a>
      <a class="desktop-nav-link {{ request()->routeIs('competitions') ? 'active' : '' }}" href="{{ route('competitions') }}"><span>03</span>Конкурсы</a>
      <a class="desktop-nav-link {{ request()->routeIs('quizzes.*') ? 'active' : '' }}" href="{{ route('quizzes.index') }}"><span>04</span>Викторины</a>
      <a class="desktop-nav-link {{ request()->routeIs('schedule') ? 'active' : '' }}" href="{{ route('schedule') }}"><span>05</span>Расписание</a>
      <a class="desktop-nav-link" href="{{ route('home') }}#studios"><span>06</span>Студии</a>

      <div class="desktop-nav-more dropdown">
        <button class="desktop-nav-more-btn" data-bs-toggle="dropdown" aria-expanded="false">Ещё <span>＋</span></button>
        <div class="dropdown-menu dropdown-menu-dark sci-dropdown dropdown-menu-end">
          <a class="dropdown-item" href="{{ route('team') }}">Команда</a>
          <a class="dropdown-item" href="{{ route('equipment') }}">Оборудование</a>
          <a class="dropdown-item" href="{{ route('news.index') }}">Новости</a>
          <a class="dropdown-item" href="{{ route('documents.index') }}">Документы</a>
          <a class="dropdown-item" href="{{ route('home') }}#campus360">360° тур</a>
          <button type="button" class="dropdown-item d-none" data-pwa-install>Установить приложение</button>
          @auth
            <a class="dropdown-item" href="{{ route('cabinet') }}">Личный кабинет</a>
            @if(auth()->user()->is_admin || auth()->user()->isSectionAdmin())<a class="dropdown-item" href="{{ route(auth()->user()->adminLandingRoute()) }}">Админ-панель</a>@endif
            <div class="dropdown-divider"></div>
            <form method="post" action="{{ route('logout') }}" class="m-0">@csrf
              <button type="submit" class="dropdown-item sci-logout-item">Выйти из аккаунта</button>
            </form>
          @else
            <a class="dropdown-item" href="{{ route('login') }}">Войти</a>
          @endauth
        </div>
      </div>

      <a class="desktop-apply-btn" href="{{ route('apply') }}"><span>✦</span> Поступить</a>
    </div>
  </div>
</nav>

<nav class="mobile-app-nav" aria-label="Мобильная навигация">
  <a class="mobile-app-item {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
    <span class="mobile-app-icon">⌂</span><small>Главная</small>
  </a>
  <a class="mobile-app-item {{ request()->routeIs('projects.*') ? 'active' : '' }}" href="{{ route('projects.index') }}">
    <span class="mobile-app-icon">◇</span><small>Работы</small>
  </a>
  <a class="mobile-app-item {{ request()->routeIs('competitions') ? 'active' : '' }}" href="{{ route('competitions') }}">
    <span class="mobile-app-icon">★</span><small>Конкурсы</small>
  </a>
  @auth
    <a class="mobile-app-item {{ request()->routeIs('cabinet') || request()->routeIs('academic.*') ? 'active' : '' }}" href="{{ route('cabinet') }}">
      <span class="mobile-app-icon">◉</span><small>Кабинет</small>
    </a>
  @else
    <a class="mobile-app-item {{ request()->routeIs('login') || request()->routeIs('register') ? 'active' : '' }}" href="{{ route('login') }}">
      <span class="mobile-app-icon">◉</span><small>Войти</small>
    </a>
  @endauth
  <button type="button" class="mobile-app-item mobile-more-trigger" data-mobile-more-open>
    <span class="mobile-app-icon">☰</span><small>Ещё</small>
  </button>
</nav>

<div class="mobile-more-sheet" data-mobile-more-sheet aria-hidden="true">
  <button class="mobile-more-backdrop" type="button" data-mobile-more-close aria-label="Закрыть меню"></button>
  <div class="mobile-more-panel">
    <div class="mobile-more-handle"></div>
    <div class="mobile-more-head">
      <div><div class="eyebrow">Навигация</div><h3>Разделы сайта</h3></div>
      <button type="button" class="mobile-more-close" data-mobile-more-close aria-label="Закрыть">×</button>
    </div>

    <div class="mobile-more-grid">
      <a href="{{ route('home') }}#studios"><span>✦</span><strong>Студии</strong></a>
      <a href="{{ route('schedule') }}"><span>◷</span><strong>Расписание</strong></a>
      <a href="{{ route('quizzes.index') }}"><span>?</span><strong>Викторины</strong></a>
      <a href="{{ route('team') }}"><span>◎</span><strong>Команда</strong></a>
      <a href="{{ route('equipment') }}"><span>⌘</span><strong>Оборудование</strong></a>
      <a href="{{ route('news.index') }}"><span>▤</span><strong>Новости</strong></a>
      <a href="{{ route('documents.index') }}"><span>▣</span><strong>Документы</strong></a>
      <a href="{{ route('home') }}#campus360"><span>360°</span><strong>Виртуальный тур</strong></a>
      <a href="{{ route('apply') }}"><span>＋</span><strong>Поступить</strong></a>
      <button type="button" data-pwa-install class="mobile-more-install"><span>⇩</span><strong>Установить приложение</strong></button>
      @auth
        @if(auth()->user()->is_admin || auth()->user()->isSectionAdmin())
          <a href="{{ route(auth()->user()->adminLandingRoute()) }}"><span>⚙</span><strong>Админка</strong></a>
        @endif
      @endauth
    </div>
  </div>
</div>

@auth
<form method="post" action="{{ request()->routeIs('admin.*') ? route('admin.logout') : route('logout') }}" class="mobile-logout-fab">@csrf
  <button type="submit" aria-label="Выйти из аккаунта"><span>↪</span></button>
</form>
@endauth

@auth
@if(request()->routeIs('admin.*') && !request()->routeIs('admin.login*'))
<nav class="admin-subnav">
  <div class="container-fluid px-lg-5">
    <div class="admin-subnav-scroll">
      @if(auth()->user()->is_admin || auth()->user()->canAdminSection('studios') || auth()->user()->canAdminSection('applications') || auth()->user()->canAdminSection('news'))
        <a class="admin-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Обзор</a>
      @endif
      @if(auth()->user()->canAdminSection('studios'))<a class="admin-nav-link {{ request()->routeIs('admin.studios.*') || request()->routeIs('admin.media*') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}#studiosAdmin">Студии</a>@endif
      @if(auth()->user()->canAdminSection('news'))<a class="admin-nav-link {{ request()->routeIs('admin.news.*') ? 'active' : '' }}" href="{{ route('admin.news.create') }}">Новости</a>@endif
      @if(auth()->user()->canAdminSection('projects'))<a class="admin-nav-link {{ request()->routeIs('admin.projects*') ? 'active' : '' }}" href="{{ route('admin.projects') }}">Проекты</a>@endif
      @if(auth()->user()->canAdminSection('events'))<a class="admin-nav-link {{ request()->routeIs('admin.events*') ? 'active' : '' }}" href="{{ route('admin.events') }}">События</a>@endif
      @if(auth()->user()->canAdminSection('team'))<a class="admin-nav-link {{ request()->routeIs('admin.team*') ? 'active' : '' }}" href="{{ route('admin.team') }}">Команда</a>@endif
      @if(auth()->user()->canAdminSection('equipment'))<a class="admin-nav-link {{ request()->routeIs('admin.equipment*') ? 'active' : '' }}" href="{{ route('admin.equipment') }}">Оборудование</a>@endif
      @if(auth()->user()->canAdminSection('students'))<a class="admin-nav-link {{ request()->routeIs('admin.students*') ? 'active' : '' }}" href="{{ route('admin.students') }}">Ученики</a>@endif
      @if(auth()->user()->canAdminSection('competitions'))<a class="admin-nav-link {{ request()->routeIs('admin.competitions*') || request()->routeIs('admin.achievements*') ? 'active' : '' }}" href="{{ route('admin.competitions') }}">Конкурсы</a>@endif
      @if(auth()->user()->canAdminSection('quizzes'))<a class="admin-nav-link {{ request()->routeIs('admin.quizzes*') ? 'active' : '' }}" href="{{ route('admin.quizzes') }}">Викторины</a>@endif
      @if(auth()->user()->canAdminSection('groups'))<a class="admin-nav-link {{ request()->routeIs('admin.groups*') ? 'active' : '' }}" href="{{ route('admin.groups') }}">Группы</a>@endif
      @if(auth()->user()->canAdminSection('subjects'))<a class="admin-nav-link {{ request()->routeIs('admin.subjects*') ? 'active' : '' }}" href="{{ route('admin.subjects') }}">Предметы</a>@endif
      @if(auth()->user()->canAdminSection('schedule') || auth()->user()->teacherGroups()->exists())<a class="admin-nav-link {{ request()->routeIs('admin.schedule*') ? 'active' : '' }}" href="{{ route('admin.schedule') }}">Расписание</a>@endif
      @if(auth()->user()->canAdminSection('journal') || auth()->user()->teacherGroups()->exists())<a class="admin-nav-link {{ request()->routeIs('admin.journal*') ? 'active' : '' }}" href="{{ route('admin.journal') }}">Журнал</a>@endif
      @if(auth()->user()->canAdminSection('homework') || auth()->user()->teacherGroups()->exists())<a class="admin-nav-link {{ request()->routeIs('admin.homework*') ? 'active' : '' }}" href="{{ route('admin.homework') }}">Домашние задания</a>@endif
      @if(auth()->user()->canAdminSection('settings'))<a class="admin-nav-link {{ request()->routeIs('admin.settings*') ? 'active' : '' }}" href="{{ route('admin.settings') }}">Главная / SEO</a>@endif
      @if(auth()->user()->canAdminSection('documents'))<a class="admin-nav-link {{ request()->routeIs('admin.documents*') ? 'active' : '' }}" href="{{ route('admin.documents') }}">Документы</a>@endif
      @if(auth()->user()->is_admin)<a class="admin-nav-link {{ request()->routeIs('admin.access-admins*') ? 'active' : '' }}" href="{{ route('admin.access-admins') }}">Администраторы</a>@endif
      <a class="admin-nav-link admin-nav-site" href="{{ route('home') }}" target="_blank">Открыть сайт ↗</a>
      <form method="post" action="{{ route('admin.logout') }}" class="admin-logout-form">@csrf
        <button type="submit" class="admin-nav-link admin-nav-logout">Выйти</button>
      </form>
    </div>
  </div>
</nav>
@endif
@endauth

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
    <div class="mt-2"><a href="{{ route('documents.index') }}">Официальные документы</a></div>
   </div>
  </div>
  <hr><div class="small opacity-50">© {{ date('Y') }} Школа креативных индустрий</div>
 </div>
</footer>
<script src="{{ asset('assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script type="module" src="{{ asset('js/site.js') }}"></script>
@stack('scripts')
</body>
</html>
