@extends('layouts.app')
@section('title','Работы учеников · Админ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Админ / портфолио</div>
  <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
   <div>
    <h1 class="display-3 fw-bold m-0">Работы учеников</h1>
    <p class="text-white-50 mt-2 mb-0">Карточка работы хранит описание и автора, а фото, 360°, 3D, видео и звук добавляются через медиагалерею.</p>
   </div>
   <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">← Админка</a>
  </div>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  @if($errors->any())
   <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <form class="form-shell mb-5" method="post" action="{{ route('admin.projects.save') }}">
   @csrf
   <div class="row g-3">
    <div class="col-md-6">
     <label class="form-label">Ученик *</label>
     <select class="form-select" name="student_profile_id" required>
      <option value="">Выберите ученика</option>
      @foreach($profiles as $profile)
       <option value="{{ $profile->id }}" @selected(old('student_profile_id')==$profile->id)>{{ $profile->user->name ?? ('Ученик #'.$profile->id) }}</option>
      @endforeach
     </select>
    </div>

    <div class="col-md-6">
     <label class="form-label">Студия</label>
     <select class="form-select" name="studio_id">
      <option value="">Без привязки</option>
      @foreach($studios as $s)<option value="{{ $s->id }}" @selected(old('studio_id')==$s->id)>{{ $s->title }}</option>@endforeach
     </select>
    </div>

    <div class="col-md-7"><label class="form-label">Название работы *</label><input class="form-control" name="title" value="{{ old('title') }}" required></div>
    <div class="col-md-3">
     <label class="form-label">Тип / направление</label>
     <select class="form-select" name="type" required>
      @foreach(['project'=>'Проект','design'=>'Дизайн','3d'=>'3D','vr-ar'=>'VR / AR','photo'=>'Фото','video'=>'Видео','audio'=>'Звук','music'=>'Музыка','prototype'=>'Прототип'] as $key=>$label)
       <option value="{{ $key }}" @selected(old('type','project')===$key)>{{ $label }}</option>
      @endforeach
     </select>
    </div>
    <div class="col-md-2"><label class="form-label">Дата</label><input type="date" class="form-control" name="completed_at" value="{{ old('completed_at',date('Y-m-d')) }}"></div>

    <div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" rows="4" name="description">{{ old('description') }}</textarea></div>
    <div class="col-md-6"><label class="form-label">Ссылка на проект</label><input class="form-control" name="project_url" value="{{ old('project_url') }}" placeholder="https://..."></div>
    <div class="col-md-6"><label class="form-label">Ссылка на видео</label><input class="form-control" name="video_url" value="{{ old('video_url') }}" placeholder="https://..."></div>

    <div class="col-md-4 d-flex align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_public" value="1" id="public" checked><label class="form-check-label" for="public">Показывать на сайте</label></div></div>
    <div class="col-md-4 d-flex align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" value="1" id="featured"><label class="form-check-label" for="featured">Показывать на главной</label></div></div>
    <div class="col-md-4 text-end"><button class="btn btn-neon">Создать и добавить медиа →</button></div>
   </div>
  </form>

  <div class="glass-card p-4">
   <div class="d-flex justify-content-between align-items-center mb-4"><h3 class="mb-0">Работы</h3><span class="badge-soft">{{ $projects->count() }}</span></div>
   <div class="table-responsive">
    <table class="table admin-table align-middle">
     <thead><tr><th>Работа</th><th>Ученик</th><th>Студия</th><th>Медиа</th><th>Сайт</th><th></th></tr></thead>
     <tbody>
      @forelse($projects as $p)
       <tr>
        <td><strong>{{ $p->title }}</strong><div class="small text-white-50">{{ $p->type }} @if($p->completed_at) · {{ $p->completed_at->format('d.m.Y') }}@endif</div></td>
        <td>{{ $p->student->user->name ?? '—' }}</td>
        <td>{{ $p->studio->title ?? '—' }}</td>
        <td><span class="badge-soft">{{ $p->media_count ?? $p->media()->count() }}</span></td>
        <td>{{ $p->is_public ? 'Да' : 'Нет' }}</td>
        <td class="text-end">
         <div class="d-flex gap-2 justify-content-end">
          <a class="btn btn-sm btn-neon" href="{{ route('admin.projects.media',$p) }}">Медиагалерея</a>
          <form method="post" action="{{ route('admin.projects.delete',$p) }}" onsubmit="return confirm('Удалить работу?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form>
         </div>
        </td>
       </tr>
      @empty
       <tr><td colspan="6" class="text-white-50">Работ пока нет.</td></tr>
      @endforelse
     </tbody>
    </table>
   </div>
  </div>
 </div>
</section>
@endsection
