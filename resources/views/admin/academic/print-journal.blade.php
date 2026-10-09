<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Журнал · {{ $group->name }} · {{ $subject->title }}</title>
<style>
@page{size:A4 landscape;margin:10mm}
*{box-sizing:border-box}
body{font-family:Arial,sans-serif;color:#111;margin:0;background:#fff;font-size:10px}
.toolbar{position:sticky;top:0;background:#fff;border-bottom:1px solid #ccc;padding:8px 0;margin-bottom:12px;display:flex;gap:8px}
button,a{font:inherit;padding:7px 10px;border:1px solid #777;background:#fff;color:#111;text-decoration:none;border-radius:4px;cursor:pointer}
h1{font-size:18px;margin:0 0 4px}
h2{font-size:14px;margin:16px 0 6px}
.meta{margin-bottom:10px;line-height:1.5}
table{width:100%;border-collapse:collapse;table-layout:fixed}
th,td{border:1px solid #333;padding:3px 4px;text-align:center;vertical-align:middle}
th.student,td.student{text-align:left;width:180px}
th.lesson{width:42px;font-size:8px;writing-mode:vertical-rl;transform:rotate(180deg);height:95px}
.summary{width:48px}
.legend{margin:8px 0 14px;font-size:9px}
.page-break{page-break-before:always}
.small{font-size:8px}
@media print{.toolbar{display:none}body{font-size:9px}a{color:#111}}
</style>
</head>
<body>
<div class="toolbar">
 <button onclick="window.print()">Печать</button>
 <a href="{{ url()->previous() }}">Назад</a>
</div>

<h1>Журнал посещаемости и успеваемости</h1>
<div class="meta">
 <strong>Группа:</strong> {{ $group->name }} ·
 <strong>Предмет:</strong> {{ $subject->title }} ·
 <strong>Период:</strong> {{ date('d.m.Y',strtotime($dateFrom)) }} — {{ date('d.m.Y',strtotime($dateTo)) }}
 @if($group->studio)<br><strong>Студия:</strong> {{ $group->studio->title }}@endif
</div>

<h2>Посещаемость</h2>
<div class="legend">Б — был, Н — отсутствовал, О — опоздал, У — уважительная причина.</div>
<table>
 <thead>
  <tr>
   <th class="student">Ученик</th>
   @foreach($lessons as $lesson)
    <th class="lesson">
     {{ $lesson->lesson_date->format('d.m') }}<br>
     {{ $lesson->planLesson->title ?? $lesson->topic }}
    </th>
   @endforeach
   <th class="summary">Б</th>
   <th class="summary">О</th>
   <th class="summary">Н</th>
   <th class="summary">У</th>
   <th class="summary">%</th>
  </tr>
 </thead>
 <tbody>
 @foreach($students as $student)
  @php
  $sum = $studentSummary[$student->id];
 @endphp
  <tr>
   <td class="student">{{ $student->name }}</td>
   @foreach($lessons as $lesson)
    <td>{{ $attendanceMatrix[$student->id][$lesson->id] ?? '—' }}</td>
   @endforeach
   <td>{{ $sum['present'] }}</td>
   <td>{{ $sum['late'] }}</td>
   <td>{{ $sum['absent'] }}</td>
   <td>{{ $sum['excused'] }}</td>
   <td>{{ $sum['attendance_rate'] !== null ? $sum['attendance_rate'] : '—' }}</td>
  </tr>
 @endforeach
 </tbody>
</table>

<div class="page-break"></div>
<h2>Успеваемость</h2>
<table>
 <thead>
  <tr>
   <th class="student">Ученик</th>
   @foreach($lessons as $lesson)
    <th class="lesson">
     {{ $lesson->lesson_date->format('d.m') }}<br>
     {{ $lesson->planLesson->title ?? $lesson->topic }}
    </th>
   @endforeach
   <th style="width:60px">Средняя</th>
  </tr>
 </thead>
 <tbody>
 @foreach($students as $student)
  @php
  $sum = $studentSummary[$student->id];
 @endphp
  <tr>
   <td class="student">{{ $student->name }}</td>
   @foreach($lessons as $lesson)
    <td>{{ $gradeMatrix[$student->id][$lesson->id] ?? '—' }}</td>
   @endforeach
   <td><strong>{{ $sum['average_grade'] !== null ? $sum['average_grade'] : '—' }}</strong></td>
  </tr>
 @endforeach
 </tbody>
</table>

<h2>Проведённые уроки</h2>
<table>
 <thead><tr><th style="width:30px">№</th><th style="width:70px">Дата</th><th>Тема</th><th style="width:180px">Преподаватель</th></tr></thead>
 <tbody>
 @foreach($lessons as $i=>$lesson)
  <tr>
   <td>{{ $i+1 }}</td>
   <td>{{ $lesson->lesson_date->format('d.m.Y') }}</td>
   <td style="text-align:left">{{ $lesson->planLesson->title ?? $lesson->topic }}</td>
   <td style="text-align:left">{{ $lesson->teacher->name ?? '—' }}</td>
  </tr>
 @endforeach
 </tbody>
</table>
</body>
</html>