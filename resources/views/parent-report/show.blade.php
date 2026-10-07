<!doctype html>
<html lang="ru">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <title>Отчёт ученика · ШКИ Волжск</title>
 <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}">
 <link rel="stylesheet" href="{{ asset('css/site.css') }}">
 <style>
  body{background:#071018;color:#fff}
  .parent-report-shell{max-width:1180px;margin:0 auto;padding:32px 18px 70px}
  .parent-report-head{padding:28px;border:1px solid rgba(255,255,255,.08);border-radius:24px;background:rgba(255,255,255,.03)}
  .parent-report-logo{width:min(360px,75vw);height:auto;margin-bottom:24px}
  .parent-report-stat{padding:18px;border-radius:18px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.025)}
  .parent-report-stat strong{font-size:2rem;display:block;margin-top:.25rem}
  @media print{
   body{background:#fff!important;color:#000!important}
   .parent-report-shell{max-width:none;padding:0}
   .glass-card,.parent-report-head,.parent-report-stat{background:#fff!important;color:#000!important;box-shadow:none!important;border-color:#ddd!important}
   .text-white-50{color:#555!important}
   .btn{display:none!important}
   table{color:#000!important}
  }
 </style>
</head>
<body>
<div class="parent-report-shell">
 <header class="parent-report-head mb-4">
  <img class="parent-report-logo" src="{{ asset('brand/ski-volzhsk-logo.svg') }}" alt="ШКИ Волжск">
  @if(($isGroupReport ?? false) && isset($group))
   <a class="btn btn-sm btn-ghost mb-3" href="{{ route('parent-report.show',$link->token) }}">← Вернуться к группе</a>
  @endif
  <div class="eyebrow">Отчёт для родителей</div>
  <h1 class="display-5 fw-bold mt-2 mb-2">{{ $profile->user->name }}</h1>
  <div class="text-white-50">
   @if(($isGroupReport ?? false) && isset($group)){{ $group->name }} · @elseif($profile->class_name){{ $profile->class_name }} · @endif
   данные по {{ $link->report_until->format('d.m.Y') }} включительно
  </div>
  <div class="mt-4"><button class="btn btn-ghost" onclick="window.print()">Печать / PDF</button></div>
 </header>

 <section class="mb-5">
  <div class="section-head"><div><div class="eyebrow">Attendance</div><h2>Посещаемость</h2></div></div>
  <div class="row g-3">
   <div class="col-6 col-md"><div class="parent-report-stat"><span class="text-white-50">Всего</span><strong>{{ $attendance['total'] }}</strong></div></div>
   <div class="col-6 col-md"><div class="parent-report-stat"><span class="text-white-50">Был</span><strong>{{ $attendance['present'] }}</strong></div></div>
   <div class="col-6 col-md"><div class="parent-report-stat"><span class="text-white-50">Опоздал</span><strong>{{ $attendance['late'] }}</strong></div></div>
   <div class="col-6 col-md"><div class="parent-report-stat"><span class="text-white-50">Отсутствовал</span><strong>{{ $attendance['absent'] }}</strong></div></div>
   <div class="col-6 col-md"><div class="parent-report-stat"><span class="text-white-50">Уваж.</span><strong>{{ $attendance['excused'] }}</strong></div></div>
   <div class="col-6 col-md"><div class="parent-report-stat"><span class="text-white-50">Посещаемость</span><strong>{{ $attendance['rate'] !== null ? $attendance['rate'].'%' : '—' }}</strong></div></div>
  </div>
 </section>

 <section class="mb-5">
  <div class="section-head"><div><div class="eyebrow">Performance</div><h2>Успеваемость</h2></div></div>
  <div class="row g-3">
   <div class="col-6 col-md-3"><div class="parent-report-stat"><span class="text-white-50">Оценок</span><strong>{{ $performance['count'] }}</strong></div></div>
   <div class="col-6 col-md-3"><div class="parent-report-stat"><span class="text-white-50">Средний балл</span><strong>{{ $performance['average'] !== null ? $performance['average'] : '—' }}</strong></div></div>
   <div class="col-6 col-md-3"><div class="parent-report-stat"><span class="text-white-50">Минимальная</span><strong>{{ $performance['min'] !== null ? $performance['min'] : '—' }}</strong></div></div>
   <div class="col-6 col-md-3"><div class="parent-report-stat"><span class="text-white-50">Максимальная</span><strong>{{ $performance['max'] !== null ? $performance['max'] : '—' }}</strong></div></div>
  </div>
 </section>

 <section class="mb-5">
  <div class="section-head"><div><div class="eyebrow">Subjects</div><h2>По предметам</h2></div></div>
  <div class="glass-card p-4">
   <div class="table-responsive">
    <table class="table admin-table align-middle">
     <thead><tr><th>Предмет</th><th>Занятий</th><th>Был</th><th>Опоздал</th><th>Пропуск</th><th>Уваж.</th><th>Посещаемость</th><th>Средний балл</th></tr></thead>
     <tbody>
      @forelse($bySubject as $row)
       <tr>
        <td><strong>{{ $row['subject'] }}</strong></td>
        <td>{{ $row['total'] }}</td>
        <td>{{ $row['present'] }}</td>
        <td>{{ $row['late'] }}</td>
        <td>{{ $row['absent'] }}</td>
        <td>{{ $row['excused'] }}</td>
        <td>{{ $row['attendance_rate'] !== null ? $row['attendance_rate'].'%' : '—' }}</td>
        <td>{{ $row['average_grade'] !== null ? $row['average_grade'] : '—' }}</td>
       </tr>
      @empty
       <tr><td colspan="8" class="text-white-50">Данных пока нет.</td></tr>
      @endforelse
     </tbody>
    </table>
   </div>
  </div>
 </section>

 <section>
  <div class="section-head"><div><div class="eyebrow">Journal</div><h2>Журнал</h2></div></div>
  <div class="glass-card p-4">
   <div class="table-responsive">
    <table class="table admin-table align-middle">
     <thead><tr><th>Дата</th><th>Предмет</th><th>Тема</th><th>Посещение</th><th>Оценка</th><th>Комментарий</th></tr></thead>
     <tbody>
      @forelse($entries as $entry)
       <tr>
        <td>{{ optional($entry->lesson->lesson_date)->format('d.m.Y') }}</td>
        <td>{{ $entry->lesson->subject->title ?? '—' }}</td>
        <td>{{ $entry->lesson->topic ?? '—' }}</td>
        <td>{{ ['present'=>'Был','absent'=>'Отсутствовал','late'=>'Опоздал','excused'=>'Уваж. причина'][$entry->attendance] ?? $entry->attendance }}</td>
        <td><strong>{{ $entry->grade ?? $entry->grade_label ?? '—' }}</strong></td>
        <td>{{ $entry->comment ?: '—' }}</td>
       </tr>
      @empty
       <tr><td colspan="6" class="text-white-50">Записей журнала пока нет.</td></tr>
      @endforelse
     </tbody>
    </table>
   </div>
  </div>
 </section>
</div>
</body>
</html>
