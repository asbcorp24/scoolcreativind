@extends('layouts.app')
@section('title',$siteSettings['seo_title'] ?? 'Школа креативных индустрий · Волжск')
@section('content')
<section class="hero3d">
 <canvas id="heroCanvas"></canvas>
 <div class="hero-grid"></div>
 <div class="container position-relative hero-copy">
  <div class="row align-items-end min-vh-100 py-5">
   <div class="col-xl-9 pb-5">
    <div class="eyebrow reveal">{{ $siteSettings['home_eyebrow'] ?? 'Волжск · образование будущего · 2 года' }}</div>
    <h1 class="hero-title reveal">{{ $siteSettings['home_title_line1'] ?? 'ШКОЛА' }}<br><span>{{ $siteSettings['home_title_line2'] ?? 'КРЕАТИВНЫХ' }}</span><br>{{ $siteSettings['home_title_line3'] ?? 'ИНДУСТРИЙ' }}</h1>
    <div class="row g-4 align-items-center mt-2 reveal">
     <div class="col-lg-7"><p class="lead text-white-50">{{ $siteSettings['home_intro'] ?? 'Здесь идеи превращаются в анимацию, музыку, дизайн, фильмы, 3D, VR и AR. Обучение строится вокруг реальных проектов и современной студийной техники.' }}</p></div>
     <div class="col-lg-5 d-flex gap-3 flex-wrap"><a class="btn btn-neon btn-lg" href="{{ route('apply') }}">{{ $siteSettings['home_primary_button'] ?? 'Записаться на обучение' }}</a><a class="btn btn-ghost btn-lg" href="#studios">{{ $siteSettings['home_secondary_button'] ?? 'Исследовать студии' }}</a></div>
    </div>
   </div>
  </div>
 </div>
 <div class="hero-side">SCROLL / EXPLORE / CREATE</div>
</section>

<section class="ticker"><div>ANIMATION · 3D · DESIGN · AUDIO · ELECTRONIC MUSIC · FILM · PHOTO · VR · AR · INTERACTIVE · </div></section>

<section id="studios" class="section-space">
 <div class="container">
  <div class="section-head reveal">
   <div><div class="eyebrow">{{ $siteSettings['home_studios_eyebrow'] ?? '6 направлений · одна экосистема' }}</div><h2>{{ $siteSettings['home_studios_title'] ?? 'Студии' }}</h2></div>
   <p>{{ $siteSettings['home_studios_text'] ?? 'Каждая студия — отдельный цифровой мир с фотогалереей, видео, 360°-пространствами и работами учеников.' }}</p>
  </div>
  <div class="studio-grid">
   @foreach($studios as $i=>$studio)
   <a href="{{ route('studios.show',$studio) }}" class="studio-card reveal" style="--accent:{{ $studio->accent ?: '#8a5cff' }}">
    <div class="studio-index">0{{ $i+1 }}</div>
    @if($studio->cover)<img src="{{ asset('storage/'.$studio->cover) }}" alt="{{ $studio->title }}">@endif
    <div class="studio-overlay"></div>
    <div class="studio-content">
      <div class="studio-icon">{{ $studio->icon ?: '✦' }}</div>
      <h3>{{ $studio->title }}</h3>
      <p>{{ $studio->subtitle }}</p>
      <span>Открыть направление ↗</span>
    </div>
   </a>
   @endforeach
  </div>
 </div>
</section>

<section class="immersive-section" id="campus360">
 <div class="container">
  <div class="row g-5 align-items-center">
   <div class="col-lg-5 reveal">
    <div class="eyebrow">Virtual campus</div>
    <h2 class="display-3 fw-bold">{{ $siteSettings['home_360_title'] ?? 'Зайди внутрь до первого занятия.' }}</h2>
    <p class="lead text-white-50">{{ $siteSettings['home_360_text'] ?? 'Панорамные 360°-сцены позволяют посмотреть студии и оборудование прямо в браузере.' }}</p>
    <a href="#studios" class="btn btn-ghost">Выбрать студию</a>
   </div>
   <div class="col-lg-7 reveal">
    @php($pano=$featuredMedia->firstWhere('type','panorama'))
    <div class="pano-shell" @if($pano) data-panorama="{{ $pano->display_url }}" @endif>
      <div class="pano-placeholder">
       <div class="pano-orbit"></div><strong>360°</strong><span>Перетащите, чтобы осмотреться</span>
      </div>
    </div>
   </div>
  </div>
 </div>
</section>

<section id="works" class="section-space">
 <div class="container-fluid px-lg-5">
  <div class="section-head reveal"><div><div class="eyebrow">Student output</div><h2>{{ $siteSettings['home_works_title'] ?? 'Сделано здесь' }}</h2></div><p>{{ $siteSettings['home_works_text'] ?? 'Не учебные упражнения, а портфолио: ролики, сцены, треки, брендинг, AR/VR и цифровые эксперименты.' }}</p></div>
  <div class="projects-rail">
   @forelse($projects as $project)
   <article class="project-card reveal">
    <div class="project-media">@if($project->cover)<img src="{{ $project->cover }}" alt="">@else<div class="project-noise"></div>@endif</div>
    <div class="d-flex justify-content-between gap-3 pt-3"><div><h4>{{ $project->title }}</h4><div class="small text-white-50">{{ $project->author }}</div></div><div class="project-year">{{ $project->year }}</div></div>
   </article>
   @empty
   <div class="text-white-50">Нет опубликованных работ.</div>
   @endforelse
  </div>
 </div>
