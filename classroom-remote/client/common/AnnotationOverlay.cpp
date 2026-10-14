#include "AnnotationOverlay.h"

#include <QInputDialog>
#include <QJsonValue>
#include <QMouseEvent>
#include <QPainter>
#include <QtMath>

AnnotationOverlay::AnnotationOverlay(QWidget* parent) : QWidget(parent) {
    setAttribute(Qt::WA_TranslucentBackground);
    setMouseTracking(true);
    setFocusPolicy(Qt::NoFocus);
}

void AnnotationOverlay::setEditable(bool editable) {
    editable_ = editable;
    setAttribute(Qt::WA_TransparentForMouseEvents, !editable_);
}

void AnnotationOverlay::setTool(Tool tool) {
    tool_ = tool;
    setCursor(tool_ == Tool::Off ? Qt::ArrowCursor : Qt::CrossCursor);
}

QPointF AnnotationOverlay::normalized(const QPointF& p) const {
    const double w = qMax(1, width());
    const double h = qMax(1, height());
    return QPointF(qBound(0.0, p.x() / w, 1.0),
                   qBound(0.0, p.y() / h, 1.0));
}

QPointF AnnotationOverlay::denormalized(double x, double y) const {
    return QPointF(x * width(), y * height());
}

void AnnotationOverlay::addShape(const QJsonObject& obj) {
    if (obj.value("kind").toString() == "laser") {
        for (int i = shapes_.size() - 1; i >= 0; --i) {
            if (shapes_.at(i).value("kind").toString() == "laser")
                shapes_.removeAt(i);
        }
    }
    shapes_.append(obj);
    if (shapes_.size() > 4000)
        shapes_.remove(0, shapes_.size() - 4000);
    update();
}

void AnnotationOverlay::applyRemote(const QJsonObject& obj) {
    if (obj.value("type").toString() == "annotation_clear") {
        clearAll();
        return;
    }
    if (obj.value("type").toString() == "annotation")
        addShape(obj);
}

void AnnotationOverlay::clearAll() {
    shapes_.clear();
    update();
}

void AnnotationOverlay::emitShape(const QString& kind, const QPointF& a,
                                  const QPointF& b, const QString& text) {
    const QPointF na = normalized(a);
    const QPointF nb = normalized(b);
    QJsonObject obj{
        {"type", "annotation"},
        {"kind", kind},
        {"x1", na.x()}, {"y1", na.y()},
        {"x2", nb.x()}, {"y2", nb.y()}
    };
    if (!text.isEmpty())
        obj["text"] = text;
    addShape(obj);
    emit annotationCreated(obj);
}

void AnnotationOverlay::mousePressEvent(QMouseEvent* e) {
    if (!editable_ || tool_ == Tool::Off || e->button() != Qt::LeftButton)
        return;
    dragging_ = true;
    start_ = e->position();
    last_ = start_;

    if (tool_ == Tool::Laser)
        emitShape("laser", start_, start_);
}

void AnnotationOverlay::mouseMoveEvent(QMouseEvent* e) {
    if (!editable_ || !dragging_)
        return;

    if (tool_ == Tool::Marker) {
        emitShape("marker", last_, e->position());
        last_ = e->position();
    } else if (tool_ == Tool::Laser) {
        emitShape("laser", e->position(), e->position());
    }
}

void AnnotationOverlay::mouseReleaseEvent(QMouseEvent* e) {
    if (!editable_ || !dragging_ || e->button() != Qt::LeftButton)
        return;
    dragging_ = false;

    switch (tool_) {
    case Tool::Arrow:
        emitShape("arrow", start_, e->position());
        break;
    case Tool::Rectangle:
        emitShape("rect", start_, e->position());
        break;
    case Tool::Text: {
        bool ok = false;
        const QString text = QInputDialog::getText(
            this, "Текст аннотации", "Текст:", QLineEdit::Normal, {}, &ok);
        if (ok && !text.trimmed().isEmpty())
            emitShape("text", start_, start_, text.trimmed());
        break;
    }
    case Tool::Laser:
        emitShape("laser", e->position(), e->position());
        break;
    default:
        break;
    }
}

void AnnotationOverlay::paintEvent(QPaintEvent*) {
    QPainter p(this);
    p.setRenderHint(QPainter::Antialiasing, true);

    for (const auto& obj : shapes_) {
        const QString kind = obj.value("kind").toString();
        const QPointF a = denormalized(obj.value("x1").toDouble(),
                                       obj.value("y1").toDouble());
        const QPointF b = denormalized(obj.value("x2").toDouble(),
                                       obj.value("y2").toDouble());

        if (kind == "marker") {
            p.setPen(QPen(QColor(255, 220, 0, 210), 5,
                          Qt::SolidLine, Qt::RoundCap, Qt::RoundJoin));
            p.drawLine(a, b);
        } else if (kind == "rect") {
            p.setPen(QPen(QColor(0, 220, 255), 4));
            p.setBrush(Qt::NoBrush);
            p.drawRect(QRectF(a, b).normalized());
        } else if (kind == "arrow") {
            p.setPen(QPen(QColor(255, 70, 70), 4, Qt::SolidLine, Qt::RoundCap));
            p.drawLine(a, b);
            const double angle = std::atan2(b.y() - a.y(), b.x() - a.x());
            const double len = 16.0;
            const QPointF p1 = b - QPointF(std::cos(angle - 0.55) * len,
                                           std::sin(angle - 0.55) * len);
            const QPointF p2 = b - QPointF(std::cos(angle + 0.55) * len,
                                           std::sin(angle + 0.55) * len);
            p.drawLine(b, p1);
            p.drawLine(b, p2);
        } else if (kind == "text") {
            p.setPen(Qt::white);
            QFont f = font();
            f.setBold(true);
            f.setPointSize(14);
            p.setFont(f);
            const QString text = obj.value("text").toString();
            QRectF box(a + QPointF(6, 6), QSizeF(qMax(180, width() / 3), 100));
            p.fillRect(box.adjusted(-4, -2, 4, 2), QColor(0, 0, 0, 150));
            p.drawText(box, Qt::TextWordWrap, text);
        } else if (kind == "laser") {
            p.setPen(Qt::NoPen);
            p.setBrush(QColor(255, 30, 30, 230));
            p.drawEllipse(a, 9, 9);
            p.setBrush(QColor(255, 255, 255, 180));
            p.drawEllipse(a, 3, 3);
        }
    }
}
