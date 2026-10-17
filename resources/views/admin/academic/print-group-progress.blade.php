<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Успеваемость и посещаемость · {{ $group->name }}</title>
<style>
@page{size:A4 landscape;margin:12mm}
*{box-sizing:border-box}
body{font-family:Arial,sans-serif;color:#111;margin:0;font-size:11px}
h1{font-size:20px;margin:0 0 4px}
h2{font-size:14px;margin:18px 0 8px}
.meta{color:#555;margin-bottom:14px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #777;padding:5px 6px;text-align:center;vertical-align:middle}
th{background:#eee}
td.name{text-align:left;font-weight:700}
.summary{display:flex;gap:20px;flex-wrap:wrap;margin:10px 0 14px}
.summary div{border:1px solid #aaa;padding:8px 12px}
.actions{margin:0 0 16px}
@media print{.actions{display:none}}
</style>
</head>
<body>
<div class="actions">
 <button onclick="window.print()">Печать / PDF</button>
</div>

<h1>Сводный отчёт по группе «{{ $group->name }}»</h1>
<div class="meta">
 Учебный год: {{ $group->academicYear->name ?? '—' }} ·
 Год обучения: {{ $group->study_year }} ·
 Период: {{ date('d.m.Y',strtotime($dateFrom)) }} — {{ date('d.m.Y',strtotime($dateTo)) }}
 @if($group->studio) · Студия: {{ $group->studio->title }} @endif
</div>

<div class="summary">
 <div><strong>Учеников:</strong> {{ $rows->count() }}</div>
 <div><strong>Предметов:</strong> {{ $subjects->count() }}</div>
 <div><strong>Средняя посещаемость:</strong>
  {{ $rows->whereNotNull('attendance')->count() ? round($rows->whereNotNull('attendance')->avg('attendance'),1).'%' : '—' }}
 </div>
 <div><strong>Средняя оценка группы:</strong>
  {{ $rows->whereNotNull('average')->count() ? round($rows->whereNotNull('average')->avg('average'),2) : '—' }}
 </div>
</div>

<h2>Посещаемость и успеваемость</h2>
<table>
 <thead>
  <tr>
   <th>№</th>
   <th>Ученик</th>
   <th>Занятий</th>
   <th>Был</th>
   <th>Опоздал</th>
   <th>Пропустил</th>
   <th>Уваж.</th>
   <th>Посещаемость</th>
   <th>Оценок</th>
   <th>Средняя оценка</th>
  </tr>
 </thead>
 <tbody>
  @foreach($rows as $i=>$row)
   <tr>
    <td>{{ $i+1 }}</td>
    <td class="name">{{ $row['student']->name }}</td>
    <td>{{ $row['lessons'] }}</td>
    <td>{{ $row['present'] }}</td>
    <td>{{ $row['late'] }}</td>
    <td>{{ $row['absent'] }}</td>
    <td>{{ $row['excused'] }}</td>
    <td>{{ $row['attendance']!==null ? $row['attendance'].'%' : '—' }}</td>
    <td>{{ $row['grades_count'] }}</td>
    <td>{{ $row['average'] ?? '—' }}</td>
   </tr>
  @endforeach
 </tbody>
</table>

<h2>Предметы, включённые в отчёт</h2>
<div>
 @forelse($subjects as $subject)
  {{ $subject->title }}@if(!$loop->last), @endif
 @empty
  —
 @endforelse
</div>
</body>
</html>
