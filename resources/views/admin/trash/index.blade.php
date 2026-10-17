@extends('layouts.app')
@section('title','Корзина · Админ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Система / безопасное удаление</div>
  <h1 class="display-3 fw-bold">Корзина</h1>
  <p class="text-white-50 mb-0">Восстановление удалённых занятий, документов и новостей. Окончательное удаление необратимо.</p>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  @foreach([
   ['title'=>'Расписание','type'=>'schedule','items'=>$schedule],
   ['title'=>'Документы и файлы','type'=>'document','items'=>$documents],
   ['title'=>'Новости','type'=>'news','items'=>$news],
  ] as $section)
   <div class="section-head mt-4"><div><div class="eyebrow">Trash</div><h2>{{ $section['title'] }}</h2></div><span class="badge-soft">{{ $section['items']->count() }}</span></div>
   <div class="glass-card p-4 mb-5">
    @forelse($section['items'] as $item)
     <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap py-3 border-bottom border-secondary-subtle">
      <div>
       <strong>{{ $item->title }}</strong>
       <div class="small text-white-50">Удалено: {{ optional($item->deleted_at)->format('d.m.Y H:i') }}
        @if($section['type']==='schedule' && $item->group) · {{ $item->group->name }} @endif
       </div>
      </div>
      <div class="d-flex gap-2">
       <form method="post" action="{{ route('admin.trash.restore',[$section['type'],$item->id]) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-neon">Восстановить</button></form>
       <form method="post" action="{{ route('admin.trash.purge',[$section['type'],$item->id]) }}" onsubmit="return confirm('Удалить окончательно? Восстановление будет невозможно.')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить навсегда</button></form>
      </div>
     </div>
    @empty
     <div class="text-white-50">Корзина раздела пуста.</div>
    @endforelse
   </div>
  @endforeach
 </div>
</section>
@endsection
