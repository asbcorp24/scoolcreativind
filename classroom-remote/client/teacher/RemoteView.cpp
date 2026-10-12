#include "RemoteView.h"

#include <QKeyEvent>
#include <QMouseEvent>
#include <QWheelEvent>

RemoteView::RemoteView(QWidget* parent) : QLabel(parent) {
    setAlignment(Qt::AlignCenter);
    setFocusPolicy(Qt::StrongFocus);
    setMouseTracking(true);
    setMinimumSize(640, 360);
    setStyleSheet("background:#020617;color:#94a3b8;border:1px solid #334155;border-radius:8px;");
    setText("Экран выбранного ученика");
}

void RemoteView::setTarget(const QString& target) {
    target_ = target;
}

void RemoteView::emitMouse(const QString& type, QMouseEvent* event) {
    if (target_.isEmpty() || width() <= 0 || height() <= 0)
        return;

    const double x = qBound(0.0, event->position().x() / double(width()), 1.0);
    const double y = qBound(0.0, event->position().y() / double(height()), 1.0);
    QString button = "left";
    if (event->button() == Qt::RightButton)
        button = "right";
    else if (event->button() == Qt::MiddleButton)
        button = "middle";

    emit remoteEvent(QJsonObject{
        {"type", "remote_input"},
        {"target", target_},
        {"event", type},
        {"x", x},
        {"y", y},
        {"button", button}
    });
}

void RemoteView::mouseMoveEvent(QMouseEvent* event) { emitMouse("mouse_move", event); }
void RemoteView::mousePressEvent(QMouseEvent* event) { emitMouse("mouse_down", event); }
void RemoteView::mouseReleaseEvent(QMouseEvent* event) { emitMouse("mouse_up", event); }

void RemoteView::wheelEvent(QWheelEvent* event) {
    if (target_.isEmpty())
        return;
    emit remoteEvent(QJsonObject{
        {"type", "remote_input"},
        {"target", target_},
        {"event", "mouse_wheel"},
        {"delta", event->angleDelta().y()}
    });
}

void RemoteView::keyPressEvent(QKeyEvent* event) {
    if (target_.isEmpty())
        return;
    emit remoteEvent(QJsonObject{
        {"type", "remote_input"},
        {"target", target_},
        {"event", "key_down"},
        {"vk", int(event->nativeVirtualKey())},
        {"text", event->text()}
    });
}

void RemoteView::keyReleaseEvent(QKeyEvent* event) {
    if (target_.isEmpty())
        return;
    emit remoteEvent(QJsonObject{
        {"type", "remote_input"},
        {"target", target_},
        {"event", "key_up"},
        {"vk", int(event->nativeVirtualKey())}
    });
}
