@extends('layouts.app')
@section('title','Поиск · ШКИ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Search</div>
  <h1 class="display-2 fw-bold">Поиск по сайту</h1>
  <form method="get" action="{{ route('search') }}" class="mt-4">
   <div class="input-group input-group-lg">
    <input class="form-control" name="q" value="{{ $query }}" placeholder="Новости, документы, уроки, работы, расписание..." autofocus>
    <button class="btn btn-neon">Найти</button>
   </div>
  </form>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  @if(mb_strlen($query)<2)
   <div class="glass-card p-4 text-white-50">Введите минимум 2 символа.</div>
  @else
   <div class="section-head"><div><div class="eyebrow">Results</div><h2>Найдено: {{ $results->count() }}</h2></div></div>
   <div class="row g-3">
    @forelse($results as $item)
     <div class="col-md-6">
      <a href="{{ $item['url'] }}" class="glass-card p-4 h-100 d-block text-decoration-none">
       <div class="eyebrow">{{ $item['type'] }}</div>
       <h3 class="mt-2">{{ $item['title'] }}</h3>
       @if(!empty($item['text']))<p class="text-white-50 mb-0">{{ mb_strimwidth(strip_tags($item['text']),0,220,'…') }}</p>@endif
      </a>
     </div>
    @empty
     <div class="col-12"><div class="glass-card p-4 text-white-50">Ничего не найдено.</div></div>
    @endforelse
   </div>
  @endif
 </div>
</section>
@endsection
