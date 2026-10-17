@extends('layouts.app')
@section('title','Уведомления · ШКИ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Notifications</div>
  <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
   <div>
    <h1 class="display-2 fw-bold">Уведомления</h1>
    <p class="text-white-50 mb-0">Объявления школы и ваших учебных групп.</p>
   </div>
   <form method="post" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-ghost">Прочитать всё</button></form>
  </div>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  <div class="row g-3">
   @forelse($announcements as $notice)
    <div class="col-lg-6">
     <article class="glass-card p-4 h-100 {{ $notice->is_read ? 'opacity-75' : '' }}">
      <div class="d-flex justify-content-between gap-3 align-items-start">
       <div>
        <div class="eyebrow">{{ $notice->group->name ?? 'ШКИ' }} · {{ optional($notice->published_at)->format('d.m.Y H:i') }}</div>
        <h3>{{ $notice->title }}</h3>
       </div>
       @if(!$notice->is_read)<span class="badge bg-danger">Новое</span>@else<span class="badge-soft">Прочитано</span>@endif
      </div>
      <p class="text-white-50">{{ $notice->body }}</p>
      @if(!$notice->is_read)
       <form method="post" action="{{ route('notifications.read',$notice) }}">@csrf<button class="btn btn-sm btn-neon">Отметить прочитанным</button></form>
      @endif
     </article>
    </div>
   @empty
    <div class="col-12"><div class="glass-card p-4 text-white-50">Уведомлений пока нет.</div></div>
   @endforelse
  </div>

  @if($announcements->hasPages())<div class="mt-4">{{ $announcements->links() }}</div>@endif
 </div>
</section>
@endsection
