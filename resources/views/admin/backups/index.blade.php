@extends('layouts.app')
@section('title','Резервные копии · Админ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Система / резервирование</div>
  <h1 class="display-3 fw-bold">Резервные копии</h1>
  <p class="text-white-50 mb-0">Скачайте базу данных и загруженные пользователями файлы отдельно.</p>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
  <div class="row g-4">
   <div class="col-lg-6">
    <div class="glass-card p-4 h-100">
     <div class="eyebrow">Database</div>
     <h2 class="h3 mt-2">База данных</h2>
     <p class="text-white-50">Экспорт всех таблиц в JSON. Драйвер: <code>{{ $driver }}</code>.</p>
     <a class="btn btn-neon" href="{{ route('admin.backups.database') }}">Скачать базу</a>
    </div>
   </div>
   <div class="col-lg-6">
    <div class="glass-card p-4 h-100">
     <div class="eyebrow">Storage</div>
     <h2 class="h3 mt-2">Загруженные файлы</h2>
     <p class="text-white-50">ZIP каталога <code>storage/app/public</code>. Размер: {{ number_format($storageBytes/1024/1024,1,',',' ') }} МБ.</p>
     <a class="btn btn-neon" href="{{ route('admin.backups.files') }}">Скачать ZIP</a>
    </div>
   </div>
  </div>

  <div class="alert alert-warning mt-4">
   Резервные копии содержат персональные данные. Храните их только в защищённом месте. Файл <code>.env</code> и серверные секреты сюда не включаются.
  </div>
 </div>
</section>
@endsection
