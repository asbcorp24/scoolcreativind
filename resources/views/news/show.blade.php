@extends('layouts.app')
@section('title',$post->title.' · ШКИ')
@section('content')
<section class="page-top"><div class="container"><a href="{{ route('news.index') }}" class="backlink">← Новости</a><div class="eyebrow mt-4">{{ optional($post->published_at)->format('d.m.Y') }}</div><h1 class="display-2 fw-bold mt-3">{{ $post->title }}</h1><p class="lead text-white-50">{{ $post->excerpt }}</p></div></section>
@if($post->cover)<div class="container"><img src="{{ $post->cover }}" class="w-100 rounded-4" alt=""></div>@endif
<section class="section-space pt-5"><div class="container"><div class="studio-description col-xl-9">{!! nl2br(e($post->body)) !!}</div></div></section>
@endsection
