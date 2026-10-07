<!doctype html>
<html lang="ru">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <title>Отчёт группы · ШКИ Волжск</title>
 <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}">
 <link rel="stylesheet" href="{{ asset('css/site.css') }}">
 <style>
  body{background:#071018;color:#fff}
  .parent-report-shell{max-width:1180px;margin:0 auto;padding:32px 18px 70px}
  .parent-report-head{padding:28px;border:1px solid rgba(255,255,255,.08);border-radius:24px;background:rgba(255,255,255,.03)}
  .parent-report-logo{width:min(360px,75vw);height:auto;margin-bottom:24px}
  .parent-student-card{display:block;padding:18px;border:1px solid rgba(255,255,255,.08);border-radius:18px;background:rgba(255,255,255,.025);text-decoration:none;color:#fff}
  .parent-student-card:hover{border-color:rgba(0,229,255,.28);transform:translateY(-2px)}
 </style>
</head>
<body>
<div class="parent-report-shell">
 <header class="parent-report-head mb-4">
  <img class="parent-report-logo" src="{{ asset('brand/ski-volzhsk-logo.svg') }}" alt="ШКИ Волжск">
  <div class="eyebrow">Отчёт для родителей</div>
  <h1 class="display-5 fw-bold mt-2 mb-2">{{ $group->name }}</h1>
  <div class="text-white-50">Данные по {{ $link->report_until->format('d.m.Y') }} включительно</div>
 </header>

 <section>
  <div class="section-head">
   <div><div class="eyebrow">Students</div><h2>Выберите ученика</h2></div>
   <p>По этой ссылке доступны отчёты только учеников данной группы.</p>
  </div>

  <div class="row g-3">
   @forelse($profiles as $profile)
    <div class="col-md-6 col-xl-4">
     <a class="parent-student-card" href="{{ route('parent-report.show',$link->token) }}?student_profile_id={{ $profile->id }}">
      <div class="small text-white-50">{{ $profile->class_name ?: $group->name }}</div>
      <h3 class="mt-2 mb-1">{{ $profile->user->name ?? 'Ученик' }}</h3>
      <span class="small text-info">Открыть отчёт →</span>
     </a>
    </div>
   @empty
    <div class="text-white-50">В группе нет учеников с профилем.</div>
   @endforelse
  </div>
 </section>
</div>
</body>
</html>
