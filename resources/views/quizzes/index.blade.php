@extends('layouts.app')
@section('title','Викторины · ШКИ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Quiz lab</div><h1 class="display-1 fw-bold scroll-title">Викторины</h1><p class="lead text-white-50 col-lg-8">Проверь знания, набери проходной балл и получи персональный онлайн-сертификат.</p></div></section>
<section class="pb-5"><div class="container">
<div class="row g-4">
@forelse($quizzes as $quiz)
 @php($attempt=$attempts->get($quiz->id))
 <div class="col-md-6 col-xl-4">
  <article class="glass-card p-4 h-100 tilt-card">
   <div class="eyebrow">{{ count($quiz->questions_json ?? []) }} вопросов · проходной {{ $quiz->pass_score }}%</div>
   <h3 class="display-6 fw-bold mt-3">{{ $quiz->title }}</h3>
   <p class="text-white-50">{{ $quiz->description }}</p>
   @if($attempt)
    <div class="quiz-result-mini mb-3"><strong>{{ $attempt->score }}%</strong><span>{{ $attempt->passed ? 'Сертификат получен' : 'Можно попробовать ещё раз' }}</span></div>
   @endif
   @auth
    <a class="btn btn-neon" href="{{ route('quizzes.show',$quiz) }}">{{ $attempt ? 'Пройти ещё раз' : 'Начать викторину' }}</a>
    @if($attempt && $attempt->passed && $attempt->certificate_code)
     <a class="btn btn-ghost" href="{{ route('quizzes.certificate',$attempt->certificate_code) }}">Сертификат</a>
    @endif
   @else
    <a class="btn btn-neon" href="{{ route('login') }}">Войти и пройти</a>
   @endauth
  </article>
 </div>
@empty
 <div class="text-white-50">Доступных викторин нет.</div>
@endforelse
</div>
</div></section>
@endsection
