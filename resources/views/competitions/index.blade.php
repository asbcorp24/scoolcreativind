@extends('layouts.app')
@section('title','Конкурсы и достижения · ШКИ')
@section('content')

<section class="competition-hero">
 <div class="competition-hero-grid"></div>
 <div class="container position-relative">
  <div class="competition-hero-copy">
   <div class="eyebrow">Creative challenges / awards</div>
   <h1 class="competition-title scroll-title">Конкурсы.<br><span>Участие.</span><br>Достижения.</h1>
   <p class="competition-lead">Творческие соревнования, фестивали и проекты, где можно заявить о себе, отправить работу и получить новый опыт.</p>
   <div class="competition-hero-actions">
    @auth
     <a href="#competitions-list" class="btn btn-neon">Выбрать конкурс</a>
     <a href="{{ route('cabinet') }}" class="btn btn-ghost">Мои участия</a>
    @else
     <a href="{{ route('login') }}" class="btn btn-neon">Войти для участия</a>
     <a href="#competitions-list" class="btn btn-ghost">Смотреть конкурсы</a>
    @endauth
   </div>
  </div>

  <div class="competition-orbit-card tilt-card">
   <div class="competition-orbit-ring"></div>
   <div class="competition-orbit-ring r2"></div>
   <div class="competition-orbit-core">✦</div>
   <div class="competition-orbit-meta">
    <span>{{ $competitions->count() }}</span>
    <small>активных конкурсов</small>
   </div>
  </div>
 </div>
</section>

<section id="competitions-list" class="section-space competition-list-section">
 <div class="container">
  <div class="section-head">
   <div>
    <div class="eyebrow">Open calls</div>
    <h2>Конкурсы</h2>
   </div>
   <p>Выберите конкурс, зарегистрируйтесь и отправьте работу прямо через личный кабинет сайта.</p>
  </div>

  <div class="row g-4">
  @forelse($competitions as $c)
   @php($reg=$registrations->get($c->id))
   <div class="col-md-6">
    <article class="competition-card tilt-card">
     <div class="competition-card-top">
      <div class="competition-index">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</div>
      <div class="competition-date">
       {{ optional($c->starts_on)->format('d.m.Y') }}
       @if($c->ends_on)<span>→</span>{{ $c->ends_on->format('d.m.Y') }}@endif
      </div>
     </div>

     <div class="competition-card-body">
      <div class="eyebrow">{{ $c->organizer ?: 'Творческий конкурс' }}</div>
      <h3>{{ $c->title }}</h3>
      <p>{{ $c->description }}</p>

      <div class="competition-meta-row">
       @if($c->location)<span>⌖ {{ $c->location }}</span>@endif
       <span>◎ участников: {{ $c->registrations_count }}</span>
      </div>

      <div class="competition-actions">
       @if($c->url)<a href="{{ $c->url }}" class="btn btn-ghost" target="_blank">Подробнее ↗</a>@endif

       @auth
        @if(!$reg)
         <form method="post" action="{{ route('competitions.register',$c) }}">@csrf<button class="btn btn-neon">Записаться</button></form>
        @else
         <span class="competition-status">{{ ['registered'=>'Вы записаны','submitted'=>'Работа отправлена','reviewed'=>'Проверено','cancelled'=>'Отменено'][$reg->status] ?? $reg->status }}</span>
         @if($reg->status==='registered')
          <form method="post" action="{{ route('competitions.cancel',$c) }}">@csrf @method('DELETE')<button class="btn btn-ghost">Отменить запись</button></form>
         @endif
        @endif
       @else
        <a href="{{ route('login') }}" class="btn btn-neon">Войти для участия</a>
       @endauth
      </div>

      @auth
      @if($reg && in_array($reg->status,['registered','submitted']))
       <form method="post" enctype="multipart/form-data" action="{{ route('competitions.submit',$c) }}" class="competition-submit">@csrf
        <div class="eyebrow">Моя работа</div>
        <textarea class="form-control mb-2" rows="3" name="submission_text" placeholder="Кратко расскажите о работе">{{ old('submission_text',$reg->submission_text) }}</textarea>
        <input class="form-control mb-2" name="submission_url" value="{{ old('submission_url',$reg->submission_url) }}" placeholder="Ссылка на проект / видео / облако">
        <input type="file" class="form-control mb-2" name="submission_file">
        @if($reg->file_url)<div class="small mb-2"><a href="{{ $reg->file_url }}" target="_blank">Текущий файл: {{ $reg->file_name }}</a></div>@endif
        <button class="btn btn-neon">{{ $reg->status==='submitted' ? 'Обновить работу' : 'Отправить работу' }}</button>
       </form>
      @endif
      @endauth
     </div>
    </article>
   </div>
  @empty
   <div class="col-12">
    <div class="competition-empty tilt-card">
     <div class="competition-empty-visual">
      <span class="competition-empty-orbit"></span>
      <strong>00</strong>
     </div>
     <div>
      <div class="eyebrow">Next challenge loading</div>
      <h3>Новые конкурсы скоро появятся</h3>
      <p>Мы готовим подборку творческих соревнований и фестивалей. Как только конкурс будет опубликован, здесь появится карточка с условиями и кнопкой участия.</p>
      @auth
       <a href="{{ route('cabinet') }}" class="btn btn-ghost">Перейти в кабинет</a>
      @else
       <a href="{{ route('login') }}" class="btn btn-neon">Войти на сайт</a>
      @endauth
     </div>
    </div>
   </div>
  @endforelse
  </div>
 </div>
</section>

<section class="section-space pt-0 achievements-section">
 <div class="container">
  <div class="section-head">
   <div>
    <div class="eyebrow">Hall of fame</div>
    <h2>Наши достижения</h2>
   </div>
   <p>Награды, дипломы, финалы и победы учеников школы.</p>
  </div>

  <div class="achievement-grid">
  @forelse($achievements as $a)
   <article class="achievement-card reveal tilt-card">
    <div class="achievement-medal">★</div>
    <div>
     <div class="small text-white-50">{{ optional($a->awarded_at)->format('d.m.Y') }}</div>
     <h4>{{ $a->student->user->name ?? $a->title }}</h4>
     <strong>{{ $a->result ?: $a->title }}</strong>
     <div class="text-white-50">{{ $a->competition->title ?? $a->level }}</div>
    </div>
   </article>
  @empty
   <div class="achievement-empty">
    <div class="achievement-empty-icon">◇</div>
    <div>
     <div class="eyebrow">First win is ahead</div>
     <h3>Здесь появится история побед</h3>
     <p>Когда ученики начнут получать дипломы, призовые места и специальные награды, они автоматически появятся в этой галерее достижений.</p>
    </div>
   </div>
  @endforelse
  </div>
 </div>
</section>
@endsection
