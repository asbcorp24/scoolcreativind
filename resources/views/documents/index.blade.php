@extends('layouts.app')
@section('title','Документы · ШКИ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Official documents</div><h1 class="display-1 fw-bold scroll-title">Документы</h1><p class="lead text-white-50 col-lg-8">Официальные документы Школы креативных индустрий.</p></div></section>

<section class="pb-5"><div class="container">
 <div class="d-flex gap-2 flex-wrap mb-4">
  <a href="{{ route('documents.index') }}" class="btn {{ empty($activeCategory) ? 'btn-neon' : 'btn-ghost' }}">Все документы</a>
  @foreach($categories as $category)
   <a href="{{ route('documents.index',['category'=>$category]) }}" class="btn {{ $activeCategory===$category ? 'btn-neon' : 'btn-ghost' }}">{{ $category }}</a>
  @endforeach
 </div>

 <div class="document-list">
 @forelse($documents as $doc)
  <article class="official-document-card tilt-card">
   <div class="official-document-icon">PDF</div>
   <div class="official-document-main">
    <div class="eyebrow">{{ $doc->category ?: 'Документ' }}</div>
    <h3>{{ $doc->title }}</h3>
    @if($doc->description)<p>{{ $doc->description }}</p>@endif
    <div class="official-document-meta">
     @if($doc->document_number)<span>№ {{ $doc->document_number }}</span>@endif
     @if($doc->document_date)<span>{{ $doc->document_date->format('d.m.Y') }}</span>@endif
     @if($doc->human_file_size)<span>{{ $doc->human_file_size }}</span>@endif
    </div>
   </div>
   <a href="{{ $doc->file_url }}" target="_blank" rel="noopener" class="btn btn-neon">Открыть PDF</a>
  </article>
 @empty
  <div class="glass-card p-4 text-white-50">Нет опубликованных документов.</div>
 @endforelse
 </div>

 @if($documents->hasPages())<div class="mt-5">{{ $documents->links() }}</div>@endif
</div></section>
@endsection
