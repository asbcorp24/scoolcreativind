@extends('layouts.app')
@section('title','Личный кабинет · ШКИ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Личный кабинет</div><div class="d-flex justify-content-between align-items-end gap-3 flex-wrap"><div><h1 class="display-2 fw-bold mt-2">{{ auth()->user()->name }}</h1><p class="text-white-50 mb-0">{{ auth()->user()->email }}</p></div><form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-ghost">Выйти</button></form></div></div></section>
<section class="pb-4"><div class="container"><div class="cabinet-links">
<a href="{{ route('schedule') }}" class="cabinet-link"><span>01</span><strong>Расписание</strong><small>Календарь занятий</small></a>
<a href="{{ route('portfolio.mine') }}" class="cabinet-link"><span>02</span><strong>Моё портфолио</strong><small>Работы и награды</small></a>
<a href="{{ route('competitions') }}" class="cabinet-link"><span>03</span><strong>Конкурсы</strong><small>Запись и мои конкурсные работы</small></a>
<a href="{{ route('quizzes.index') }}" class="cabinet-link"><span>04</span><strong>Викторины</strong><small>Тесты и онлайн-сертификаты</small></a>
<a href="{{ route('academic.dashboard') }}" class="cabinet-link"><span>05</span><strong>Моя учёба</strong><small>Оценки, посещаемость и домашние задания</small></a>
@if(auth()->user()->teacherGroups()->exists())<a href="{{ route('admin.journal') }}" class="cabinet-link"><span>06</span><strong>Кабинет преподавателя</strong><small>Журнал, ДЗ и расписание</small></a>@endif
</div></div></section>
<section class="pb-5 mb-5"><div class="container"><div class="section-head"><div><div class="eyebrow">Admission</div><h2>Мои заявки</h2></div><a href="{{ route('apply') }}" class="btn btn-neon">Новая заявка</a></div>
<div class="glass-card p-3 p-lg-4"><div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Дата</th><th>Студия</th><th>Телефон</th><th>Статус</th></tr></thead><tbody>
@forelse($applications as $a)<tr><td>{{ $a->created_at->format('d.m.Y H:i') }}</td><td>{{ $a->studio->title ?? 'Не выбрана' }}</td><td>{{ $a->phone }}</td><td><span class="badge-soft">{{ ['new'=>'Новая','processing'=>'В обработке','accepted'=>'Принята','rejected'=>'Отклонена'][$a->status] ?? $a->status }}</span></td></tr>@empty<tr><td colspan="4" class="text-white-50 py-4">У вас пока нет заявок.</td></tr>@endforelse
</tbody></table></div></div></div></section>

<section class="pb-5"><div class="container">
<div class="row g-4">
 <div class="col-lg-6"><div class="glass-card p-4 h-100"><h3>Мои конкурсы</h3>
 @forelse($competitionRegistrations as $r)<div class="py-3 border-bottom border-secondary-subtle"><strong>{{ $r->competition->title }}</strong><div class="small text-white-50">{{ ['registered'=>'Записан','submitted'=>'Работа отправлена','reviewed'=>'Проверено','cancelled'=>'Отменено'][$r->status] ?? $r->status }}</div></div>@empty<div class="text-white-50 mt-3">Вы пока не записывались на конкурсы.</div>@endforelse
 </div></div>
 <div class="col-lg-6"><div class="glass-card p-4 h-100"><h3>Мои сертификаты</h3>
 @forelse($quizCertificates as $a)<div class="py-3 border-bottom border-secondary-subtle d-flex justify-content-between gap-3"><div><strong>{{ $a->quiz->title }}</strong><div class="small text-white-50">{{ $a->score }}% · {{ optional($a->completed_at)->format('d.m.Y') }}</div></div><a class="btn btn-sm btn-ghost" href="{{ route('quizzes.certificate',$a->certificate_code) }}" target="_blank">Открыть</a></div>@empty<div class="text-white-50 mt-3">Сертификатов пока нет.</div>@endforelse
 </div></div>
</div>
</div></section>
@endsection
