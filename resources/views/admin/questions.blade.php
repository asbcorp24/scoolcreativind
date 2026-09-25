@extends('layouts.app')
@section('title','Вопросы · Админ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Админ / обращения</div><div class="d-flex justify-content-between align-items-end gap-3 flex-wrap"><h1 class="display-3 fw-bold mb-0">Вопросы</h1><a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">← Админка</a></div></div></section>

<section class="pb-5"><div class="container">
 <div class="d-flex gap-2 flex-wrap mb-4">
  @foreach([''=>'Все','new'=>'Новые','processing'=>'В работе','answered'=>'Отвечено','closed'=>'Закрыто'] as $key=>$label)
   <a href="{{ route('admin.questions',array_filter(['status'=>$key])) }}" class="btn {{ $activeStatus===$key || (!$activeStatus && $key==='') ? 'btn-neon' : 'btn-ghost' }}">{{ $label }}</a>
  @endforeach
 </div>

 <div class="d-grid gap-3">
 @forelse($questions as $q)
  <article class="glass-card p-4">
   <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
    <div>
     <div class="eyebrow">{{ $q->created_at->format('d.m.Y H:i') }}</div>
     <h3 class="mt-2 mb-1">{{ $q->subject ?: 'Вопрос' }}</h3>
     <div class="small text-white-50">{{ $q->name }} · {{ $q->email ?: 'без email' }} · {{ $q->phone ?: 'без телефона' }}</div>
    </div>
    <span class="badge-soft">{{ ['new'=>'Новый','processing'=>'В работе','answered'=>'Отвечено','closed'=>'Закрыто'][$q->status] ?? $q->status }}</span>
   </div>

   <div class="mt-3 p-3 rounded-3" style="background:rgba(255,255,255,.025)">{{ $q->question }}</div>

   <form method="post" action="{{ route('admin.questions.update',$q) }}" class="mt-3">@csrf @method('PATCH')
    <div class="row g-3">
     <div class="col-md-4"><label class="form-label">Статус</label><select class="form-select" name="status">@foreach(['new'=>'Новый','processing'=>'В работе','answered'=>'Отвечено','closed'=>'Закрыто'] as $k=>$v)<option value="{{ $k }}" @selected($q->status===$k)>{{ $v }}</option>@endforeach</select></div>
     <div class="col-12"><label class="form-label">Ответ</label><textarea class="form-control" rows="5" name="answer">{{ $q->answer }}</textarea></div>
     <div class="col-12 d-flex justify-content-between gap-2 flex-wrap">
      <button class="btn btn-neon">Сохранить</button>
   </form>
      <form method="post" action="{{ route('admin.questions.delete',$q) }}" onsubmit="return confirm('Удалить обращение?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Удалить</button></form>
     </div>
    </div>
  </article>
 @empty
  <div class="glass-card p-4 text-white-50">Нет обращений.</div>
 @endforelse
 </div>

 @if($questions->hasPages())<div class="mt-5">{{ $questions->links() }}</div>@endif
</div></section>
@endsection
