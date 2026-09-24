@extends('layouts.app')
@section('title','Новости · ШКИ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Школа сегодня</div><h1 class="display-1 fw-bold">Новости</h1></div></section>
<section class="pb-5 mb-5"><div class="container"><div class="row g-4">@foreach($posts as $post)<div class="col-md-6 col-xl-4"><a href="{{ route('news.show',$post) }}" class="news-card"><div class="news-cover" @if($post->cover) style="background-image:url('{{ $post->cover }}')" @endif></div><div class="news-body"><div class="small text-white-50">{{ optional($post->published_at)->format('d.m.Y') }}</div><h3>{{ $post->title }}</h3><p>{{ $post->excerpt }}</p></div></a></div>@endforeach</div><div class="mt-5">{{ $posts->links() }}</div></div></section>
@endsection
