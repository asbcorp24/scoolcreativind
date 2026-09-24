@extends('layouts.app')
@section('title','Настройки сайта · Админ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Админ / настройки</div><div class="d-flex justify-content-between align-items-end gap-3 flex-wrap"><h1 class="display-3 fw-bold m-0">Тексты главной и SEO</h1><a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">← Админка</a></div></div></section>
<section class="pb-5"><div class="container">
<form method="post" action="{{ route('admin.settings.update') }}" class="glass-card p-4 p-lg-5">@csrf
<ul class="nav nav-pills gap-2 mb-4" role="tablist">
 <li class="nav-item"><button class="btn btn-ghost active" data-bs-toggle="pill" data-bs-target="#homeSettings" type="button">Главная страница</button></li>
 <li class="nav-item"><button class="btn btn-ghost" data-bs-toggle="pill" data-bs-target="#seoSettings" type="button">SEO</button></li>
</ul>
<div class="tab-content">
<div class="tab-pane fade show active" id="homeSettings">
 <div class="row g-3">
  <div class="col-12"><h3>Первый экран</h3></div>
  <div class="col-12"><label class="form-label">Надзаголовок</label><input class="form-control" name="home_eyebrow" value="{{ old('home_eyebrow',$settings['home_eyebrow'] ?? 'Волжск · образование будущего · 2 года') }}"></div>
  <div class="col-md-4"><label class="form-label">Заголовок 1</label><input class="form-control" name="home_title_line1" value="{{ old('home_title_line1',$settings['home_title_line1'] ?? 'ШКОЛА') }}"></div>
  <div class="col-md-4"><label class="form-label">Заголовок 2</label><input class="form-control" name="home_title_line2" value="{{ old('home_title_line2',$settings['home_title_line2'] ?? 'КРЕАТИВНЫХ') }}"></div>
  <div class="col-md-4"><label class="form-label">Заголовок 3</label><input class="form-control" name="home_title_line3" value="{{ old('home_title_line3',$settings['home_title_line3'] ?? 'ИНДУСТРИЙ') }}"></div>
  <div class="col-12"><label class="form-label">Вводный текст</label><textarea class="form-control" rows="4" name="home_intro">{{ old('home_intro',$settings['home_intro'] ?? 'Здесь идеи превращаются в анимацию, музыку, дизайн, фильмы, 3D, VR и AR. Обучение строится вокруг реальных проектов и современной студийной техники.') }}</textarea></div>
  <div class="col-md-6"><label class="form-label">Основная кнопка</label><input class="form-control" name="home_primary_button" value="{{ old('home_primary_button',$settings['home_primary_button'] ?? 'Записаться на обучение') }}"></div>
  <div class="col-md-6"><label class="form-label">Вторая кнопка</label><input class="form-control" name="home_secondary_button" value="{{ old('home_secondary_button',$settings['home_secondary_button'] ?? 'Исследовать студии') }}"></div>
  <div class="col-12 mt-4"><h3>Блок студий</h3></div>
  <div class="col-md-4"><label class="form-label">Надзаголовок</label><input class="form-control" name="home_studios_eyebrow" value="{{ old('home_studios_eyebrow',$settings['home_studios_eyebrow'] ?? '6 направлений · одна экосистема') }}"></div>
  <div class="col-md-4"><label class="form-label">Заголовок</label><input class="form-control" name="home_studios_title" value="{{ old('home_studios_title',$settings['home_studios_title'] ?? 'Студии') }}"></div>
  <div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" rows="3" name="home_studios_text">{{ old('home_studios_text',$settings['home_studios_text'] ?? 'Каждая студия — отдельный цифровой мир с фотогалереей, видео, 360°-пространствами и работами учеников.') }}</textarea></div>
  <div class="col-12 mt-4"><h3>360° блок</h3></div>
  <div class="col-md-6"><label class="form-label">Заголовок</label><input class="form-control" name="home_360_title" value="{{ old('home_360_title',$settings['home_360_title'] ?? 'Зайди внутрь до первого занятия.') }}"></div>
  <div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" rows="3" name="home_360_text">{{ old('home_360_text',$settings['home_360_text'] ?? 'Панорамные 360°-сцены позволяют посмотреть студии и оборудование прямо в браузере.') }}</textarea></div>
  <div class="col-12 mt-4"><h3>Работы учеников</h3></div>
  <div class="col-md-6"><label class="form-label">Заголовок</label><input class="form-control" name="home_works_title" value="{{ old('home_works_title',$settings['home_works_title'] ?? 'Сделано здесь') }}"></div>
  <div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" rows="3" name="home_works_text">{{ old('home_works_text',$settings['home_works_text'] ?? 'Не учебные упражнения, а портфолио: ролики, сцены, треки, брендинг, AR/VR и цифровые эксперименты.') }}</textarea></div>
  <div class="col-12 mt-4"><h3>Финальный призыв</h3></div>
  <div class="col-12"><label class="form-label">Надзаголовок</label><input class="form-control" name="home_cta_eyebrow" value="{{ old('home_cta_eyebrow',$settings['home_cta_eyebrow'] ?? 'Приём документов · г. Волжск, ул. Ленина, 32') }}"></div>
  <div class="col-12"><label class="form-label">Заголовок</label><input class="form-control" name="home_cta_title" value="{{ old('home_cta_title',$settings['home_cta_title'] ?? 'Твоё первое портфолио начинается здесь.') }}"></div>
  <div class="col-md-6"><label class="form-label">Текст кнопки</label><input class="form-control" name="home_cta_button" value="{{ old('home_cta_button',$settings['home_cta_button'] ?? 'Подать заявку') }}"></div>
 </div>
</div>
<div class="tab-pane fade" id="seoSettings">
 <div class="row g-3">
  <div class="col-12"><label class="form-label">SEO Title</label><input class="form-control" name="seo_title" value="{{ old('seo_title',$settings['seo_title'] ?? 'Школа креативных индустрий · Волжск') }}"></div>
  <div class="col-12"><label class="form-label">Meta Description</label><textarea class="form-control" rows="3" name="seo_description">{{ old('seo_description',$settings['seo_description'] ?? 'Школа креативных индустрий в Волжске: анимация, 3D, дизайн, звук, электронная музыка, фото, видео, VR и AR.') }}</textarea></div>
  <div class="col-12"><label class="form-label">Keywords</label><textarea class="form-control" rows="2" name="seo_keywords">{{ old('seo_keywords',$settings['seo_keywords'] ?? 'школа креативных индустрий, Волжск, 3D, дизайн, звукорежиссура, электронная музыка, видео, VR, AR') }}</textarea></div>
  <div class="col-md-6"><label class="form-label">Robots</label><input class="form-control" name="seo_robots" value="{{ old('seo_robots',$settings['seo_robots'] ?? 'index,follow') }}"></div>
  <div class="col-md-6"><label class="form-label">Canonical URL</label><input class="form-control" name="seo_canonical" value="{{ old('seo_canonical',$settings['seo_canonical'] ?? '') }}"></div>
  <div class="col-12"><hr class="border-secondary"></div>
  <div class="col-12"><h3>OpenGraph / соцсети</h3></div>
  <div class="col-12"><label class="form-label">OG Title</label><input class="form-control" name="seo_og_title" value="{{ old('seo_og_title',$settings['seo_og_title'] ?? '') }}"></div>
  <div class="col-12"><label class="form-label">OG Description</label><textarea class="form-control" rows="3" name="seo_og_description">{{ old('seo_og_description',$settings['seo_og_description'] ?? '') }}</textarea></div>
  <div class="col-12"><label class="form-label">OG Image URL</label><input class="form-control" name="seo_og_image" value="{{ old('seo_og_image',$settings['seo_og_image'] ?? '') }}"></div>
  <div class="col-md-6"><label class="form-label">Twitter Card</label><input class="form-control" name="seo_twitter_card" value="{{ old('seo_twitter_card',$settings['seo_twitter_card'] ?? 'summary_large_image') }}"></div>
 </div>
</div>
</div>
<div class="text-end mt-4"><button class="btn btn-neon btn-lg">Сохранить настройки</button></div>
</form>
</div></section>
@endsection
