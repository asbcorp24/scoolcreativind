<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>КТП · {{ $subject->title }}</title>
<style>
@page{size:A4 landscape;margin:10mm}
*{box-sizing:border-box}
body{font-family:Arial,sans-serif;color:#111;margin:0;background:#fff;font-size:10px}
.toolbar{position:sticky;top:0;background:#fff;border-bottom:1px solid #ccc;padding:8px 0;margin-bottom:12px;display:flex;gap:8px}
button,a{font:inherit;padding:7px 10px;border:1px solid #777;background:#fff;color:#111;text-decoration:none;border-radius:4px;cursor:pointer}
h1{font-size:18px;margin:0 0 4px}
.meta{margin-bottom:12px;line-height:1.5}
table{width:100%;border-collapse:collapse;table-layout:fixed}
th,td{border:1px solid #333;padding:5px;vertical-align:top}
th{text-align:center}
.c{text-align:center}
.small{font-size:8px}
@media print{.toolbar{display:none}a{color:#111}}
</style>
</head>
<body>
<div class="toolbar">
 <button onclick="window.print()">Печать</button>
 <a href="{{ url()->previous() }}">Назад</a>
</div>

<h1>Календарно-тематический план</h1>
<div class="meta">
 <strong>Предмет:</strong> {{ $subject->title }}
 @if($subject->studio) · <strong>Студия:</strong> {{ $subject->studio->title }} @endif
 @if($group) · <strong>Группа:</strong> {{ $group->name }} @endif
</div>

<table>
 <thead>
  <tr>
   <th style="width:38px">№</th>
   <th style="width:170px">Тема урока</th>
   <th>Содержание</th>
   <th style="width:200px">Домашнее задание</th>
   <th style="width:70px">Срок, дней</th>
   <th style="width:70px">Макс. балл</th>
   <th style="width:70px">Материалы</th>
   @if($group)<th style="width:105px">Факт. дата</th>@endif
  </tr>
 </thead>
 <tbody>
 @forelse($subject->lessons as $lesson)
  <tr>
   <td class="c">{{ $lesson->lesson_number }}</td>
   <td><strong>{{ $lesson->title }}</strong></td>
   <td>{!! nl2br(e($lesson->content ?: '—')) !!}</td>
   <td>{!! nl2br(e($lesson->homework_description ?: '—')) !!}</td>
   <td class="c">{{ $lesson->homework_due_days ?? '—' }}</td>
   <td class="c">{{ $lesson->homework_max_score ?? '—' }}</td>
   <td class="c">{{ $lesson->media_count }}</td>
   @if($group)<td class="c">{{ $actualDates[$lesson->id] ?? '—' }}</td>@endif
  </tr>
 @empty
  <tr><td colspan="{{ $group ? 8 : 7 }}">КТП пока пуст.</td></tr>
 @endforelse
 </tbody>
</table>
</body>
</html>