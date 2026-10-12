@extends('layouts.app')
@section('title','Печатные отчёты · Админ')
@section('content')
<section class="page-top">
 <div class="container">
  <div class="eyebrow">Админ / обучение</div>
  <h1 class="display-3 fw-bold">Печатные отчёты</h1>
  <p class="text-white-50 mb-0">Журнал посещаемости и успеваемости, а также календарно-тематический план.</p>
 </div>
</section>

<section class="pb-5">
 <div class="container">
  <div class="row g-4">
   <div class="col-xl-6">
    <div class="glass-card p-4 h-100">
     <div class="eyebrow">Журнал</div>
     <h3 class="mt-2">Посещаемость и успеваемость</h3>
     <p class="text-white-50">Формируется печатный журнал по группе, предмету и периоду. В отчёте две таблицы: посещаемость и оценки, плюс список проведённых уроков.</p>

     <form method="get" target="_blank" action="{{ route('admin.attendance.print') }}" class="row g-3 mt-2" data-report-journal>
      <div class="col-12">
       <label class="form-label">Группа</label>
       <select class="form-select" name="group_id" required data-report-group>
        <option value="">Выберите группу</option>
        @foreach($groups as $group)
         <option value="{{ $group->id }}">{{ $group->name }} · {{ $group->study_year }} год</option>
        @endforeach
       </select>
      </div>

      <div class="col-12">
       <label class="form-label">Предмет</label>
       <select class="form-select" name="subject_id" required data-report-subject disabled>
        <option value="">Сначала выберите группу</option>
       </select>
      </div>

      <div class="col-md-6">
       <label class="form-label">С даты</label>
       <input type="date" class="form-control" name="date_from" value="{{ now()->startOfMonth()->toDateString() }}">
      </div>
      <div class="col-md-6">
       <label class="form-label">По дату</label>
       <input type="date" class="form-control" name="date_to" value="{{ now()->toDateString() }}">
      </div>

      <div class="col-12 d-grid mt-3">
       <button class="btn btn-neon btn-lg">Открыть журнал для печати ↗</button>
      </div>
     </form>
    </div>
   </div>

   <div class="col-xl-6">
    <div class="glass-card p-4 h-100">
     <div class="eyebrow">КТП</div>
     <h3 class="mt-2">Календарно-тематический план</h3>
     <p class="text-white-50">Печатается план предмета: № урока, тема, содержание, домашнее задание, материалы. Если выбрать группу, дополнительно выводятся фактические даты проведения.</p>

     <form method="get" target="_blank" data-ktp-form class="row g-3 mt-2">
      <div class="col-12">
       <label class="form-label">Предмет</label>
       <select class="form-select" required data-ktp-subject>
        <option value="">Выберите предмет</option>
        @foreach($ktpSubjects as $subject)
         <option value="{{ $subject->id }}">{{ $subject->title }}</option>
        @endforeach
       </select>
      </div>

      <div class="col-12">
       <label class="form-label">Группа</label>
       <select class="form-select" name="group_id" data-ktp-group>
        <option value="">Без группы — только план</option>
        @foreach($groups as $group)
         <option value="{{ $group->id }}">{{ $group->name }} · {{ $group->study_year }} год</option>
        @endforeach
       </select>
       <div class="form-text text-white-50">Если группа выбрана, в КТП появится колонка с фактическими датами проведённых уроков.</div>
      </div>

      <div class="col-12 d-grid mt-3">
       <button class="btn btn-neon btn-lg">Открыть КТП для печати ↗</button>
      </div>
     </form>
    </div>
   </div>
  </div>

  <div class="glass-card p-4 mt-4">
   <div class="eyebrow">Печать</div>
   <h4 class="mt-2">Формат отчётов</h4>
   <div class="row g-3 text-white-50">
    <div class="col-md-4">Журнал — A4, альбомная ориентация.</div>
    <div class="col-md-4">КТП — A4, альбомная ориентация.</div>
    <div class="col-md-4">При печати меню и кнопки автоматически скрываются.</div>
   </div>
  </div>
 </div>
</section>

<script>
document.addEventListener('DOMContentLoaded',()=>{
 const subjectOptions=@json($subjectOptions);

 const journalForm=document.querySelector('[data-report-journal]');
 const groupSelect=journalForm?.querySelector('[data-report-group]');
 const subjectSelect=journalForm?.querySelector('[data-report-subject]');

 const updateJournalSubjects=()=>{
  if(!groupSelect||!subjectSelect)return;
  const items=subjectOptions[groupSelect.value] || [];
  subjectSelect.innerHTML='<option value="">Выберите предмет</option>';
  items.forEach(item=>{
   const option=document.createElement('option');
   option.value=String(item.id);
   option.textContent=item.title;
   subjectSelect.appendChild(option);
  });
  subjectSelect.disabled=!groupSelect.value || items.length===0;
 };

 groupSelect?.addEventListener('change',updateJournalSubjects);
 updateJournalSubjects();

 const ktpForm=document.querySelector('[data-ktp-form]');
 const ktpSubject=ktpForm?.querySelector('[data-ktp-subject]');
 if(ktpForm && ktpSubject){
  ktpForm.addEventListener('submit',event=>{
   if(!ktpSubject.value){
    event.preventDefault();
    ktpSubject.focus();
    return;
   }
   ktpForm.action='{{ url('/admin/attendance/ktp') }}/'+encodeURIComponent(ktpSubject.value)+'/print';
  });
 }
});
</script>
@endsection
