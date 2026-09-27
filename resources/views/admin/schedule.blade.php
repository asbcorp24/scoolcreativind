@extends('layouts.app')
@section('title','Расписание · Админ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Админ / расписание</div>
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
   <div>
    <h1 class="display-3 fw-bold m-0">Расписание</h1>
    <p class="text-white-50 mt-2 mb-0">Добавляйте одно занятие или создавайте серию по выбранному дню недели.</p>
   </div>
   <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">← Админка</a>
  </div>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  @if($errors->any())
   <div class="alert alert-danger">
    <strong>Проверьте поля:</strong>
    <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
   </div>
  @endif

  <div class="schedule-admin-grid mb-5">
   <div class="schedule-admin-card">
    <div class="schedule-admin-card-head">
     <div>
      <div class="eyebrow">Один урок</div>
      <h3>Добавить занятие</h3>
     </div>
     <span class="schedule-mode-badge">1 дата</span>
    </div>

    <form method="post" action="{{ route('admin.schedule.save') }}">@csrf
     <div class="row g-3">
      <div class="col-md-7"><label class="form-label">Занятие</label><input class="form-control" name="title" value="{{ old('title') }}" required></div>
      <div class="col-md-5"><label class="form-label">Студия</label><select class="form-select" name="studio_id"><option value="">Общее</option>@foreach($studios as $s)<option value="{{ $s->id }}" @selected(old('studio_id')==$s->id)>{{ $s->title }}</option>@endforeach</select></div>

      <div class="col-md-6"><label class="form-label">Группа</label><select class="form-select" name="study_group_id"><option value="">Для всех</option>@foreach($groups as $g)<option value="{{ $g->id }}" @selected(old('study_group_id')==$g->id)>{{ $g->study_year }} год · {{ $g->name }}</option>@endforeach</select></div>
      <div class="col-md-6"><label class="form-label">Дата</label><input type="date" class="form-control" name="lesson_date" value="{{ old('lesson_date') }}" required></div>

      <div class="col-md-4"><label class="form-label">Начало</label><input type="time" class="form-control" name="starts_at" value="{{ old('starts_at','13:00') }}" required></div>
      <div class="col-md-4"><label class="form-label">Конец</label><input type="time" class="form-control" name="ends_at" value="{{ old('ends_at','16:30') }}" required></div>
      <div class="col-md-4"><label class="form-label">Аудитория</label><input class="form-control" name="room" value="{{ old('room') }}"></div>

      <div class="col-md-7"><label class="form-label">Преподаватель</label><input class="form-control" name="teacher_name" value="{{ old('teacher_name') }}"></div>
      <div class="col-md-3"><label class="form-label">Цвет</label><input type="color" class="form-control form-control-color w-100" name="color" value="{{ old('color','#8a5cff') }}"></div>
      <div class="col-md-2 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_published" value="1" checked><label class="form-check-label">Показывать</label></div></div>

      <div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" rows="3" name="description">{{ old('description') }}</textarea></div>
      <div class="col-12 text-end"><button class="btn btn-neon">Добавить занятие</button></div>
     </div>
    </form>
   </div>

   <div class="schedule-admin-card schedule-master-card">
    <div class="schedule-admin-card-head">
     <div>
      <div class="eyebrow">Мастер расписания</div>
      <h3>Повторять по дням недели</h3>
     </div>
     <span class="schedule-mode-badge accent">серия</span>
    </div>

    <p class="text-white-50 small mb-4">Пример: каждую субботу с 13:00 до 16:30, начиная с 3 октября и заканчивая 19 декабря. Все подходящие даты будут созданы автоматически.</p>

    <form method="post" action="{{ route('admin.schedule.generate') }}">@csrf
     <div class="row g-3">
      <div class="col-md-7"><label class="form-label">Занятие</label><input class="form-control" name="title" value="{{ old('title') }}" required></div>
      <div class="col-md-5"><label class="form-label">День недели</label><select class="form-select" name="weekday" required>
       @foreach([1=>'Понедельник',2=>'Вторник',3=>'Среда',4=>'Четверг',5=>'Пятница',6=>'Суббота',7=>'Воскресенье'] as $day=>$label)
        <option value="{{ $day }}" @selected((int)old('weekday',6)===$day)>{{ $label }}</option>
       @endforeach
      </select></div>

      <div class="col-md-6"><label class="form-label">Студия</label><select class="form-select" name="studio_id"><option value="">Общее</option>@foreach($studios as $s)<option value="{{ $s->id }}" @selected(old('studio_id')==$s->id)>{{ $s->title }}</option>@endforeach</select></div>
      <div class="col-md-6"><label class="form-label">Группа</label><select class="form-select" name="study_group_id"><option value="">Для всех</option>@foreach($groups as $g)<option value="{{ $g->id }}" @selected(old('study_group_id')==$g->id)>{{ $g->study_year }} год · {{ $g->name }}</option>@endforeach</select></div>

      <div class="col-md-6"><label class="form-label">С даты</label><input type="date" class="form-control" name="date_from" value="{{ old('date_from') }}" required></div>
      <div class="col-md-6"><label class="form-label">По дату</label><input type="date" class="form-control" name="date_to" value="{{ old('date_to') }}" required></div>

      <div class="col-md-4"><label class="form-label">Начало</label><input type="time" class="form-control" name="starts_at" value="{{ old('starts_at','13:00') }}" required></div>
      <div class="col-md-4"><label class="form-label">Конец</label><input type="time" class="form-control" name="ends_at" value="{{ old('ends_at','16:30') }}" required></div>
      <div class="col-md-4"><label class="form-label">Аудитория</label><input class="form-control" name="room" value="{{ old('room') }}"></div>

      <div class="col-md-7"><label class="form-label">Преподаватель</label><input class="form-control" name="teacher_name" value="{{ old('teacher_name') }}"></div>
      <div class="col-md-3"><label class="form-label">Цвет</label><input type="color" class="form-control form-control-color w-100" name="color" value="{{ old('color','#00e5ff') }}"></div>
      <div class="col-md-2 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_published" value="1" checked><label class="form-check-label">Показывать</label></div></div>

      <div class="col-12"><label class="form-label">Описание</label><textarea class="form-control" rows="3" name="description">{{ old('description') }}</textarea></div>

      <div class="col-12">
       <div class="schedule-master-summary" data-schedule-master-summary>
        Выберите даты — здесь появится предварительное количество занятий.
       </div>
      </div>

      <div class="col-12 text-end"><button class="btn btn-neon btn-lg">Создать серию занятий</button></div>
     </div>
    </form>
   </div>
  </div>

  <div class="glass-card p-4">
   <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Созданные занятия</h3>
    <span class="badge-soft">{{ $lessons->count() }}</span>
   </div>
   <div class="table-responsive">
    <table class="table admin-table align-middle">
     <thead><tr><th>Дата</th><th>Время</th><th>Занятие</th><th>Студия</th><th>Группа</th><th></th></tr></thead>
     <tbody>
      @forelse($lessons as $l)
       <tr>
        <td>{{ $l->lesson_date->format('d.m.Y') }}</td>
        <td>{{ substr($l->starts_at,0,5) }}–{{ substr($l->ends_at,0,5) }}</td>
        <td><strong>{{ $l->title }}</strong><div class="small text-white-50">{{ $l->teacher_name }}</div></td>
        <td>{{ $l->studio->title ?? 'Общее' }}</td>
        <td>{{ $l->group->name ?? 'Все' }}</td>
        <td class="text-end"><form method="post" action="{{ route('admin.schedule.delete',$l) }}" onsubmit="return confirm('Удалить занятие?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Удалить</button></form></td>
       </tr>
      @empty
       <tr><td colspan="6" class="text-white-50">Пока пусто.</td></tr>
      @endforelse
     </tbody>
    </table>
   </div>
  </div>
 </div>
</section>
@endsection
