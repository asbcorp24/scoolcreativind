@extends('layouts.app')
@section('title','Ученики · Админ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Админ / ученики</div>
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
   <div>
    <h1 class="display-3 fw-bold m-0">Ученики</h1>
    <p class="text-white-50 mt-2 mb-0">Список учеников школы, их группы и переход к отдельной странице проектов.</p>
   </div>
   <a href="{{ route('admin.groups') }}" class="btn btn-neon">+ Добавить ученика в группе</a>
  </div>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  <div class="glass-card p-4">
   <div class="table-responsive">
    <table class="table admin-table align-middle">
     <thead>
      <tr>
       <th>Ученик</th>
       <th>Группа</th>
       <th>Профиль</th>
       <th>Публичный</th>
       <th></th>
      </tr>
     </thead>
     <tbody>
     @forelse($students as $student)
      @php($profile=$profiles->get($student->id))
      <tr>
       <td>
        <strong>{{ $student->name }}</strong>
        <div class="small text-white-50">{{ $student->email }}</div>
       </td>
       <td>
        @forelse($student->studyGroups as $group)
         <span class="badge-soft me-1">{{ $group->study_year }} год · {{ $group->name }}</span>
        @empty
         —
        @endforelse
       </td>
       <td>{{ $profile ? 'Создан' : 'Нет' }}</td>
       <td>{{ $profile && $profile->is_public ? 'Да' : 'Нет' }}</td>
       <td class="text-end">
        @if($profile)
         <a href="{{ route('admin.students.portfolio',$profile) }}" class="btn btn-sm btn-ghost">Проекты / портфолио</a>
         @if($profile->is_public)
          <a href="{{ route('portfolio.show',$profile) }}" class="btn btn-sm btn-ghost" target="_blank">Открыть ↗</a>
         @endif
        @else
         <span class="small text-white-50">Профиль создаётся автоматически при добавлении ученика через группу</span>
        @endif
       </td>
      </tr>
     @empty
      <tr><td colspan="5" class="text-white-50">Ученики ещё не добавлены. Создавайте их в разделе «Группы».</td></tr>
     @endforelse
     </tbody>
    </table>
   </div>
  </div>
 </div>
</section>
@endsection
