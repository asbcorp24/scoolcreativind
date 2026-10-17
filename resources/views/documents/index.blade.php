@extends('layouts.app')
@section('title','Документы, файлы и ссылки · ШКИ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Resources</div><h1 class="display-1 fw-bold scroll-title">Документы, файлы и ссылки</h1><p class="lead text-white-50 col-lg-8">Документы ШКИ, приложения для работы, учебные файлы и полезные ссылки.</p></div></section>

<section class="pb-5"><div class="container">
 <div class="d-flex gap-2 flex-wrap mb-3">
  <a href="{{ route('documents.index',array_filter(['category'=>$activeCategory])) }}" class="btn {{ empty($activeType) ? 'btn-neon' : 'btn-ghost' }}">Все</a>
  @foreach(['document'=>'Документы','application'=>'Приложения','file'=>'Файлы','link'=>'Ссылки'] as $type=>$label)
   <a href="{{ route('documents.index',array_filter(['type'=>$type,'category'=>$activeCategory])) }}" class="btn {{ $activeType===$type ? 'btn-neon' : 'btn-ghost' }}">{{ $label }}</a>
  @endforeach
 </div>

 @if($categories->count())
 <div class="d-flex gap-2 flex-wrap mb-4">
  <a href="{{ route('documents.index',array_filter(['type'=>$activeType])) }}" class="btn {{ empty($activeCategory) ? 'btn-neon' : 'btn-ghost' }}">Все категории</a>
  @foreach($categories as $category)
   <a href="{{ route('documents.index',array_filter(['type'=>$activeType,'category'=>$category])) }}" class="btn {{ $activeCategory===$category ? 'btn-neon' : 'btn-ghost' }}">{{ $category }}</a>
  @endforeach
 </div>
 @endif

 <div class="document-list">
 @forelse($documents as $doc)
  <article class="official-document-card tilt-card">
   <div class="official-document-icon">{{ $doc->type_icon }}</div>
   <div class="official-document-main">
    <div class="eyebrow">{{ $doc->type_label }}@if($doc->category) · {{ $doc->category }}@endif</div>
    <h3>{{ $doc->title }}</h3>
    @if($doc->description)<p>{{ $doc->description }}</p>@endif
    <div class="official-document-meta">
     @if($doc->document_number)<span>№ {{ $doc->document_number }}</span>@endif
     @if($doc->document_date)<span>{{ $doc->document_date->format('d.m.Y') }}</span>@endif
     @if($doc->human_file_size)<span>{{ $doc->human_file_size }}</span>@endif
     @if($doc->file_name)<span>{{ $doc->file_name }}</span>@endif
    </div>
   </div>
   @if($doc->target_url)
    <a href="{{ $doc->target_url }}" target="_blank" rel="noopener" class="btn btn-neon">
      {{ $doc->button_label ?: ($doc->item_type==='application' ? 'Скачать' : ($doc->item_type==='link' ? 'Перейти' : 'Открыть')) }}
    </a>
   @endif
  </article>
 @empty
  <div class="glass-card p-4 text-white-50">В этом разделе пока нет опубликованных материалов.</div>
 @endforelse
 </div>

 @if($documents->hasPages())<div class="mt-5">{{ $documents->links() }}</div>@endif
</div></section>
@endsection
