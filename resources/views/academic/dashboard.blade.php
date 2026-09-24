@extends('layouts.app')
@section('title','Учёба · ШКИ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Учебный кабинет</div><h1 class="display-2 fw-bold">Моя учёба</h1><p class="text-white-50">Расписание, оценки, посещаемость и домашние задания.</p></div></section>
<section class="pb-5"><div class="container">
<div class="section-head"><div><div class="eyebrow">Schedule</div><h2>Ближайшие занятия</h2></div><a class="btn btn-ghost" href="{{ route('schedule') }}">Календарь</a></div>
<div class="row g-3 mb-5">@forelse($lessons as $l)<div class="col-md-6"><article class="glass-card p-4"><div class="eyebrow">{{ $l->lesson_date->format('d.m.Y') }} · {{ substr($l->starts_at,0,5) }}</div><h3 class="mt-3">{{ $l->title }}</h3><div class="text-white-50">{{ $l->group->name ?? '' }} @if($l->room) · {{ $l->room }} @endif</div></article></div>@empty<div class="text-white-50">Ближайших занятий нет.</div>@endforelse</div>
<div class="section-head"><div><div class="eyebrow">Homework</div><h2>Домашние задания</h2></div></div>
<div class="row g-3 mb-5">@forelse($homework as $h)<div class="col-md-6"><article class="glass-card p-4 h-100"><div class="small text-white-50">{{ $h->subject->title ?? '' }} · {{ $h->group->name ?? '' }}</div><h3>{{ $h->title }}</h3><p>{{ IlluminateSupportStr::limit($h->description,180) }}</p><div class="d-flex justify-content-between align-items-center"><span class="badge-soft">{{ optional($h->due_at)->format('d.m.Y H:i') ?: 'Без срока' }}</span><a class="btn btn-ghost btn-sm" href="{{ route('academic.homework',$h) }}">Открыть</a></div></article></div>@empty<div class="text-white-50">Заданий нет.</div>@endforelse</div>
<div class="section-head"><div><div class="eyebrow">Journal</div><h2>Оценки и посещаемость</h2></div></div>
<div class="glass-card p-4"><div class="table-responsive"><table class="table admin-table"><thead><tr><th>Дата</th><th>Предмет</th><th>Тема</th><th>Посещение</th><th>Оценка</th></tr></thead><tbody>@forelse($grades as $g)<tr><td>{{ $g->lesson->lesson_date->format('d.m.Y') }}</td><td>{{ $g->lesson->subject->title ?? '' }}</td><td>{{ $g->lesson->topic }}</td><td>{{ ['present'=>'Был','absent'=>'Отсутствовал','late'=>'Опоздал','excused'=>'Уваж. причина'][$g->attendance] ?? $g->attendance }}</td><td><strong>{{ $g->grade ?? $g->grade_label ?? '—' }}</strong></td></tr>@empty<tr><td colspan="5" class="text-white-50">Оценок пока нет.</td></tr>@endforelse</tbody></table></div></div>
</div></section>
@endsection
