@extends('layouts.app')
@section('title','Результат · '.$attempt->quiz->title)
@section('content')
<section class="page-top"><div class="container text-center">
 <div class="eyebrow justify-content-center">Quiz result</div>
 <h1 class="display-1 fw-bold scroll-title">{{ $attempt->score }}%</h1>
 <p class="lead">{{ $attempt->passed ? 'Поздравляем! Викторина пройдена.' : 'Проходной балл пока не набран.' }}</p>
 <p class="text-white-50">{{ $attempt->quiz->title }} · проходной балл {{ $attempt->quiz->pass_score }}%</p>
 <div class="d-flex justify-content-center gap-3 flex-wrap mt-4">
  <a href="{{ route('quizzes.show',$attempt->quiz) }}" class="btn btn-ghost">Пройти ещё раз</a>
  @if($attempt->passed && $attempt->certificate_code)
   <a href="{{ route('quizzes.certificate',$attempt->certificate_code) }}" class="btn btn-neon">Открыть сертификат</a>
  @endif
 </div>
</div></section>
@endsection
