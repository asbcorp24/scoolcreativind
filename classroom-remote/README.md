# Classroom Remote

Отдельный модуль системы удалённого занятия для ШКИ.

## Реализовано

### Teacher.exe — C++20 / Qt 6

- подключение к занятию через WSS;
- список подключённых учеников;
- отображение поднятой руки;
- трансляция рабочего стола преподавателя;
- трансляция камеры преподавателя;
- микрофон преподавателя;
- просмотр realtime-экрана выбранного ученика;
- управление мышью, клавиатурой и колесом мыши выбранного ученика;
- приватный аудиоканал ученик -> преподаватель;
- скачивание записи экрана ученика в MP4.

### Student.exe — C++20 / Qt 6

- ученик не видит список других участников;
- принимает только лекцию преподавателя;
- кнопка «Поднять руку»;
- принимает видео рабочего стола или камеры преподавателя;
- принимает звук преподавателя;
- раз в секунду сохраняет snapshot рабочего стола на сервер;
- по запросу преподавателя включает realtime-передачу экрана;
- показывает постоянный индикатор активного удалённого подключения;
- принимает remote-input через WebRTC и выполняет его через WinAPI SendInput;
- по запросу включает микрофон для аудиодиалога.

### Go server

- WebSocket signaling;
- presence;
- raised hand;
- маршрутизация remote/audio команд;
- Pion WebRTC v4 relay;
- отдельный WebRTC DataChannel на каждого клиента;
- один входящий поток лекции от Teacher -> сервер -> все Students;
- realtime Student -> сервер -> Teacher;
- snapshot storage;
- ZIP export;
- MP4 export через FFmpeg;
- Docker deployment;
- PostgreSQL контейнер подготовлен под следующий слой учётных записей/занятий;
- coturn и Caddy.

## Текущий realtime transport

Realtime-медиа теперь передаётся обычными WebRTC RTP/SRTP media tracks:

- **H.264 Constrained/Baseline, packetization-mode=1**, 1280×720, около 10 FPS, целевой bitrate ~1.8 Мбит/с;
- **Opus 48 kHz mono**, 20 ms frame, целевой bitrate ~64 кбит/с;
- RTP packetization/depacketization выполняет `libdatachannel`;
- сервер на Pion пересылает RTP без декодирования и повторного кодирования;
- DataChannel используется только для JSON-команд удалённого управления и служебных сообщений.

Треки имеют фиксированный порядок и MID:

```
lecture-video   H.264  Teacher -> Server -> Students
lecture-audio   Opus   Teacher -> Server -> Students
private-audio   Opus   Teacher -> Server -> selected Student
remote-video    H.264  selected Student -> Server -> Teacher
remote-audio    Opus   selected Student -> Server -> Teacher
data            SCTP   mouse/keyboard/control signaling
```

При подключении нового ученика сервер отправляет преподавателю запрос на немедленный IDR, поэтому новый просмотр не должен ждать очередной GOP. libdatachannel добавляет RTP packetization, RTCP Sender Reports и NACK handling.

JPEG больше не используется для realtime-видео, PCM больше не передаётся по сети. JPEG оставлен только для **архивного snapshot раз в секунду**, из которого сервер формирует запись MP4.

Кодеки Windows-клиента:
- Cisco OpenH264 — encode/decode H.264;
- libopus — encode/decode Opus;
- OpenSSL/libSRTP через libdatachannel — DTLS/SRTP.

## Структура

```
classroom-remote/
├── client/
│   ├── common/
│   │   ├── WsClient.*
│   │   ├── RtcRelayClient.*
│   │   └── AudioPipe.*
│   ├── teacher/
│   ├── student/
│   ├── CMakeLists.txt
│   └── build-windows.bat
├── server/
│   ├── main.go
│   ├── rtc_relay.go
│   ├── recordings.go
│   ├── go.mod
│   └── Dockerfile
└── deploy/
    ├── docker-compose.yml
    ├── Caddyfile
    ├── turnserver.conf
    ├── .env.example
    └── install-vps.sh
```

## VPS

Требуется VPS/VDS с публичным IPv4.

```bash
cd classroom-remote/deploy
cp .env.example .env
```

В `.env`:

```
PUBLIC_IP=ВАШ_PUBLIC_IPV4
TEACHER_TOKEN=ДЛИННЫЙ_СЛУЧАЙНЫЙ_ТОКЕН_ПРЕПОДАВАТЕЛЯ
STUDENT_TOKEN=ДРУГОЙ_ДЛИННЫЙ_СЛУЧАЙНЫЙ_ТОКЕН_УЧЕНИКОВ
```

