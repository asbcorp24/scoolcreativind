# Classroom Remote

Отдельный модуль системы удалённого занятия для ШКИ.

## Архитектура

Клиенты остаются нативными C++/Qt, а сервер теперь полностью автономный на Go.

```
ClassroomTeacher.exe ─┐
                     ├── HTTPS/WSS + WebRTC ── classroom-server
ClassroomStudent.exe ─┘                         ├─ Pion WebRTC RTP relay
                                               ├─ встроенный TURN/STUN
                                               ├─ встроенный HTTPS/ACME
                                               ├─ встроенная web-панель
                                               ├─ SQLite
                                               └─ storage/
```

Для работы сервера больше не обязательны PostgreSQL, coturn, Caddy, Redis или Docker.

## Teacher.exe — C++20 / Qt 6

- подключение к занятию через WSS/WS;
- список подключённых учеников;
- поднятая рука;
- трансляция экрана или камеры преподавателя;
- общий микрофон;
- просмотр realtime-экрана выбранного ученика;
- отдельный режим удалённого управления мышью и клавиатурой;
- приватный двусторонний аудиодиалог;
- скачивание секундных кадров ученика в ZIP;
- MP4 экспорт доступен дополнительно, если рядом с сервером установлен FFmpeg.

## Student.exe — C++20 / Qt 6

- видит только лекцию преподавателя;
- не видит список других учеников;
- кнопка «Поднять руку»;
- принимает H.264 и Opus;
- раз в секунду отправляет архивный snapshot;
- realtime-экран включается только по запросу преподавателя;
- управление включается отдельно от простого просмотра;
- при удалённом подключении показывается постоянный индикатор;
- вход выполняется только в рамках активной серверной сессии.

## Realtime transport

Realtime-медиа передаётся обычными WebRTC RTP/SRTP media tracks:

- H.264 Baseline/Constrained Baseline, packetization-mode=1;
- 1280×720, около 10 FPS;
- целевой video bitrate ~1.8 Мбит/с;
- Opus 48 kHz mono;
- Opus frame 20 ms;
- целевой audio bitrate ~64 кбит/с.

Треки:

```
lecture-video   H.264  Teacher -> Go -> Students
lecture-audio   Opus   Teacher -> Go -> Students
private-audio   Opus   Teacher -> Go -> selected Student
remote-video    H.264  selected Student -> Go -> Teacher
remote-audio    Opus   selected Student -> Go -> Teacher
data            SCTP   mouse/keyboard/control JSON
```

Go/Pion пересылает RTP без декодирования и повторного кодирования. DataChannel больше не используется для JPEG/PCM realtime.

JPEG остаётся только для архивного snapshot раз в секунду.

## Автономный Go-сервер

Основной результат сборки:

```
Windows:
classroom-server.exe

Linux:
classroom-server
```

Один и тот же исходный код работает на Windows и Linux.

Внутри бинарника:

- HTTP/HTTPS;
- WebSocket/WSS;
- Pion WebRTC;
- H.264/Opus RTP relay;
- DataChannel relay;
- Pion TURN/STUN;
- SQLite;
- web-панель;
- авторизация Teacher/Student;
- snapshot storage;
- ZIP export;
- audit log;
- автоматический Let's Encrypt ACME при наличии домена.

SQLite реализован pure-Go драйвером, поэтому сервер собирается с `CGO_ENABLED=0`.

## Первый запуск на Windows

Скачайте `classroom-server.exe` и запустите:

```bat
classroom-server.exe
```

При первом запуске автоматически создаются:

```
config.json
data/
├── classroom.db
├── storage/
└── acme-cache/
```

Сервер сам генерирует и сохраняет:

- `teacher_token`;
- `student_token`;
- TURN password.

По умолчанию локальный сервер:

```
http://IP_КОМПЬЮТЕРА:8080
ws://IP_КОМПЬЮТЕРА:8080/ws
```

Для открытия портов Windows есть:

```
server/windows-firewall.bat
```

Запускать его нужно от администратора.

