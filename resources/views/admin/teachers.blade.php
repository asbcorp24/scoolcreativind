@extends('layouts.app')
@section('title','Преподаватели · Админ')
@section('content')
@php
 $editing=$editTeacher ?? null;
 $selectedGroups=old(
     'groups',
     $editing ? $editing->teacherGroups()->pluck('study_groups.id')->map(fn($id)=>(string)$id)->all() : []
 );
 $selectedSubjects=old(
     'subjects',
     array_keys($assignedSubjects ?? [])
 );
@endphp

<section class="page-top">
 <div class="container">
  <div class="eyebrow">Админ / доступ</div>
  <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
   <div>
    <h1 class="display-3 fw-bold mb-1">Преподаватели</h1>
    <p class="text-white-50 mb-0">Аккаунты, логины, пароли, группы и закреплённые предметы.</p>
   </div>
   <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">← Админка</a>
  </div>
 </div>
</section>

<section class="pb-5">
 <div class="container">

  @if(session('teacher_credentials'))
   <div class="alert alert-success mb-4">
    <strong>Данные для входа преподавателя</strong>
    <div class="mt-2">ФИО: {{ session('teacher_credentials.name') }}</div>
    <div>Логин: <code>{{ session('teacher_credentials.login') }}</code></div>
    <div>Пароль: <code>{{ session('teacher_credentials.password') }}</code></div>
    <div class="small mt-2">Скопируйте пароль сейчас. После закрытия страницы исходный пароль восстановить нельзя — можно только задать новый.</div>
   </div>
  @endif

  @if($errors->any())
   <div class="alert alert-danger mb-4">
    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
   </div>
  @endif

  <div class="row g-4">
   <div class="col-xl-5">
    <form class="glass-card p-4" method="post" action="{{ route('admin.teachers.save',$editing) }}">
     @csrf
     <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
      <h3 class="mb-0">{{ $editing ? 'Редактировать преподавателя' : 'Новый преподаватель' }}</h3>
      @if($editing)<a class="btn btn-sm btn-ghost" href="{{ route('admin.teachers') }}">Отмена</a>@endif
     </div>

     <div class="row g-3">
      <div class="col-12">
       <label class="form-label">ФИО</label>
       <input class="form-control" name="name" value="{{ old('name',$editing->name ?? '') }}" required>
      </div>

      <div class="col-12">
       <label class="form-label">Логин / Email</label>
       <input type="email" class="form-control" name="email" value="{{ old('email',$editing->email ?? '') }}" required>
      </div>

      <div class="col-12">
       <label class="form-label">Телефон</label>
       <input class="form-control" name="phone" value="{{ old('phone',$editing->phone ?? '') }}">
      </div>

      <div class="col-12">
       <label class="form-label">{{ $editing ? 'Новый пароль' : 'Пароль' }}</label>
       <input class="form-control" name="password" placeholder="{{ $editing ? 'Оставьте пустым, чтобы не менять' : 'Оставьте пустым для автогенерации' }}">
       <div class="form-text text-white-50">
        {{ $editing ? 'Текущий пароль не отображается, так как хранится в виде хеша.' : 'Если оставить пустым, система создаст пароль автоматически.' }}
       </div>
      </div>

      <div class="col-12">
       <label class="form-label">Учебные группы</label>
       <div class="d-flex flex-column gap-2">
        @foreach($groups as $group)
         <label class="glass-card p-3 d-flex align-items-center gap-3">
          <input class="form-check-input mt-0" type="checkbox" name="groups[]" value="{{ $group->id }}"
                 @checked(in_array((string)$group->id,array_map('strval',$selectedGroups),true))>
          <span>
           <strong>{{ $group->name }}</strong>
           <small class="d-block text-white-50">{{ $group->study_year }} год · {{ $group->code }}</small>
          </span>
         </label>
        @endforeach
       </div>
      </div>

      <div class="col-12">
       <label class="form-label">Предметы преподавателя</label>
       <div class="small text-white-50 mb-2">Выберите предметы именно в тех группах, где преподаватель их ведёт.</div>

       <div class="accordion" id="teacherSubjects">
        @foreach($groups as $group)
         <div class="accordion-item bg-transparent border-secondary-subtle">
          <h2 class="accordion-header">
           <button class="accordion-button collapsed bg-transparent text-white" type="button" data-bs-toggle="collapse" data-bs-target="#teacher-group-{{ $group->id }}">
            {{ $group->name }}
           </button>
          </h2>
          <div id="teacher-group-{{ $group->id }}" class="accordion-collapse collapse" data-bs-parent="#teacherSubjects">
           <div class="accordion-body">
            @forelse($group->subjects as $subject)
             @php($key=$group->id.':'.$subject->id)
             <label class="d-flex align-items-center gap-2 py-2">
              <input class="form-check-input mt-0" type="checkbox" name="subjects[]" value="{{ $key }}"
                     @checked(in_array($key,$selectedSubjects,true))>
              <span>{{ $subject->title }}</span>
             </label>
            @empty
             <div class="small text-white-50">В группе пока нет предметов.</div>
            @endforelse
           </div>
          </div>
         </div>
        @endforeach
       </div>
      </div>

      <div class="col-12 text-end">
       <button class="btn btn-neon">{{ $editing ? 'Сохранить' : 'Создать преподавателя' }}</button>
      </div>
     </div>
    </form>
   </div>

   <div class="col-xl-7">
    <div class="glass-card p-4">
     <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
      <div>
       <div class="eyebrow">Accounts</div>
       <h3 class="mb-0">Аккаунты преподавателей</h3>
      </div>
      <span class="badge-soft">{{ $teachers->count() }}</span>
     </div>

     <div class="table-responsive">
      <table class="table admin-table align-middle">
       <thead>
        <tr>
         <th>Преподаватель</th>
         <th>Группы</th>
         <th>Предметы</th>
         <th></th>
        </tr>
       </thead>
       <tbody>
        @forelse($teachers as $teacher)
         @php
          $subjectRows=\Illuminate\Support\Facades\DB::table('group_subjects')
              ->join('study_groups','study_groups.id','=','group_subjects.study_group_id')
              ->join('subjects','subjects.id','=','group_subjects.subject_id')
              ->where('group_subjects.teacher_id',$teacher->id)
              ->select('study_groups.name as group_name','subjects.title as subject_title')
              ->orderBy('study_groups.name')
              ->orderBy('subjects.title')
              ->get();
         @endphp
         <tr>
          <td style="min-width:220px">
           <strong>{{ $teacher->name }}</strong>
           <div class="small text-white-50">Логин: {{ $teacher->email }}</div>
           @if($teacher->phone)<div class="small text-white-50">{{ $teacher->phone }}</div>@endif

           <form class="d-flex gap-2 mt-2" method="post" action="{{ route('admin.teachers.reset-password',$teacher) }}">
            @csrf
            <input class="form-control form-control-sm" name="password" placeholder="Новый или авто">
            <button class="btn btn-sm btn-ghost" title="Сменить пароль">Пароль</button>
           </form>
          </td>

          <td>
           <div class="d-flex gap-1 flex-wrap">
            @forelse($teacher->teacherGroups as $group)
             <span class="badge-soft">{{ $group->name }}</span>
            @empty
             <span class="text-white-50">—</span>
            @endforelse
           </div>
          </td>

          <td style="min-width:220px">
           @forelse($subjectRows as $row)
            <div class="small mb-1"><strong>{{ $row->subject_title }}</strong> <span class="text-white-50">· {{ $row->group_name }}</span></div>
           @empty
            <span class="text-white-50">Не назначены</span>
           @endforelse
          </td>

          <td class="text-end">
           <div class="d-flex gap-2 justify-content-end flex-wrap">
            <a href="{{ route('admin.teachers.edit',$teacher) }}" class="btn btn-sm btn-ghost">Изменить</a>
            <form method="post" action="{{ route('admin.teachers.remove',$teacher) }}" onsubmit="return confirm('Снять роль преподавателя и убрать назначения групп/предметов? Аккаунт пользователя останется.')">
             @csrf
             @method('DELETE')
             <button class="btn btn-sm btn-outline-danger">Снять роль</button>
            </form>
           </div>
          </td>
         </tr>
        @empty
         <tr><td colspan="4" class="text-white-50 py-4">Преподаватели пока не созданы.</td></tr>
        @endforelse
       </tbody>
      </table>
     </div>
    </div>
   </div>
  </div>
 </div>
</section>
@endsection
