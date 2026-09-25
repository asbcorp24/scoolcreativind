@extends('layouts.app')
@section('title','Задать вопрос · ШКИ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Contact / question</div><h1 class="display-1 fw-bold scroll-title">Задать вопрос</h1><p class="lead text-white-50 col-lg-8">Напишите нам по вопросам поступления, обучения, расписания, мероприятий и работы школы.</p></div></section>

<section class="pb-5"><div class="container">
 <div class="row g-4">
  <div class="col-lg-7">
   <form method="post" action="{{ route('questions.store') }}" class="glass-card p-4 p-lg-5">@csrf
    <div class="row g-3">
     <div class="col-md-6"><label class="form-label">Ваше имя</label><input class="form-control" name="name" value="{{ old('name',auth()->user()->name ?? '') }}" required></div>
     <div class="col-md-6"><label class="form-label">Тема</label><input class="form-control" name="subject" value="{{ old('subject') }}" placeholder="Например: поступление"></div>
     <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="{{ old('email',auth()->user()->email ?? '') }}"></div>
     <div class="col-md-6"><label class="form-label">Телефон</label><input class="form-control" name="phone" value="{{ old('phone',auth()->user()->phone ?? '') }}"></div>
     <div class="col-12"><label class="form-label">Ваш вопрос</label><textarea class="form-control" rows="8" name="question" required>{{ old('question') }}</textarea></div>
     <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="consent" value="1" id="questionConsent" required><label class="form-check-label" for="questionConsent">Я согласен(а) на обработку персональных данных.</label></div></div>
     <div class="col-12"><button class="btn btn-neon btn-lg">Отправить вопрос</button></div>
    </div>
   </form>
  </div>
  <div class="col-lg-5">
   <div class="glass-card p-4 h-100">
    <div class="eyebrow">Контакты</div>
    <h3 class="display-6 fw-bold mt-3">Школа креативных индустрий</h3>
    <p class="text-white-50">г. Волжск, ул. Ленина, 32</p>
    <p><a href="tel:+78363164628">+7 (836) 316-46-28</a></p>
    <p class="text-white-50">Для официальных обращений и документов используйте данные, указанные в разделе «Документы».</p>
    <a href="{{ route('documents.index') }}" class="btn btn-ghost">Официальные документы</a>
   </div>
  </div>
 </div>
</div></section>
@endsection