</section>

<section class="section-space pt-0">
 <div class="container">
  <div class="split-panel reveal">
   <div class="stat"><span>2</span><small>года<br>обучения</small></div>
   <div class="stat"><span>6</span><small>профильных<br>студий</small></div>
   <div class="stat"><span>∞</span><small>пространство<br>для идей</small></div>
   <div class="split-copy"><div class="eyebrow">Практика вместо скучной теории</div><p>Командные проекты, современный софт, студийное оборудование, выставки, премьеры, мастер-классы и собственное цифровое портфолио.</p></div>
  </div>
 </div>
</section>


<section class="section-space pt-0">
 <div class="container">
  <div class="section-head reveal">
   <div><div class="eyebrow">People & tools</div><h2>Кто и на чём учит</h2></div>
   <p>Наставники, преподаватели и оборудование, с которым ученики работают в реальных проектах.</p>
  </div>

  <div class="row g-4 mb-5">
   @forelse($team as $m)
   <div class="col-md-6 col-xl-3 reveal">
    <a href="{{ route('team') }}" class="home-team-card">
      <div class="home-team-photo">@if($m->photo_url)<img src="{{ $m->photo_url }}" alt="{{ $m->name }}">@else<div class="team-placeholder">{{ mb_substr($m->name,0,1) }}</div>@endif</div>
      <div class="pt-3">
       <div class="small text-white-50">{{ $m->role }}</div>
       <h4 class="mb-1">{{ $m->name }}</h4>
      </div>
    </a>
   </div>
   @empty
   <div class="col-12 text-white-50">Нет опубликованных данных о команде.</div>
   @endforelse
  </div>

  <div class="equipment-strip">
   @forelse($equipment as $item)
    <a href="{{ route('equipment') }}" class="equipment-mini reveal">
      <div class="equipment-mini-image">@if($item->image_url)<img src="{{ $item->image_url }}" alt="{{ $item->title }}">@endif</div>
      <div>
       <div class="small text-white-50">{{ $item->category }}</div>
       <strong>{{ $item->title }}</strong>
       <div class="small">{{ $item->studio->title ?? 'ШКИ' }}</div>
      </div>
    </a>
   @empty
    <div class="text-white-50">Нет опубликованных данных об оборудовании.</div>
   @endforelse
  </div>
 </div>
</section>

<section class="section-space pt-0">
 <div class="container">
  <div class="section-head reveal"><div><div class="eyebrow">Calendar</div><h2>События</h2></div><p>Дни открытых дверей, мастер-классы, показы, выставки и встречи со специалистами индустрии.</p></div>
  <div class="row g-4">
   @forelse($events as $event)
   <div class="col-md-6 reveal">
    <article class="glass-card p-4 h-100">
      <div class="eyebrow">{{ $event->starts_at->format('d.m.Y · H:i') }}</div>
      <h3 class="display-6 fw-bold mt-3">{{ $event->title }}</h3>
      <p class="text-white-50">{{ $event->description }}</p>
      <div class="small mb-3">{{ $event->location }}</div>
      @if($event->registration_url)<a class="btn btn-ghost" href="{{ $event->registration_url }}" target="_blank" rel="noopener">Регистрация ↗</a>@endif
    </article>
   </div>
   @empty
   <div class="col-12 text-white-50">Нет опубликованных событий.</div>
   @endforelse
  </div>
 </div>
</section>

<section class="section-space">
 <div class="container">
  <div class="section-head reveal"><div><div class="eyebrow">Сейчас в школе</div><h2>Новости</h2></div><a class="btn btn-ghost" href="{{ route('news.index') }}">Все новости</a></div>
  <div class="row g-4">
   @forelse($news as $post)
   <div class="col-md-6 col-xl-4 reveal"><a href="{{ route('news.show',$post) }}" class="news-card">
    <div class="news-cover" @if($post->cover) style="background-image:url('{{ $post->cover }}')" @endif></div>
    <div class="news-body"><div class="small text-white-50">{{ optional($post->published_at)->format('d.m.Y') }}</div><h3>{{ $post->title }}</h3><p>{{ $post->excerpt }}</p></div>
   </a></div>
   @empty
   <div class="col-12 text-white-50">Нет опубликованных новостей.</div>
   @endforelse
  </div>
 </div>
</section>

<section class="cta-section">
 <div class="container text-center reveal">
  <div class="eyebrow justify-content-center">{{ $siteSettings['home_cta_eyebrow'] ?? 'Приём документов · г. Волжск, ул. Ленина, 32' }}</div>
  <h2>{{ $siteSettings['home_cta_title'] ?? 'Твоё первое портфолио начинается здесь.' }}</h2>
  <div class="d-flex justify-content-center gap-3 flex-wrap mt-4"><a href="{{ route('apply') }}" class="btn btn-neon btn-lg">{{ $siteSettings['home_cta_button'] ?? 'Подать заявку' }}</a><a href="tel:+78363164628" class="btn btn-ghost btn-lg">+7 (836) 316-46-28</a></div>
 </div>
</section>
@endsection
