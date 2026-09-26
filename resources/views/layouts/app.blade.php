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

      @foreach(($customMenuPages ?? collect()) as $customPage)
        @if($customPage->children->count())
          <div class="desktop-custom-nav dropdown">
            <button class="desktop-nav-link desktop-custom-trigger {{ request()->routeIs('pages.show') && (optional(request()->route('page'))->id === $customPage->id || optional(request()->route('page'))->parent_id === $customPage->id) ? 'active' : '' }}" data-bs-toggle="dropdown" aria-expanded="false">
              <span>◆</span>{{ $customPage->menu_title ?: $customPage->title }} <i>⌄</i>
            </button>
            <div class="dropdown-menu dropdown-menu-dark sci-dropdown custom-page-dropdown">
              <a class="dropdown-item custom-page-parent-link" href="{{ route('pages.show',$customPage) }}">
                <strong>{{ $customPage->menu_title ?: $customPage->title }}</strong>
                @if($customPage->subtitle)<small>{{ $customPage->subtitle }}</small>@endif
              </a>
              <div class="dropdown-divider"></div>
              @foreach($customPage->children as $child)
                <a class="dropdown-item {{ request()->routeIs('pages.show') && optional(request()->route('page'))->id === $child->id ? 'active' : '' }}" href="{{ route('pages.show',$child) }}">
                  {{ $child->menu_title ?: $child->title }}
                </a>
              @endforeach
            </div>
          </div>
        @else
          <a class="desktop-nav-link custom-page-top-link {{ request()->routeIs('pages.show') && optional(request()->route('page'))->id === $customPage->id ? 'active' : '' }}" href="{{ route('pages.show',$customPage) }}"><span>◆</span>{{ $customPage->menu_title ?: $customPage->title }}</a>
        @endif
      @endforeach

      <div class="desktop-nav-more dropdown">
        <button class="desktop-nav-more-btn" data-bs-toggle="dropdown" aria-expanded="false">Ещё <span>＋</span></button>
        <div class="dropdown-menu dropdown-menu-dark sci-dropdown dropdown-menu-end">
          <a class="dropdown-item" href="{{ route('team') }}">Команда</a>
          <a class="dropdown-item" href="{{ route('equipment') }}">Оборудование</a>
          <a class="dropdown-item" href="{{ route('news.index') }}">Новости</a>
          <a class="dropdown-item" href="{{ route('clips.index') }}">Клипы</a>
          <a class="dropdown-item" href="{{ route('documents.index') }}">Документы</a>
          <a class="dropdown-item" href="{{ route('questions.create') }}">Задать вопрос</a>
          <a class="dropdown-item" href="{{ route('contacts.index') }}">Контакты</a>
          <a class="dropdown-item" href="{{ route('cooperation.index') }}">Сотрудничество</a>
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

@unless(request()->routeIs('admin.*'))
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
      <a href="{{ route('clips.index') }}"><span>▶</span><strong>Клипы</strong></a>
      <a href="{{ route('documents.index') }}"><span>▣</span><strong>Документы</strong></a>
      <a href="{{ route('questions.create') }}"><span>?</span><strong>Задать вопрос</strong></a>
      <a href="{{ route('contacts.index') }}"><span>⌖</span><strong>Контакты</strong></a>
      <a href="{{ route('cooperation.index') }}"><span>∞</span><strong>Сотрудничество</strong></a>
      @foreach(($customMenuPages ?? collect()) as $customPage)
        @if($customPage->children->count())
          <details class="mobile-custom-section" {{ request()->routeIs('pages.show') && (optional(request()->route('page'))->id === $customPage->id || optional(request()->route('page'))->parent_id === $customPage->id) ? 'open' : '' }}>
            <summary>
              <span>◆</span>
              <strong>{{ $customPage->menu_title ?: $customPage->title }}</strong>
              <i>⌄</i>
            </summary>
            <div class="mobile-custom-children">
              <a href="{{ route('pages.show',$customPage) }}"><span>⌂</span><strong>Главная раздела</strong></a>
              @foreach($customPage->children as $child)
                <a href="{{ route('pages.show',$child) }}" class="{{ request()->routeIs('pages.show') && optional(request()->route('page'))->id === $child->id ? 'active' : '' }}">
                  <span>↳</span><strong>{{ $child->menu_title ?: $child->title }}</strong>
                </a>
              @endforeach
            </div>
          </details>
        @else
          <a href="{{ route('pages.show',$customPage) }}" class="{{ request()->routeIs('pages.show') && optional(request()->route('page'))->id === $customPage->id ? 'active' : '' }}">
            <span>◆</span><strong>{{ $customPage->menu_title ?: $customPage->title }}</strong>
          </a>
        @endif
      @endforeach
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
@endunless

