@extends('portfolio.show')
@section('title','Моё портфолио · ШКИ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">My portfolio</div><div class="d-flex justify-content-between align-items-end gap-3 flex-wrap"><div><h1 class="display-2 fw-bold">Моё портфолио</h1><p class="text-white-50">Публичность: {{ $profile->is_public?'включена':'выключена' }}</p></div>@if($profile->is_public)<a class="btn btn-ghost" href="{{ route('portfolio.show',$profile) }}">Открыть публичную страницу ↗</a>@endif</div></div></section>
@parent
@endsection
