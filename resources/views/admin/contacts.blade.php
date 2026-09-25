@extends('layouts.app')
@section('title','Контакты · Админ')
@section('content')
<section class="page-top"><div class="container"><div class="eyebrow">Админ / контакты</div><div class="d-flex justify-content-between align-items-end gap-3 flex-wrap"><h1 class="display-3 fw-bold mb-0">Контакты и карта</h1><a href="{{ route('admin.dashboard') }}" class="btn btn-ghost">← Админка</a></div></div></section>

<section class="pb-5"><div class="container">
<form method="post" action="{{ route('admin.contacts.update') }}" class="glass-card p-4 p-lg-5">@csrf
 <div class="row g-3">
  <div class="col-md-6"><label class="form-label">Заголовок страницы</label><input class="form-control" name="contact_title" value="{{ old('contact_title',$settings['contact_title'] ?? 'Контакты') }}"></div>
  <div class="col-12"><label class="form-label">Вводный текст</label><textarea class="form-control" rows="3" name="contact_intro">{{ old('contact_intro',$settings['contact_intro'] ?? 'Свяжитесь со Школой креативных индустрий по вопросам поступления, обучения, мероприятий и сотрудничества.') }}</textarea></div>
  <div class="col-12"><label class="form-label">Название организации</label><input class="form-control" name="contact_org_name" value="{{ old('contact_org_name',$settings['contact_org_name'] ?? 'Школа креативных индустрий · Волжск') }}"></div>
  <div class="col-12"><label class="form-label">Адрес</label><input class="form-control" name="contact_address" value="{{ old('contact_address',$settings['contact_address'] ?? 'г. Волжск, ул. Ленина, 32') }}"></div>
  <div class="col-md-6"><label class="form-label">Телефон</label><input class="form-control" name="contact_phone" value="{{ old('contact_phone',$settings['contact_phone'] ?? '+7 (836) 316-46-28') }}"></div>
  <div class="col-md-6"><label class="form-label">Дополнительный телефон</label><input class="form-control" name="contact_phone_extra" value="{{ old('contact_phone_extra',$settings['contact_phone_extra'] ?? '') }}"></div>
  <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="contact_email" value="{{ old('contact_email',$settings['contact_email'] ?? '') }}"></div>
  <div class="col-md-6"><label class="form-label">Режим работы</label><input class="form-control" name="contact_work_hours" value="{{ old('contact_work_hours',$settings['contact_work_hours'] ?? '') }}" placeholder="Пн–Пт, 08:00–17:00"></div>
  <div class="col-md-6"><label class="form-label">VK</label><input class="form-control" name="contact_vk" value="{{ old('contact_vk',$settings['contact_vk'] ?? '') }}"></div>
  <div class="col-md-6"><label class="form-label">Telegram</label><input class="form-control" name="contact_telegram" value="{{ old('contact_telegram',$settings['contact_telegram'] ?? '') }}"></div>

  <div class="col-12 mt-4"><h3>Карта</h3><p class="text-white-50 mb-0">Укажите координаты точки школы. Карта на публичной странице обновится автоматически.</p></div>
  <div class="col-md-4"><label class="form-label">Широта</label><input type="number" step="0.000001" class="form-control" name="contact_lat" value="{{ old('contact_lat',$settings['contact_lat'] ?? '55.866') }}"></div>
  <div class="col-md-4"><label class="form-label">Долгота</label><input type="number" step="0.000001" class="form-control" name="contact_lng" value="{{ old('contact_lng',$settings['contact_lng'] ?? '48.359') }}"></div>
  <div class="col-md-4"><label class="form-label">Масштаб</label><input type="number" min="5" max="19" class="form-control" name="contact_map_zoom" value="{{ old('contact_map_zoom',$settings['contact_map_zoom'] ?? 17) }}"></div>

  <div class="col-12 mt-4"><label class="form-label">Реквизиты / дополнительная информация</label><textarea class="form-control" rows="7" name="contact_requisites">{{ old('contact_requisites',$settings['contact_requisites'] ?? '') }}</textarea></div>

  <div class="col-12 text-end mt-3"><button class="btn btn-neon btn-lg">Сохранить контакты</button></div>
 </div>
</form>
</div></section>
@endsection
