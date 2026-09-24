<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Сертификат {{ $attempt->certificate_code }}</title>
<style>
body{margin:0;background:#05070b;color:#fff;font-family:Arial,sans-serif}.wrap{min-height:100vh;display:grid;place-items:center;padding:30px}.cert{width:min(1100px,92vw);aspect-ratio:1.414/1;border:1px solid #28e4ff;background:radial-gradient(circle at 15% 20%,rgba(138,92,255,.35),transparent 28%),radial-gradient(circle at 85% 75%,rgba(0,229,255,.22),transparent 28%),#090d14;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;padding:7%;box-sizing:border-box;position:relative;box-shadow:0 0 80px rgba(0,229,255,.12)}.cert:before,.cert:after{content:"";position:absolute;inset:18px;border:1px solid rgba(255,255,255,.12)}.cert:after{inset:32px;border-color:rgba(138,92,255,.3)}.ey{letter-spacing:.35em;color:#7cecff;font-size:13px;text-transform:uppercase}.name{font-size:clamp(38px,6vw,86px);margin:28px 0 18px}.quiz{font-size:clamp(22px,3vw,38px);max-width:800px}.score{margin-top:18px;color:#d6c7ff}.code{position:absolute;bottom:48px;font-size:12px;letter-spacing:.18em;color:#8893a4}.print{position:fixed;right:20px;top:20px;padding:12px 18px;border:1px solid #28e4ff;background:#0b111a;color:white;border-radius:999px}@media print{.print{display:none}.wrap{padding:0}.cert{width:100vw;box-shadow:none}}</style></head>
<body><button class="print" onclick="window.print()">Печать / PDF</button><div class="wrap"><div class="cert">
<div class="ey">Школа креативных индустрий · Волжск</div>
<div style="font-size:22px;margin-top:26px">СЕРТИФИКАТ</div>
<div style="margin-top:18px;color:#aeb7c7">подтверждает успешное прохождение викторины</div>
<div class="name">{{ $attempt->user->name }}</div>
<div class="quiz">{{ $attempt->quiz->title }}</div>
<div class="score">Результат: {{ $attempt->score }}% · {{ $attempt->completed_at->format('d.m.Y') }}</div>
<div class="code">КОД ПРОВЕРКИ: {{ $attempt->certificate_code }}</div>
</div></div></body></html>
