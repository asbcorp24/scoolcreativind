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

Первая рабочая версия использует WebRTC SCTP/DataChannel:

- JPEG 1280x720, около 10 FPS для realtime-видео;
- PCM S16 mono 16 kHz для аудио;
- JSON для mouse/keyboard remote control.

То есть сеть уже идёт через настоящий WebRTC/ICE/DTLS/SCTP (libdatachannel <-> Pion), но JPEG/PCM пока передаются как DataChannel payload, а не H.264/Opus RTP media tracks.

Это сделано намеренно: такой вариант проще запустить и диагностировать на VPS. Следующий media-уровень можно заменить на H.264/Opus RTP без изменения UI, комнат, signaling, записи и remote-control протокола.

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
```

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

- Visual Studio 2022 с Desktop development with C++;
- CMake;
- Qt 6.5+ MSVC 2022 x64 с модулями Multimedia, WebSockets;
- Git.

`libdatachannel v0.24.6` подтягивается CMake автоматически через FetchContent.

Пример:

```bat
set QT_ROOT=C:\Qt\6.8.3\msvc2022_64
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

## Что обязательно сделать перед публичным production

Текущая версия предназначена для функционального тестирования. Перед открытием сервера в Интернет необходимо подключить авторизацию Teacher/Student к основной системе ШКИ: JWT, права на конкретное занятие, временные TURN credentials, rate limit, журнал start/stop удалённого управления и retention policy для записей.

GitHub Actions `Classroom Remote Server` проверяет форматирование и сборку Go-сервера после изменений.
