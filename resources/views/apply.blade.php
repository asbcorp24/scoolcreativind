@extends('layouts.app')
@section('title','Поступление · ШКИ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Поступление</div>
  <h1 class="display-1 fw-bold mt-3">Начни создавать.</h1>
  <p class="lead text-white-50 col-lg-7">Оставьте заявку на обучение. После обработки мы свяжемся с вами и расскажем о наборе, свободных местах и необходимых документах.</p>
 </div>
</section>
<section class="pb-5 mb-5">
 <div class="container">
  <form class="form-shell" method="post" action="{{ route('apply.store') }}">
   @csrf
   @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
   <div class="row g-4">
    <div class="col-md-7"><label class="form-label">ФИО</label><input class="form-control form-control-lg" name="name" value="{{ old('name',auth()->user()->name ?? '') }}" required></div>
    <div class="col-md-5"><label class="form-label">Дата рождения</label><input type="date" class="form-control form-control-lg" name="birth_date" value="{{ old('birth_date') }}"></div>
    <div class="col-md-6"><label class="form-label">Телефон</label><input class="form-control form-control-lg" name="phone" value="{{ old('phone',auth()->user()->phone ?? '') }}" required></div>
    <div class="col-md-6"><label class="form-label">E-mail</label><input type="email" class="form-control form-control-lg" name="email" value="{{ old('email',auth()->user()->email ?? '') }}"></div>
    <div class="col-12"><label class="form-label">Интересующая студия</label><select class="form-select form-select-lg" name="studio_id"><option value="">Пока не определился / несколько направлений</option>@foreach($studios as $s)<option value="{{ $s->id }}" @selected(old('studio_id',request('studio'))==$s->id)>{{ $s->title }}</option>@endforeach</select></div>
    <div class="col-12"><label class="form-label">Комментарий</label><textarea class="form-control" rows="5" name="message" placeholder="Что вам интересно? Есть ли уже опыт?">{{ old('message') }}</textarea></div>
    <div class="col-12 d-flex justify-content-between align-items-center gap-3 flex-wrap"><small class="text-white-50">Адрес: г. Волжск, ул. Ленина, 32 · +7 (836) 316-46-28</small><button class="btn btn-neon btn-lg">Отправить заявку</button></div>
   </div>
  </form>
 </div>
</section>
@endsection