@if(!request()->routeIs('admin.*') && !request()->routeIs('clips.show') && !request()->routeIs('clips.design') && isset($musicTracks) && $musicTracks->count())
@php
$musicPlaylist=$musicTracks->map(function($track){
    return [
        'id'=>$track->id,
        'title'=>$track->title,
        'artist'=>$track->artist,
        'url'=>$track->file_url,
    ];
})->values()->all();
@endphp
<div class="global-music-player" data-global-music-player>
  <script type="application/json" data-music-playlist>{!! json_encode($musicPlaylist, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
  <div class="music-player-glow"></div>
  <button type="button" class="music-player-collapse" data-music-collapse aria-label="Свернуть плеер">⌄</button>
  <div class="music-player-top">
    <div class="music-eq" data-music-eq><i></i><i></i><i></i><i></i></div>
    <div class="music-player-label">ШКИ / AUDIO</div>
  </div>
  <div class="music-player-info">
    <strong data-music-title>Музыка ШКИ</strong>
    <span data-music-artist>Плейлист школы</span>
  </div>
  <div class="music-progress" data-music-progress>
    <div class="music-progress-fill" data-music-progress-fill></div>
  </div>
  <div class="music-time"><span data-music-current>00:00</span><span data-music-duration>00:00</span></div>
  <div class="music-controls">
    <button type="button" data-music-prev aria-label="Предыдущий трек">‹</button>
    <button type="button" class="music-main-btn" data-music-play aria-label="Воспроизведение">▶</button>
    <button type="button" data-music-next aria-label="Следующий трек">›</button>
  </div>
  <div class="music-volume">
    <span>VOL</span>
    <input type="range" min="0" max="1" step="0.01" value="0.65" data-music-volume aria-label="Громкость">
  </div>
  <audio data-music-audio preload="metadata"></audio>
</div>
@endif

@auth
@if(!request()->routeIs('admin.*'))
<form method="post" action="{{ route('logout') }}" class="mobile-logout-fab">@csrf
  <button type="submit" aria-label="Выйти из аккаунта"><span>↪</span></button>
</form>
@endif
@endauth

@auth
@if(request()->routeIs('admin.*') && !request()->routeIs('admin.login*'))
<button type="button" class="admin-sidebar-toggle" data-admin-sidebar-toggle aria-label="Открыть меню админки">☰</button>
<aside class="admin-sidebar" data-admin-sidebar>
  <div class="admin-sidebar-head">
    <div class="admin-sidebar-brand"><span>ШКИ</span><small>CONTROL CENTER</small></div>
    <button type="button" class="admin-sidebar-close" data-admin-sidebar-close aria-label="Закрыть">×</button>
  </div>

  <div class="admin-sidebar-scroll">
    @if(auth()->user()->is_admin || auth()->user()->canAdminSection('studios') || auth()->user()->canAdminSection('applications') || auth()->user()->canAdminSection('news'))
      <a class="admin-side-home {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><span>⌂</span><strong>Обзор</strong></a>
    @endif

    <div class="admin-side-group {{ request()->routeIs('admin.studios.*','admin.media*','admin.news.*','admin.projects*','admin.events*','admin.team*','admin.equipment*','admin.pages*') ? 'open' : '' }}">
      <button type="button" class="admin-side-group-title" data-admin-group-toggle><span>◆</span><strong>Контент</strong><i>⌄</i></button>
      <div class="admin-side-group-body">
        @if(auth()->user()->canAdminSection('studios'))<a class="{{ request()->routeIs('admin.studios.*') || request()->routeIs('admin.media*') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}#studiosAdmin">Студии и медиа</a>@endif
        @if(auth()->user()->canAdminSection('news'))<a class="{{ request()->routeIs('admin.news.*') ? 'active' : '' }}" href="{{ route('admin.news.create') }}">Новости</a>@endif
        @if(auth()->user()->canAdminSection('projects'))<a class="{{ request()->routeIs('admin.projects*') ? 'active' : '' }}" href="{{ route('admin.projects') }}">Проекты / портфолио</a>@endif
        @if(auth()->user()->canAdminSection('events'))<a class="{{ request()->routeIs('admin.events*') ? 'active' : '' }}" href="{{ route('admin.events') }}">События</a>@endif
        @if(auth()->user()->canAdminSection('team'))<a class="{{ request()->routeIs('admin.team*') ? 'active' : '' }}" href="{{ route('admin.team') }}">Команда</a>@endif
        @if(auth()->user()->canAdminSection('equipment'))<a class="{{ request()->routeIs('admin.equipment*') ? 'active' : '' }}" href="{{ route('admin.equipment') }}">Оборудование</a>@endif
        @if(auth()->user()->canAdminSection('pages'))<a class="{{ request()->routeIs('admin.pages*') ? 'active' : '' }}" href="{{ route('admin.pages') }}">Страницы и разделы</a>@endif
      </div>
    </div>

    <div class="admin-side-group {{ request()->routeIs('admin.groups*','admin.subjects*','admin.schedule*','admin.journal*','admin.homework*','admin.students*','admin.competitions*','admin.achievements*','admin.quizzes*') ? 'open' : '' }}">
      <button type="button" class="admin-side-group-title" data-admin-group-toggle><span>▦</span><strong>Обучение</strong><i>⌄</i></button>
      <div class="admin-side-group-body">
        @if(auth()->user()->canAdminSection('groups'))<a class="{{ request()->routeIs('admin.groups*') ? 'active' : '' }}" href="{{ route('admin.groups') }}">Учебные группы</a>@endif
        @if(auth()->user()->canAdminSection('subjects'))<a class="{{ request()->routeIs('admin.subjects*') ? 'active' : '' }}" href="{{ route('admin.subjects') }}">Предметы</a>@endif
        @if(auth()->user()->canAdminSection('schedule') || auth()->user()->teacherGroups()->exists())<a class="{{ request()->routeIs('admin.schedule*') ? 'active' : '' }}" href="{{ route('admin.schedule') }}">Расписание</a>@endif
        @if(auth()->user()->canAdminSection('journal') || auth()->user()->teacherGroups()->exists())<a class="{{ request()->routeIs('admin.journal*') ? 'active' : '' }}" href="{{ route('admin.journal') }}">Журнал</a>@endif
        @if(auth()->user()->canAdminSection('homework') || auth()->user()->teacherGroups()->exists())<a class="{{ request()->routeIs('admin.homework*') ? 'active' : '' }}" href="{{ route('admin.homework') }}">Домашние задания</a>@endif
        @if(auth()->user()->canAdminSection('students'))<a class="{{ request()->routeIs('admin.students*') ? 'active' : '' }}" href="{{ route('admin.students') }}">Ученики</a>@endif
        @if(auth()->user()->canAdminSection('competitions'))<a class="{{ request()->routeIs('admin.competitions*') || request()->routeIs('admin.achievements*') ? 'active' : '' }}" href="{{ route('admin.competitions') }}">Конкурсы и достижения</a>@endif
        @if(auth()->user()->canAdminSection('quizzes'))<a class="{{ request()->routeIs('admin.quizzes*') ? 'active' : '' }}" href="{{ route('admin.quizzes') }}">Викторины</a>@endif
      </div>
    </div>

    <div class="admin-side-group {{ request()->routeIs('admin.music*','admin.clips*','admin.documents*') ? 'open' : '' }}">
      <button type="button" class="admin-side-group-title" data-admin-group-toggle><span>◉</span><strong>Медиа</strong><i>⌄</i></button>
      <div class="admin-side-group-body">
        @if(auth()->user()->canAdminSection('music'))<a class="{{ request()->routeIs('admin.music*') ? 'active' : '' }}" href="{{ route('admin.music') }}">Музыка сайта</a>@endif
        @if(auth()->user()->canAdminSection('clips'))<a class="{{ request()->routeIs('admin.clips*') ? 'active' : '' }}" href="{{ route('admin.clips') }}">Клипы</a>@endif
        @if(auth()->user()->canAdminSection('documents'))<a class="{{ request()->routeIs('admin.documents*') ? 'active' : '' }}" href="{{ route('admin.documents') }}">Документы</a>@endif
      </div>
    </div>

    <div class="admin-side-group {{ request()->routeIs('admin.questions*','admin.contacts*') ? 'open' : '' }}">
      <button type="button" class="admin-side-group-title" data-admin-group-toggle><span>✉</span><strong>Обратная связь</strong><i>⌄</i></button>
      <div class="admin-side-group-body">
        @if(auth()->user()->canAdminSection('questions'))<a class="{{ request()->routeIs('admin.questions*') ? 'active' : '' }}" href="{{ route('admin.questions') }}">Вопросы и обращения</a>@endif
        @if(auth()->user()->canAdminSection('contacts'))<a class="{{ request()->routeIs('admin.contacts*') ? 'active' : '' }}" href="{{ route('admin.contacts') }}">Контакты и карта</a>@endif
        @if(auth()->user()->canAdminSection('applications'))<a href="{{ route('admin.dashboard') }}">Заявки на поступление</a>@endif
        @if(auth()->user()->canAdminSection('cooperation'))<a class="{{ request()->routeIs('admin.cooperation*') ? 'active' : '' }}" href="{{ route('admin.cooperation') }}">Сотрудничество</a>@endif
      </div>
    </div>

    <div class="admin-side-group {{ request()->routeIs('admin.settings*','admin.access-admins*') ? 'open' : '' }}">
      <button type="button" class="admin-side-group-title" data-admin-group-toggle><span>⚙</span><strong>Система</strong><i>⌄</i></button>
      <div class="admin-side-group-body">
        @if(auth()->user()->canAdminSection('settings'))<a class="{{ request()->routeIs('admin.settings*') ? 'active' : '' }}" href="{{ route('admin.settings') }}">Главная / SEO / хранилище</a>@endif
        @if(auth()->user()->is_admin)<a class="{{ request()->routeIs('admin.access-admins*') ? 'active' : '' }}" href="{{ route('admin.access-admins') }}">Администраторы</a>@endif
      </div>
    </div>
  </div>

  <div class="admin-sidebar-foot">
    <a href="{{ route('home') }}" target="_blank"><span>↗</span> Открыть сайт</a>
    <form method="post" action="{{ route('admin.logout') }}">@csrf<button type="submit"><span>↪</span> Выйти</button></form>
  </div>
</aside>
<div class="admin-sidebar-backdrop" data-admin-sidebar-backdrop></div>

<nav class="admin-mobile-nav" aria-label="Навигация админки">
  <a class="admin-mobile-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
    <span>⌂</span><small>Обзор</small>
  </a>

  @if(auth()->user()->canAdminSection('pages'))
    <a class="admin-mobile-item {{ request()->routeIs('admin.pages*') ? 'active' : '' }}" href="{{ route('admin.pages') }}"><span>◆</span><small>Контент</small></a>
  @elseif(auth()->user()->canAdminSection('news'))
    <a class="admin-mobile-item {{ request()->routeIs('admin.news*') ? 'active' : '' }}" href="{{ route('admin.news.create') }}"><span>◆</span><small>Контент</small></a>
  @elseif(auth()->user()->canAdminSection('studios'))
    <a class="admin-mobile-item {{ request()->routeIs('admin.studios*') || request()->routeIs('admin.media*') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}#studiosAdmin"><span>◆</span><small>Контент</small></a>
  @else
    <button type="button" class="admin-mobile-item" data-admin-sidebar-toggle><span>◆</span><small>Контент</small></button>
  @endif

  @if(auth()->user()->canAdminSection('groups'))
    <a class="admin-mobile-item {{ request()->routeIs('admin.groups*') ? 'active' : '' }}" href="{{ route('admin.groups') }}"><span>▦</span><small>Учёба</small></a>
  @elseif(auth()->user()->canAdminSection('students'))
    <a class="admin-mobile-item {{ request()->routeIs('admin.students*') ? 'active' : '' }}" href="{{ route('admin.students') }}"><span>▦</span><small>Учёба</small></a>
  @elseif(auth()->user()->canAdminSection('journal') || auth()->user()->teacherGroups()->exists())
    <a class="admin-mobile-item {{ request()->routeIs('admin.journal*') ? 'active' : '' }}" href="{{ route('admin.journal') }}"><span>▦</span><small>Учёба</small></a>
  @elseif(auth()->user()->canAdminSection('quizzes'))
    <a class="admin-mobile-item {{ request()->routeIs('admin.quizzes*') ? 'active' : '' }}" href="{{ route('admin.quizzes') }}"><span>▦</span><small>Учёба</small></a>
  @else
    <button type="button" class="admin-mobile-item" data-admin-sidebar-toggle><span>▦</span><small>Учёба</small></button>
  @endif

  @if(auth()->user()->canAdminSection('clips'))
    <a class="admin-mobile-item {{ request()->routeIs('admin.clips*') ? 'active' : '' }}" href="{{ route('admin.clips') }}"><span>▶</span><small>Медиа</small></a>
  @elseif(auth()->user()->canAdminSection('music'))
    <a class="admin-mobile-item {{ request()->routeIs('admin.music*') ? 'active' : '' }}" href="{{ route('admin.music') }}"><span>♫</span><small>Медиа</small></a>
  @elseif(auth()->user()->canAdminSection('documents'))
    <a class="admin-mobile-item {{ request()->routeIs('admin.documents*') ? 'active' : '' }}" href="{{ route('admin.documents') }}"><span>▣</span><small>Медиа</small></a>
  @else
    <button type="button" class="admin-mobile-item" data-admin-sidebar-toggle><span>◉</span><small>Медиа</small></button>
  @endif

  <button type="button" class="admin-mobile-item admin-mobile-more" data-admin-sidebar-toggle>
    <span>☰</span><small>Ещё</small>
  </button>
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
    <div class="mt-2"><a href="{{ route('questions.create') }}">Задать вопрос</a></div>
    <div class="mt-2"><a href="{{ route('contacts.index') }}">Контакты</a></div>
    <div class="mt-2"><a href="{{ route('cooperation.index') }}">Сотрудничество</a></div>
    <div class="mt-2"><a href="{{ route('clips.index') }}">Клипы</a></div>
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