Токены можно сгенерировать командами `openssl rand -hex 32`. Они должны быть разными. Teacher-клиент принимает только `TEACHER_TOKEN`, Student-клиент — только `STUDENT_TOKEN`.

В `Caddyfile` заменить:

```
classroom.example.ru
```

на реальный домен.

В `turnserver.conf` заменить домен, пароль и `external-ip`.

Запуск:

```bash
sudo bash install-vps.sh
docker compose up -d --build
```

Проверка:

```bash
curl https://ВАШ_ДОМЕН/health
```

Ожидается:

```
ok
```

### Порты

- TCP 80, 443
- UDP 443
- TCP/UDP 3478
- TCP 5349
- UDP 49160-49260 — coturn relay
- UDP 50000-50100 — Pion WebRTC

## Windows build

Нужно установить:

- Visual Studio 2022 или Visual Studio 2026 с Desktop development with C++;
- CMake;
- Qt 6.5+ MSVC 2022 x64 с модулями Multimedia, WebSockets;
- Git;
- vcpkg.

`libdatachannel v0.24.6` подтягивается CMake автоматически через FetchContent. OpenSSL ставится через `client/vcpkg.json`; MbedTLS не используется, потому что стандартная Windows-сборка MbedTLS из vcpkg может быть без DTLS-SRTP API, необходимого libdatachannel.

Пример:

```bat
set QT_ROOT=C:\Qt\6.8.3\msvc2022_64
set VCPKG_ROOT=C:\vcpkg
cd classroom-remote\client
build-windows.bat
```

После сборки:

```
client\build\Release\ClassroomTeacher.exe
client\build\Release\ClassroomStudent.exe
```

## Snapshot / recording API

Snapshot раз в секунду:

```
POST /api/v1/lessons/{lesson}/students/{student}/snapshot
Content-Type: image/jpeg
```

Последний кадр:

```
GET /api/v1/lessons/{lesson}/students/{student}/snapshot/latest
```

Архив исходных кадров:

```
GET /api/v1/lessons/{lesson}/students/{student}/recording.zip
```

Готовый MP4:

```
GET /api/v1/lessons/{lesson}/students/{student}/recording.mp4
```

MP4 строится из секундных снимков серверным FFmpeg.

## События signaling

```json
{"type":"hand_raise"}
{"type":"hand_down"}
{"type":"remote_request","target":"student-id"}
{"type":"remote_stop","target":"student-id"}
{"type":"audio_request","target":"student-id"}
{"type":"lecture_state","mode":"screen","active":true}
{"type":"rtc_offer","sdp":"..."}
{"type":"rtc_answer","sdp":"..."}
```

## Доступ и production

Публичный сервер теперь требует два независимых токена:
- `TEACHER_TOKEN` для WebSocket преподавателя и скачивания записей;
- `STUDENT_TOKEN` для WebSocket ученика и загрузки секундных snapshot.

Сервер не позволяет student-роли отправлять команды remote-control или audio-call.

Это уже защищает тестовый публичный VPS от анонимного подключения, но для полноценного production следующий уровень — выдавать короткоживущие JWT из основной системы ШКИ с привязкой к пользователю и конкретному занятию. Также нужны временные TURN credentials, rate limit, аудит start/stop удалённого управления и retention policy для записей.

GitHub Actions `Classroom Remote Server` проверяет форматирование и сборку Go-сервера после изменений.


## Надёжность realtime

Видео режется на WebRTC DataChannel-сообщения примерно по 48 КБ и собирается на принимающей стороне. Это позволяет передавать JPEG-кадры, которые превышают безопасный размер одного SCTP сообщения.

Если отправляющая очередь WebRTC превышает 2 МБ, старый видеокадр отбрасывается вместо накопления задержки. Для удалённого управления и звука это позволяет сохранить отзывчивость даже при ухудшении канала.

Режимы просмотра разделены:
- **Просмотр** — преподаватель получает realtime-экран, но ввод не исполняется.
- **Управление** — ученик явно переводится в control-сессию, после чего разрешается WinAPI `SendInput`.

Аудиодиалог преподавателя с поднявшим руку учеником адресный: приватный голос преподавателя получает только выбранный ученик.


## Проверка media-кодеков

Windows CI запускает `MediaCodecSmoke` после сборки. Тест выполняет реальный round-trip:

1. RGB test frame -> I420 -> OpenH264 -> H.264 -> OpenH264 decoder -> QImage;
2. PCM S16 48 kHz -> Opus -> Opus decoder -> PCM.

Серверный Go CI отдельно проверяет SDP negotiation для Teacher и Student с пятью H.264/Opus media m-line, затем собирает Docker image.
