@extends('layouts.app')
@section('title',$quiz->title.' · Викторина')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Quiz</div><h1 class="display-2 fw-bold scroll-title">{{ $quiz->title }}</h1><p class="lead text-white-50">{{ $quiz->description }}</p></div></section>
<section class="pb-5"><div class="container">
<form method="post" action="{{ route('quizzes.submit',$quiz) }}" class="quiz-shell">@csrf
 @foreach($quiz->questions_json ?? [] as $i=>$q)
 <article class="glass-card p-4 mb-4 tilt-card">
  <div class="eyebrow">Вопрос {{ str_pad($i+1,2,'0',STR_PAD_LEFT) }}</div>
  <h3 class="mt-3">{{ $q['question'] ?? '' }}</h3>
  <div class="quiz-options mt-4">
   @foreach(($q['options'] ?? []) as $key=>$label)
    <label class="quiz-option">
     <input type="radio" name="answers[{{ $i }}]" value="{{ $key }}" required>
     <span class="quiz-option-mark">{{ strtoupper($key) }}</span>
     <span>{{ $label }}</span>
    </label>
   @endforeach
  </div>
 </article>
 @endforeach
 <div class="text-center"><button class="btn btn-neon btn-lg">Завершить викторину</button></div>
</form>
</div></section>
@endsection
