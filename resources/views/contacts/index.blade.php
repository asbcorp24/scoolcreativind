@extends('layouts.app')
@section('title','Контакты · ШКИ')
@section('content')
@php
$lat=$settings['contact_lat'] ?? '55.866';
$lng=$settings['contact_lng'] ?? '48.359';
$zoom=$settings['contact_map_zoom'] ?? '17';
$address=$settings['contact_address'] ?? 'г. Волжск, ул. Ленина, 32';
$phone=$settings['contact_phone'] ?? '+7 (836) 316-46-28';
@endphp

<section class="page-top"><div class="container"><div class="eyebrow">Contacts</div><h1 class="display-1 fw-bold scroll-title">{{ $settings['contact_title'] ?? 'Контакты' }}</h1><p class="lead text-white-50 col-lg-8">{{ $settings['contact_intro'] ?? 'Свяжитесь со Школой креативных индустрий по вопросам поступления, обучения, мероприятий и сотрудничества.' }}</p></div></section>

<section class="pb-5"><div class="container">
 <div class="row g-4">
  <div class="col-lg-5">
   <div class="glass-card p-4 h-100 contact-card">
    <div class="eyebrow">Школа креативных индустрий</div>
    <h2 class="display-6 fw-bold mt-3">{{ $settings['contact_org_name'] ?? 'Школа креативных индустрий · Волжск' }}</h2>

    <div class="contact-list mt-4">
      <div><small>Адрес</small><strong>{{ $address }}</strong></div>
      <div><small>Телефон</small><a href="tel:{{ preg_replace('/[^+0-9]/','',$phone) }}">{{ $phone }}</a></div>
      @if(!empty($settings['contact_phone_extra']))<div><small>Дополнительный телефон</small><a href="tel:{{ preg_replace('/[^+0-9]/','',$settings['contact_phone_extra']) }}">{{ $settings['contact_phone_extra'] }}</a></div>@endif
      @if(!empty($settings['contact_email']))<div><small>Email</small><a href="mailto:{{ $settings['contact_email'] }}">{{ $settings['contact_email'] }}</a></div>@endif
      @if(!empty($settings['contact_work_hours']))<div><small>Режим работы</small><strong>{{ $settings['contact_work_hours'] }}</strong></div>@endif
    </div>

    <div class="d-flex gap-2 flex-wrap mt-4">
      @if(!empty($settings['contact_vk']))<a class="btn btn-ghost" href="{{ $settings['contact_vk'] }}" target="_blank" rel="noopener">VK ↗</a>@endif
      @if(!empty($settings['contact_telegram']))<a class="btn btn-ghost" href="{{ $settings['contact_telegram'] }}" target="_blank" rel="noopener">Telegram ↗</a>@endif
      <a class="btn btn-neon" href="{{ route('questions.create') }}">Задать вопрос</a>
    </div>

    @if(!empty($settings['contact_requisites']))
    <div class="contact-requisites mt-4">
      <div class="eyebrow">Реквизиты</div>
      <div class="mt-2">{!! nl2br(e($settings['contact_requisites'])) !!}</div>
    </div>
    @endif
   </div>
  </div>

  <div class="col-lg-7">
    <div class="contact-map-shell">
      <iframe
        title="Школа креативных индустрий на карте"
        src="https://www.openstreetmap.org/export/embed.html?bbox={{ $lng-0.01 }}%2C{{ $lat-0.006 }}%2C{{ $lng+0.01 }}%2C{{ $lat+0.006 }}&layer=mapnik&marker={{ $lat }}%2C{{ $lng }}"
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
    <div class="mt-3 d-flex gap-2 flex-wrap">
      <a class="btn btn-ghost" href="https://www.openstreetmap.org/?mlat={{ $lat }}&mlon={{ $lng }}#map={{ $zoom }}/{{ $lat }}/{{ $lng }}" target="_blank" rel="noopener">Открыть карту ↗</a>
      <a class="btn btn-ghost" href="https://yandex.ru/maps/?pt={{ $lng }},{{ $lat }}&z={{ $zoom }}&l=map" target="_blank" rel="noopener">Яндекс Карты ↗</a>
    </div>
  </div>
 </div>
</div></section>
@endsection
