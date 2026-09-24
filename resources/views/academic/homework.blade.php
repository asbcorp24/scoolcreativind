@extends('layouts.app')
@section('title',$assignment->title.' · Домашнее задание')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">{{ $assignment->subject->title ?? 'Домашнее задание' }} · {{ $assignment->group->name ?? '' }}</div><h1 class="display-3 fw-bold">{{ $assignment->title }}</h1><p class="lead text-white-50">{{ $assignment->description }}</p><div class="d-flex gap-2 flex-wrap"><span class="badge-soft">До {{ optional($assignment->due_at)->format('d.m.Y H:i') ?: 'без срока' }}</span><span class="badge-soft">Макс. {{ $assignment->max_score }}</span></div></div></section>
<section class="pb-5"><div class="container"><form class="form-shell" method="post" enctype="multipart/form-data" action="{{ route('academic.homework.submit',$assignment) }}">@csrf
@if($assignment->attachment_url)<a class="btn btn-ghost mb-3" href="{{ $assignment->attachment_url }}" target="_blank">Файл задания ↗</a>@endif
@if($assignment->external_url)<a class="btn btn-ghost mb-3" href="{{ $assignment->external_url }}" target="_blank">Материал ↗</a>@endif
<div class="mb-3"><label class="form-label">Ответ</label><textarea class="form-control" rows="7" name="text_answer">{{ old('text_answer',$submission->text_answer) }}</textarea></div>
<div class="mb-3"><label class="form-label">Ссылка</label><input class="form-control" name="external_url" value="{{ old('external_url',$submission->external_url) }}"></div>
<div class="mb-3"><label class="form-label">Файл</label><input type="file" class="form-control" name="file"></div>
@if($submission->score!==null)<div class="alert alert-success">Оценка: <strong>{{ $submission->score }}</strong><br>{{ $submission->teacher_comment }}</div>@endif
<button class="btn btn-neon">Отправить работу</button>
</form></div></section>
@endsection
