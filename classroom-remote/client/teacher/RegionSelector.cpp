#include "RegionSelector.h"

#include <QApplication>
#include <QGuiApplication>
#include <QKeyEvent>
#include <QMouseEvent>
#include <QPainter>
#include <QScreen>

RegionSelector::RegionSelector(QScreen* screen,
                               std::function<void(const QRect&)> callback,
                               QWidget* parent)
    : QWidget(parent),
      screen_(screen),
      callback_(std::move(callback)) {
    setWindowFlags(Qt::Tool | Qt::FramelessWindowHint | Qt::WindowStaysOnTopHint);
    setAttribute(Qt::WA_TranslucentBackground);
    setCursor(Qt::CrossCursor);
    setFocusPolicy(Qt::StrongFocus);

    if (screen_)
        setGeometry(screen_->geometry());

    show();
    raise();
    activateWindow();
    setFocus();
}

void RegionSelector::paintEvent(QPaintEvent*) {
    QPainter p(this);
    p.fillRect(rect(), QColor(0, 0, 0, 115));

    const QRect selected = QRect(start_, current_).normalized();
    if (!selected.isEmpty()) {
        p.setCompositionMode(QPainter::CompositionMode_Clear);
        p.fillRect(selected, Qt::transparent);
        p.setCompositionMode(QPainter::CompositionMode_SourceOver);

        QPen pen(QColor(34, 211, 238));
        pen.setWidth(3);
        p.setPen(pen);
        p.setBrush(Qt::NoBrush);
        p.drawRect(selected);

        const QString label = QString("%1 × %2").arg(selected.width()).arg(selected.height());
        QFont font = p.font();
        font.setPointSize(13);
        font.setBold(true);
        p.setFont(font);

        const QRect textRect = p.fontMetrics().boundingRect(label).adjusted(-8, -5, 8, 5);
        QRect box(selected.topLeft() + QPoint(8, 8), textRect.size());
        p.fillRect(box, QColor(7, 17, 31, 220));
        p.setPen(Qt::white);
        p.drawText(box, Qt::AlignCenter, label);
    }

    p.setPen(Qt::white);
    QFont hint = p.font();
    hint.setPointSize(15);
    hint.setBold(true);
    p.setFont(hint);
    p.drawText(rect().adjusted(20, 20, -20, -20),
               Qt::AlignTop | Qt::AlignHCenter,
               "Выделите область мышью. Esc — отмена");
}

void RegionSelector::mousePressEvent(QMouseEvent* e) {
    if (e->button() != Qt::LeftButton)
        return;
    start_ = e->position().toPoint();
    current_ = start_;
    selecting_ = true;
    update();
}

void RegionSelector::mouseMoveEvent(QMouseEvent* e) {
    if (!selecting_)
        return;
    current_ = e->position().toPoint();
    update();
}

void RegionSelector::mouseReleaseEvent(QMouseEvent* e) {
    if (!selecting_ || e->button() != Qt::LeftButton)
        return;

    selecting_ = false;
    current_ = e->position().toPoint();
    const QRect selected = QRect(start_, current_).normalized().intersected(rect());

    if (selected.width() >= 40 && selected.height() >= 40 && callback_)
        callback_(selected);

    close();
    deleteLater();
}

void RegionSelector::keyPressEvent(QKeyEvent* e) {
    if (e->key() == Qt::Key_Escape) {
        close();
        deleteLater();
        return;
    }
    QWidget::keyPressEvent(e);
}
