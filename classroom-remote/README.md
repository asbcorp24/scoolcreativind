# Classroom Remote

Отдельный модуль системы удалённого занятия.

## Что уже заложено

- Teacher-клиент: C++20 + Qt 6.
- Student-клиент: C++20 + Qt 6.
- Go-сервер: WebSocket signaling, presence, raised hand, команды remote/audio.
- Snapshot API: загрузка WebP-кадров ученика и получение последнего кадра.
- Docker Compose: Go API + PostgreSQL + coturn + Caddy.
- Архитектура рассчитана на публичный VPS.

## Потоки

1. Преподаватель публикует лекцию (экран/камера) через WebRTC.
2. Ученики видят только поток преподавателя.
3. Преподаватель видит присутствующих через WebSocket presence.
4. Ученик отправляет hand_raise.
5. Преподаватель может запросить realtime-экран и управление выбранного ученика.
6. Управление мышью/клавиатурой передаётся через WebRTC DataChannel и исполняется на Windows через SendInput.
7. Student-клиент раз в секунду делает уменьшенный WebP snapshot и отправляет на сервер.
8. Сервер хранит кадры для истории/скачивания.

## Сервер

```bash
cd classroom-remote/deploy
cp Caddyfile Caddyfile.local
# заменить classroom.example.ru на реальный домен
# заменить TURN пароль и указать external-ip
docker compose up -d --build
```

Проверка:

```bash
curl https://classroom.example.ru/health
```

### Порты VPS

- TCP 80, 443
- UDP 443
- TCP/UDP 3478
- TCP 5349
- UDP 49160-49260

## WebSocket

```
wss://classroom.example.ru/ws?lesson=101&id=teacher-1&role=teacher&name=Преподаватель
wss://classroom.example.ru/ws?lesson=101&id=student-12&role=student&name=Иванов
```

Основные сообщения:

```json
{"type":"hand_raise"}
{"type":"hand_down"}
{"type":"remote_request","target":"student-12"}
{"type":"remote_stop","target":"student-12"}
{"type":"audio_request","target":"student-12"}
{"type":"signal","target":"student-12","payload":{}}
{"type":"lecture_state","mode":"screen","active":true}
```

## Snapshot API

```
POST /api/v1/lessons/{lesson}/students/{student}/snapshot
Content-Type: image/webp

GET /api/v1/lessons/{lesson}/students/{student}/snapshot/latest
```

Следующий слой production-hardening: JWT, PostgreSQL-модели пользователей/занятий, временные TURN credentials, rate limit, retention policy, MP4 export, аудит удалённого управления и полноценный WebRTC/SFU.
