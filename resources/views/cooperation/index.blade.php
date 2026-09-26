@extends('layouts.app')
@section('title','Сотрудничество · ШКИ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Сотрудничество</div>
  <h1 class="display-1 fw-bold">Создаём вместе</h1>
  <p class="lead text-white-50 col-lg-8">Партнёрства, совместные проекты и участие специалистов в развитии Школы креативных индустрий.</p>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  <div class="cooperation-layout">
   <aside class="cooperation-tabs">
    <a href="#offers" class="active">Предложения</a>
    <a href="#partners">Партнёры</a>
    <a href="#projects">Совместные проекты</a>
    <a href="#form">Заполнить анкету</a>
   </aside>

   <div class="cooperation-main">
    <section id="offers" class="cooperation-section">
     <div class="section-head compact"><div><div class="eyebrow">Предложения</div><h2>Варианты сотрудничества</h2></div></div>
     <div class="cooperation-actions">
      <article><div><span>01</span><h3>Стать партнёром</h3><p>Совместные образовательные, технологические и творческие инициативы.</p></div><a href="#form" data-role-link="partner" class="btn btn-neon">Подать заявку</a></article>
      <article><div><span>02</span><h3>Стать куратором</h3><p>Помогайте командам и ученикам развивать проекты и профессиональные навыки.</p></div><a href="#form" data-role-link="curator" class="btn btn-neon">Подать заявку</a></article>
      <article><div><span>03</span><h3>Стать преподавателем</h3><p>Передавайте практический опыт и участвуйте в образовательной программе.</p></div><a href="#form" data-role-link="teacher" class="btn btn-neon">Подать заявку</a></article>
     </div>

     @if($proposals->count())
     <div class="cooperation-cards mt-5">
      @foreach($proposals as $item)
       <article class="glass-card p-4">@if($item->image_url)<img src="{{ $item->image_url }}" class="cooperation-thumb" alt="{{ $item->title }}">@endif<h3>{{ $item->title }}</h3><p>{{ $item->description }}</p>@if($item->url)<a href="{{ $item->url }}" target="_blank" rel="noopener">Подробнее ↗</a>@endif</article>
      @endforeach
     </div>
     @endif
    </section>

    <section id="partners" class="cooperation-section">
     <div class="section-head compact"><div><div class="eyebrow">Партнёры</div><h2>С кем мы работаем</h2></div></div>
     <div class="cooperation-cards">
      @forelse($partners as $item)
       <article class="glass-card p-4">@if($item->image_url)<img src="{{ $item->image_url }}" class="cooperation-thumb" alt="{{ $item->title }}">@endif<h3>{{ $item->title }}</h3><p>{{ $item->description }}</p>@if($item->url)<a href="{{ $item->url }}" target="_blank" rel="noopener">Сайт партнёра ↗</a>@endif</article>
      @empty
       <div class="text-white-50">Информация о партнёрах публикуется администрацией школы.</div>
      @endforelse
     </div>
    </section>

    <section id="projects" class="cooperation-section">
     <div class="section-head compact"><div><div class="eyebrow">Проекты</div><h2>Совместные проекты</h2></div></div>
     <div class="cooperation-cards">
      @forelse($projects as $item)
       <article class="glass-card p-4">@if($item->image_url)<img src="{{ $item->image_url }}" class="cooperation-thumb" alt="{{ $item->title }}">@endif<h3>{{ $item->title }}</h3><p>{{ $item->description }}</p>@if($item->url)<a href="{{ $item->url }}" target="_blank" rel="noopener">О проекте ↗</a>@endif</article>
      @empty
       <div class="text-white-50">Опубликованных совместных проектов пока нет.</div>
      @endforelse
     </div>
    </section>

    <section id="form" class="cooperation-section">
     <div class="section-head compact"><div><div class="eyebrow">Анкета</div><h2>Предложить сотрудничество</h2></div></div>
     <form method="post" action="{{ route('cooperation.store') }}" class="glass-card p-4 p-lg-5">@csrf
      <div class="row g-3">
       <div class="col-md-6"><label class="form-label">Кем хотите стать</label><select class="form-select" name="role" id="cooperationRole"><option value="partner">Партнёром</option><option value="curator">Куратором</option><option value="teacher">Преподавателем</option><option value="other">Другое предложение</option></select></div>
       <div class="col-md-6"><label class="form-label">ФИО *</label><input class="form-control" name="name" value="{{ old('name') }}" required></div>
       <div class="col-md-6"><label class="form-label">Организация</label><input class="form-control" name="organization" value="{{ old('organization') }}"></div>
       <div class="col-md-6"><label class="form-label">Сайт</label><input type="url" class="form-control" name="website" value="{{ old('website') }}"></div>
       <div class="col-md-6"><label class="form-label">Телефон</label><input class="form-control" name="phone" value="{{ old('phone') }}"></div>
       <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="{{ old('email') }}"></div>
       <div class="col-12"><label class="form-label">Расскажите о предложении</label><textarea class="form-control" rows="6" name="message">{{ old('message') }}</textarea></div>
       <div class="col-12 text-end"><button class="btn btn-neon btn-lg">Отправить заявку</button></div>
      </div>
     </form>
    </section>
   </div>
  </div>
 </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const role=document.getElementById('cooperationRole');
 document.querySelectorAll('[data-role-link]').forEach(a=>a.addEventListener('click',()=>{ if(role)role.value=a.dataset.roleLink; }));
});
</script>
@endpush
