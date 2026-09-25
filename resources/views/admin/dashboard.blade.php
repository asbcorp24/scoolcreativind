@extends('layouts.app')
@section('title','Админ-панель · ШКИ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Control center</div><div class="d-flex justify-content-between align-items-end flex-wrap gap-3"><h1 class="display-2 fw-bold m-0">Админ-панель</h1><div class="d-flex gap-2 flex-wrap">
@if(auth()->user()->canAdminSection('studios'))<a href="{{ route('admin.studios.create') }}" class="btn btn-neon">+ Раздел</a>@endif
@if(auth()->user()->canAdminSection('news'))<a href="{{ route('admin.news.create') }}" class="btn btn-ghost">+ Новость</a>@endif
@if(auth()->user()->canAdminSection('projects'))<a href="{{ route('admin.projects') }}" class="btn btn-ghost">Проекты</a>@endif
@if(auth()->user()->canAdminSection('events'))<a href="{{ route('admin.events') }}" class="btn btn-ghost">События</a>@endif
@if(auth()->user()->canAdminSection('team'))<a href="{{ route('admin.team') }}" class="btn btn-ghost">Команда</a>@endif
@if(auth()->user()->canAdminSection('equipment'))<a href="{{ route('admin.equipment') }}" class="btn btn-ghost">Оборудование</a>@endif
@if(auth()->user()->canAdminSection('schedule'))<a href="{{ route('admin.schedule') }}" class="btn btn-ghost">Расписание</a>@endif
@if(auth()->user()->canAdminSection('groups'))<a href="{{ route('admin.groups') }}" class="btn btn-ghost">Учебные группы</a>@endif
@if(auth()->user()->canAdminSection('subjects'))<a href="{{ route('admin.subjects') }}" class="btn btn-ghost">Предметы</a>@endif
@if(auth()->user()->canAdminSection('journal'))<a href="{{ route('admin.journal') }}" class="btn btn-ghost">Журнал</a>@endif
@if(auth()->user()->canAdminSection('homework'))<a href="{{ route('admin.homework') }}" class="btn btn-ghost">Домашние задания</a>@endif
@if(auth()->user()->canAdminSection('students'))<a href="{{ route('admin.students') }}" class="btn btn-ghost">Ученики</a>@endif
@if(auth()->user()->canAdminSection('competitions'))<a href="{{ route('admin.competitions') }}" class="btn btn-ghost">Конкурсы</a>@endif
@if(auth()->user()->canAdminSection('quizzes'))<a href="{{ route('admin.quizzes') }}" class="btn btn-ghost">Викторины</a>@endif
@if(auth()->user()->canAdminSection('settings'))<a href="{{ route('admin.settings') }}" class="btn btn-ghost">Настройки сайта / SEO</a>@endif
@if(auth()->user()->canAdminSection('documents'))<a href="{{ route('admin.documents') }}" class="btn btn-ghost">Документы</a>@endif
@if(auth()->user()->canAdminSection('questions'))<a href="{{ route('admin.questions') }}" class="btn btn-ghost">Вопросы</a>@endif
@if(auth()->user()->canAdminSection('contacts'))<a href="{{ route('admin.contacts') }}" class="btn btn-ghost">Контакты</a>@endif
@if(auth()->user()->is_admin)<a href="{{ route('admin.access-admins') }}" class="btn btn-ghost">Администраторы</a>@endif
</div></div></div></section>
<section class="pb-5"><div class="container">
 @if(auth()->user()->canAdminSection('studios'))
 <div id="studiosAdmin" class="glass-card p-4 mb-4"><div class="d-flex justify-content-between mb-3"><h3>Студии / разделы</h3><span class="text-white-50">{{ $studios->count() }} шт.</span></div><div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Порядок</th><th>Название</th><th>Статус</th><th></th></tr></thead><tbody>@foreach($studios as $s)<tr><td>{{ $s->sort_order }}</td><td><strong>{{ $s->title }}</strong><div class="small text-white-50">/{{ $s->slug }}</div></td><td>{{ $s->is_active?'Активен':'Скрыт' }}</td><td class="text-end"><a class="btn btn-sm btn-ghost" href="{{ route('admin.media',$s) }}">Медиа</a> <a class="btn btn-sm btn-ghost" href="{{ route('admin.studios.edit',$s) }}">Изменить</a><form class="d-inline" method="post" action="{{ route('admin.studios.delete',$s) }}" onsubmit="return confirm('Удалить раздел?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">×</button></form></td></tr>@endforeach</tbody></table></div></div>
 @endif
 @if(auth()->user()->canAdminSection('applications'))
 <div class="glass-card p-4 mb-4">
  <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
   <div><h3 class="mb-1">Регистрации и заявки</h3><div class="small text-white-50">Новые регистрации сайта и заявки на поступление. Отсюда ученика можно сразу зачислить в реальную учебную группу.</div></div>
   <span class="badge-soft">{{ $applications->count() }} записей</span>
  </div>

  @if(session('created_student_credentials'))
  <div class="alert alert-success">
   <strong>Создан аккаунт ученика.</strong>
   <div>ФИО: {{ session('created_student_credentials.name') }}</div>
   <div>Логин: <code>{{ session('created_student_credentials.email') }}</code></div>
   <div>Пароль: <code>{{ session('created_student_credentials.password') }}</code></div>
  </div>
  @endif

  <div class="table-responsive">
   <table class="table admin-table align-middle">
    <thead><tr><th>Дата</th><th>Ученик</th><th>Контакты</th><th>Источник / студия</th><th>Статус</th><th>Зачисление</th></tr></thead>
    <tbody>
    @forelse($applications as $a)
     <tr>
      <td>{{ $a->created_at->format('d.m.Y H:i') }}</td>
      <td>
       <strong>{{ $a->name }}</strong>
       @if($a->user)<div class="small text-success">Есть аккаунт</div>@else<div class="small text-warning">Без аккаунта</div>@endif
      </td>
      <td>{{ $a->phone ?: '—' }}<div class="small text-white-50">{{ $a->email ?: '—' }}</div></td>
      <td>
       {{ $a->studio->title ?? 'Регистрация на сайте' }}
       @if($a->message)<div class="small text-white-50">{{ $a->message }}</div>@endif
      </td>
      <td>
       <form method="post" action="{{ route('admin.applications.status',$a) }}">@csrf @method('PATCH')
        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
         @foreach(['new'=>'Новая','processing'=>'В обработке','accepted'=>'Принята','rejected'=>'Отклонена'] as $k=>$v)
          <option value="{{ $k }}" @selected($a->status===$k)>{{ $v }}</option>
         @endforeach
        </select>
       </form>
      </td>
      <td style="min-width:280px">
       @if($a->status==='accepted' && $a->user && $a->user->studentGroups()->exists())
        <span class="text-success">Уже зачислен</span>
       @else
        <form method="post" action="{{ route('admin.applications.enroll',$a) }}" class="d-flex gap-2">@csrf
         <select class="form-select form-select-sm" name="study_group_id" required>
          <option value="">Выберите группу</option>
          @foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->study_year }} год · {{ $g->name }}</option>@endforeach
         </select>
         <button class="btn btn-sm btn-neon">Зачислить</button>
        </form>
       @endif
      </td>
     </tr>
    @empty
     <tr><td colspan="6" class="text-white-50">Пока нет регистраций и заявок.</td></tr>
    @endforelse
    </tbody>
   </table>
  </div>
 </div>
 @endif
 @if(auth()->user()->canAdminSection('news'))
 <div class="glass-card p-4"><h3 class="mb-3">Новости</h3>@foreach($news as $n)<div class="d-flex justify-content-between align-items-center py-3 border-bottom border-secondary-subtle"><div><strong>{{ $n->title }}</strong><div class="small text-white-50">{{ $n->is_published?'Опубликовано':'Черновик' }}</div></div><a href="{{ route('admin.news.edit',$n) }}" class="btn btn-sm btn-ghost">Редактировать</a></div>@endforeach</div>
 @endif
</div></section>
@endsection