## config.json

Пример:

```json
{
  "http_addr": ":8080",
  "https_addr": ":443",
  "domain": "",
  "acme_email": "",
  "public_ip": "",
  "storage_dir": "./data/storage",
  "db_path": "./data/classroom.db",
  "teacher_token": "...",
  "student_token": "...",
  "rtc_min_port": 50000,
  "rtc_max_port": 50100,
  "turn_enabled": true,
  "turn_listen": ":3478",
  "turn_realm": "classroom",
  "turn_username": "classroom",
  "turn_password": "...",
  "turn_min_port": 49160,
  "turn_max_port": 49260,
  "ffmpeg_path": ""
}
```

### Локальная сеть

Для одного кабинета можно оставить:

```json
"domain": "",
"public_ip": ""
```

В этом случае сервер работает по HTTP/WS и WebRTC использует локальные ICE candidates.

### Публичный VPS

Задать:

```json
"domain": "classroom.example.ru",
"acme_email": "admin@example.ru",
"public_ip": "1.2.3.4"
```

Тогда один Go-процесс:

- слушает 80 для ACME;
- сам получает сертификат Let's Encrypt;
- слушает 443 для HTTPS/WSS;
- поднимает TURN/STUN на 3478;
- выдаёт Teacher/Student готовые TURN credentials при входе.

Caddy и coturn для этого больше не нужны.

## Порты публичного сервера

```
TCP 80             ACME
TCP 443            HTTPS / WSS
TCP/UDP 3478       встроенный TURN/STUN
UDP 49160-49260    TURN relay
UDP 50000-50100    Pion WebRTC ICE
```

## Автоматическая ICE/TURN конфигурация

После WebSocket-входа сервер отправляет:

```json
{
  "type": "server_config",
  "ice_servers": [
    "turn://classroom:PASSWORD@classroom.example.ru:3478?transport=udp",
    "turn://classroom:PASSWORD@classroom.example.ru:3478?transport=tcp",
    "stun://classroom.example.ru:3478"
  ]
}
```

Teacher и Student автоматически передают этот список в libdatachannel. Ручная настройка TURN на клиентских компьютерах не требуется.

## Подключение по ссылкам

Для обычного использования вручную вводить сервер, номер занятия и токены больше не нужно.

На web-панели сервера:

```
http://SERVER:8080/
```

администратор вводит master `teacher_token`, указывает номер занятия и нажимает:

- **Создать ссылку преподавателя**;
- **Создать ссылку учеников**.

Получаются ссылки вида:

```
http://SERVER:8080/join/3f9c...
```

При создании новой ссылки той же роли для того же занятия предыдущая ссылка автоматически отзывается.

Invite-код ограничен:
- ролью `teacher` или `student`;
- конкретным номером занятия.

Teacher-ссылка не может использоваться Student-клиентом и наоборот.

На Windows приложения при запуске регистрируют для текущего пользователя:

```
classroomteacher://
classroomstudent://
```

После этого открытие обычной HTTP invite-ссылки в браузере запускает соответствующее приложение. Приложение само получает с сервера:

- WS/WSS адрес;
- HTTP/HTTPS адрес;
- номер занятия;
- ограниченный access key.

Если URL-протокол ещё не зарегистрирован, эту же invite-ссылку можно вставить в поле **Ссылка** в Teacher или Student и нажать **Войти по ссылке**.

Старые поля сервера/занятия/ключа оставлены под кнопкой **Ручная настройка** только для диагностики.

## Web-авторизация сервера

Web-панель больше не хранит `teacher_token` в JavaScript/localStorage.

На компьютере сервера запускайте:

```bat
open-admin.bat
```

Откроется `http://127.0.0.1:8080/`. Сервер распознаёт loopback-запрос и автоматически создаёт защищённую HttpOnly-cookie на основе `teacher_token`. Сам токен в URL и браузерный JavaScript не передаётся.

При удалённом открытии панели токен вводится один раз. После успешного входа сервер выдаёт HttpOnly-cookie на 30 дней.

