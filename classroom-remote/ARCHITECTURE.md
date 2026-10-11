# Архитектура Classroom Remote

## Компоненты

### Teacher (C++20 / Qt 6)
- подключение к занятию;
- список только подключённых учеников;
- raised hand;
- выбор ученика;
- запрос realtime screen;
- запрос remote control;
- аудиодиалог;
- локальный preview камеры/рабочего стола.

### Student (C++20 / Qt 6)
- показывает только лекцию преподавателя;
- кнопка «Поднять руку»;
- snapshot рабочего стола раз в 1 секунду;
- WebRTC screen stream только по запросу преподавателя;
- WinAPI SendInput для разрешённой remote-сессии;
- аудио с преподавателем.

### Go server
- WebSocket signaling;
- presence;
- маршрутизация событий;
- snapshot upload/storage;
- REST API;
- далее: JWT/PostgreSQL/retention/export.

### WebRTC
Основной медиа-трафик не должен идти через REST/WebSocket.

- Teacher lecture -> SFU -> Students.
- Selected Student screen -> Teacher.
- Teacher <-> Student audio.
- Remote input -> WebRTC DataChannel.
- Go WebSocket используется для SDP/ICE signaling и команд состояния.

## Безопасность

Удалённое управление должно включаться только внутри активного занятия и только преподавателем этого занятия. На экране ученика при активном управлении должен отображаться постоянный индикатор. Все start/stop remote events должны журналироваться.

Для production необходимо добавить JWT, TLS, временные TURN credentials и проверку ролей на каждом серверном событии.

## Запись

Постоянная запись учеников — snapshots 1 FPS, а не 30 FPS видео. Рекомендуемый кадр 960x540 WebP q=55. В будущем добавляется change detection: неизменившийся кадр повторно не загружается, а сервер хранит диапазон времени предыдущего кадра.
