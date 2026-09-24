@extends('layouts.app')
@section('title','Оборудование · ШКИ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Creative technology</div><h1 class="display-1 fw-bold">Оборудование</h1><p class="lead text-white-50 col-lg-7">Не просто классы, а настоящие рабочие станции: камеры, свет, звук, графика, VR и профессиональный софт.</p></div></section>
<section class="pb-5 mb-5"><div class="container-fluid px-lg-5"><div class="equipment-grid">
@forelse($items as $item)
<article class="equipment-card reveal">
 <div class="equipment-image">@if($item->image_url)<img src="{{ $item->image_url }}" alt="{{ $item->title }}">@else<div class="equipment-noise"></div>@endif</div>
 <div class="equipment-info">
  <div class="d-flex justify-content-between gap-3"><div class="eyebrow">{{ $item->category ?: 'Equipment' }}</div>@if($item->studio)<span class="equipment-studio">{{ $item->studio->title }}</span>@endif</div>
  <h3>{{ $item->title }}</h3>
  @if($item->brand || $item->model)<div class="equipment-model">{{ trim(($item->brand ?? '').' '.($item->model ?? '')) }}</div>@endif
  @if($item->description)<p>{{ $item->description }}</p>@endif
  @if(is_array($item->specs) && count($item->specs))
   <div class="specs-list">@foreach($item->specs as $k=>$v)<div><span>{{ $k }}</span><strong>{{ $v }}</strong></div>@endforeach</div>
  @endif
 </div>
</article>
@empty
<div class="text-white-50">Каталог оборудования пока пуст.</div>
@endforelse
</div></div></section>
@endsection