## Уроки, материалы и домашние работы

Внутри каждого classroom/занятия теперь хранится история уроков.

Урок содержит:

- тему;
- описание / содержание;
- текст домашнего задания;
- порядок;
- срок сдачи;
- флаг публикации;
- прикреплённые документы.

Преподаватель может работать с уроками через web-панель сервера или вкладку **Уроки и ДЗ** в `ClassroomTeacher.exe`.

Ученик во вкладке **Уроки и домашние работы** видит все опубликованные старые уроки, содержание, домашнее задание и документы. По любому опубликованному уроку можно выбрать один или несколько файлов и отправить домашнюю работу, в том числе позже.

Преподаватель видит список сдач по выбранному уроку и скачивает приложенные файлы.

Файлы хранятся вне web-root:

```
data/storage/lesson-materials/<classroom>/<lesson>/
data/storage/homework/<classroom>/<lesson>/<submission>/
```

Максимальный размер задаётся в `config.json`:

```json
"max_upload_mb": 50
```

Для домашней работы лимит применяется к суммарному размеру файлов одной отправки. Сервер самостоятельно отклоняет превышение с HTTP 413.

## SQLite

Файл:

```
data/classroom.db
```

Сейчас в нём хранятся:

- индекс snapshot;
- audit log подключений;
- start/stop просмотра;
- start/stop управления;
- start/stop аудиодиалога.

База работает в WAL режиме.

## Web-панель сервера

Открыть:

```
http://SERVER:8080/
```

или в публичном режиме:

```
https://classroom.example.ru/
```

Для просмотра списка подключённых пользователей вводится `teacher_token`.

## Записи

Snapshot ученика сохраняется примерно раз в секунду:

```
data/storage/{lesson}/{student}/{timestamp}.jpg
```

Встроенный pure-Go экспорт:

```
GET /api/v1/lessons/{lesson}/students/{student}/recording.zip
```

Эта функция не требует никаких внешних программ.

MP4:

```
GET /api/v1/lessons/{lesson}/students/{student}/recording.mp4
```

MP4 оставлен дополнительной функцией. Если в `config.json` заполнен `ffmpeg_path` или FFmpeg найден в PATH, сервер соберёт MP4. Без FFmpeg основной сервер, WebRTC, TURN, SQLite, ZIP-запись и управление работают полностью.

## Сборка сервера

Windows:

```bat
cd classroom-remote\server
build-windows.bat
```

Результат:

```
server\dist\classroom-server.exe
```

Linux:

```bash
cd classroom-remote/server
sh build-linux.sh
```

Результат:

```
server/dist/classroom-server
```

GitHub Actions автоматически собирает оба варианта как отдельные artifacts.

## Сборка C++ клиентов

Нужно:

- Visual Studio 2022/2026;
- Qt 6.5+ MSVC x64;
- CMake;
- Git;
- vcpkg.

Зависимости клиента:

- libdatachannel;
- OpenSSL;
- OpenH264;
- Opus.

Пример:

```bat
set QT_ROOT=C:\Qt\6.8.3\msvc2022_64
set VCPKG_ROOT=C:\vcpkg
cd classroom-remote\client
build-windows.bat
```

## Безопасность

Сервер использует два независимых токена:

- `teacher_token`;
- `student_token`.

Только Teacher может инициировать:

- просмотр экрана;
- управление;
- аудиодиалог;
- скачивание записей.

Student не может отправлять remote-control команды.

На компьютере ученика просмотр и управление визуально обозначаются.

Для следующего production-уровня можно заменить общие teacher/student токены на короткоживущие JWT основной системы ШКИ с привязкой к пользователю, группе и конкретному занятию.

## CI

Проверяются:

- Go unit tests;
- SDP negotiation H.264/Opus;
- Linux Go server;
- Windows Go server cross-build;
- Docker-образ одного Go сервера;
- OpenH264 encode/decode;
- Opus encode/decode;
- сборка Teacher.exe;
- сборка Student.exe.
