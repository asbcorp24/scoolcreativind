#include "ScreenshotCapture.h"

#include <QApplication>
#include <QDialog>
#include <QGuiApplication>
#include <QInputDialog>
#include <QLabel>
#include <QMouseEvent>
#include <QPainter>
#include <QPixmap>
#include <QScreen>
#include <QTimer>
#include <QVBoxLayout>
#include <QWidget>

#ifdef Q_OS_WIN
#include <windows.h>
#endif

namespace {

void settleDesktop(int ms = 300) {
    QEventLoop loop;
    QTimer::singleShot(ms, &loop, &QEventLoop::quit);
    loop.exec();
}

class RegionDialog final : public QDialog {
public:
    RegionDialog(QScreen* screen, const QImage& background, QWidget* parent = nullptr)
        : QDialog(parent), screen_(screen), background_(background) {
        setWindowFlags(Qt::FramelessWindowHint | Qt::WindowStaysOnTopHint | Qt::Tool);
        setModal(true);
        setCursor(Qt::CrossCursor);
        setGeometry(screen_->geometry());
        setMouseTracking(true);
    }

    QRect selectedRect() const { return selection_.normalized(); }

protected:
    void paintEvent(QPaintEvent*) override {
        QPainter p(this);
        p.setRenderHint(QPainter::Antialiasing);
        p.drawImage(rect(), background_);

        p.fillRect(rect(), QColor(0, 0, 0, 95));
        if (!selection_.isNull()) {
            const QRect r = selection_.normalized();
            p.save();
            p.setClipRect(r);
            p.drawImage(rect(), background_);
            p.restore();

            p.setPen(QPen(QColor(0, 180, 255), 2));
            p.setBrush(Qt::NoBrush);
            p.drawRect(r);

            const QString text = QString("%1 × %2").arg(r.width()).arg(r.height());
            QRect label = QFontMetrics(font()).boundingRect(text).adjusted(-8, -4, 8, 4);
            label.moveTopLeft(r.topLeft() + QPoint(8, 8));
            p.fillRect(label, QColor(0, 0, 0, 180));
            p.setPen(Qt::white);
            p.drawText(label, Qt::AlignCenter, text);
        }

        p.setPen(Qt::white);
        p.setFont(QFont(font().family(), 13, QFont::Bold));
        p.drawText(rect().adjusted(16, 16, -16, -16),
                   Qt::AlignTop | Qt::AlignHCenter,
                   "Выделите область мышью • Esc — отмена");
    }

    void mousePressEvent(QMouseEvent* e) override {
        if (e->button() != Qt::LeftButton) return;
        start_ = e->position().toPoint();
        selection_ = QRect(start_, start_);
        dragging_ = true;
        update();
    }

    void mouseMoveEvent(QMouseEvent* e) override {
        if (!dragging_) return;
        selection_ = QRect(start_, e->position().toPoint());
        update();
    }

    void mouseReleaseEvent(QMouseEvent* e) override {
        if (!dragging_ || e->button() != Qt::LeftButton) return;
        dragging_ = false;
        selection_ = QRect(start_, e->position().toPoint()).normalized();
        if (selection_.width() >= 10 && selection_.height() >= 10)
            accept();
        else
            selection_ = {};
        update();
    }

    void keyPressEvent(QKeyEvent* e) override {
        if (e->key() == Qt::Key_Escape) {
            reject();
            return;
        }
        QDialog::keyPressEvent(e);
    }

private:
    QScreen* screen_{};
    QImage background_;
    QPoint start_;
    QRect selection_;
    bool dragging_{false};
};

struct WindowItem {
#ifdef Q_OS_WIN
    HWND hwnd{};
#endif
    QString title;
};

#ifdef Q_OS_WIN
BOOL CALLBACK enumWindowProc(HWND hwnd, LPARAM lParam) {
    auto* list = reinterpret_cast<QList<WindowItem>*>(lParam);
    if (!IsWindowVisible(hwnd) || IsIconic(hwnd))
        return TRUE;

    const LONG exStyle = GetWindowLongW(hwnd, GWL_EXSTYLE);
    if (exStyle & WS_EX_TOOLWINDOW)
        return TRUE;

    wchar_t titleBuf[512]{};
    const int len = GetWindowTextW(hwnd, titleBuf, 511);
    if (len <= 0)
        return TRUE;

    RECT rc{};
    if (!GetWindowRect(hwnd, &rc) || rc.right - rc.left < 80 || rc.bottom - rc.top < 60)
        return TRUE;

    const QString title = QString::fromWCharArray(titleBuf, len).trimmed();
    if (title.isEmpty())
        return TRUE;

    list->append(WindowItem{hwnd, title});
    return TRUE;
}

QList<WindowItem> enumerateWindows(WId ownWindow) {
    QList<WindowItem> out;
    EnumWindows(enumWindowProc, reinterpret_cast<LPARAM>(&out));
    out.erase(std::remove_if(out.begin(), out.end(), [ownWindow](const WindowItem& w) {
        return reinterpret_cast<WId>(w.hwnd) == ownWindow;
    }), out.end());
    return out;
}
#endif

QScreen* chooseScreen(QWidget* owner) {
    const auto screens = QGuiApplication::screens();
    if (screens.isEmpty())
        return nullptr;
    if (screens.size() == 1)
        return screens.first();

    QStringList labels;
    for (int i = 0; i < screens.size(); ++i) {
        const auto* s = screens.at(i);
        labels << QString("%1 — %2×%3")
                      .arg(s->name())
                      .arg(s->geometry().width())
                      .arg(s->geometry().height());
    }

    bool ok = false;
    const QString selected = QInputDialog::getItem(
        owner, "Скриншот результата", "Какой экран снимать:",
        labels, 0, false, &ok);
    if (!ok)
        return nullptr;
    const int idx = labels.indexOf(selected);
    return idx >= 0 ? screens.at(idx) : nullptr;
}

QWidget* topLevel(QWidget* owner) {
    return owner ? owner->window() : nullptr;
}

void hideStudent(QWidget* owner, bool* wasVisible, bool* wasMaximized) {
    QWidget* w = topLevel(owner);
    if (!w) return;
    *wasVisible = w->isVisible();
    *wasMaximized = w->isMaximized();
    w->hide();
    QApplication::processEvents(QEventLoop::AllEvents, 100);
    settleDesktop();
}

void restoreStudent(QWidget* owner, bool wasVisible, bool wasMaximized) {
    QWidget* w = topLevel(owner);
    if (!w || !wasVisible) return;
    if (wasMaximized)
        w->showMaximized();
    else
        w->show();
    w->raise();
    w->activateWindow();
}

} // namespace

QImage ScreenshotCapture::capture(QWidget* owner, QString* description) {
    const QStringList choices{
        "Весь экран",
        "Конкретное окно",
        "Выделенная область"
    };

    bool ok = false;
    const QString mode = QInputDialog::getItem(
        owner,
        "Скриншот результата",
        "Что нужно снять:",
        choices,
        0,
        false,
        &ok);
    if (!ok)
        return {};

    bool wasVisible = false;
    bool wasMaximized = false;

    if (mode == "Весь экран") {
        QScreen* screen = chooseScreen(owner);
        if (!screen)
            return {};

        hideStudent(owner, &wasVisible, &wasMaximized);
        const QImage image = screen->grabWindow(0).toImage();
        restoreStudent(owner, wasVisible, wasMaximized);

        if (description)
            *description = "экран " + screen->name();
        return image;
    }

    if (mode == "Выделенная область") {
        QScreen* screen = chooseScreen(owner);
        if (!screen)
            return {};

        hideStudent(owner, &wasVisible, &wasMaximized);
        const QImage full = screen->grabWindow(0).toImage();
        if (full.isNull()) {
            restoreStudent(owner, wasVisible, wasMaximized);
            return {};
        }

        RegionDialog dlg(screen, full);
        const int result = dlg.exec();
        const QRect selected = dlg.selectedRect();
        if (result != QDialog::Accepted || selected.isEmpty()) {
            restoreStudent(owner, wasVisible, wasMaximized);
            return {};
        }

        const qreal dpr = full.devicePixelRatio();
        QRect pixelRect(
            qRound(selected.x() * dpr),
            qRound(selected.y() * dpr),
            qRound(selected.width() * dpr),
            qRound(selected.height() * dpr));
        pixelRect = pixelRect.intersected(full.rect());
        const QImage image = full.copy(pixelRect);
        restoreStudent(owner, wasVisible, wasMaximized);

        if (description)
            *description = QString("область %1×%2").arg(selected.width()).arg(selected.height());
        return image;
    }

#ifdef Q_OS_WIN
    QWidget* w = topLevel(owner);
    const WId ownId = w ? w->winId() : 0;
    const QList<WindowItem> windows = enumerateWindows(ownId);
    if (windows.isEmpty())
        return {};

    QStringList labels;
    QHash<QString, int> indexByLabel;
    for (int i = 0; i < windows.size(); ++i) {
        QString label = windows.at(i).title;
        if (indexByLabel.contains(label))
            label += QString(" [%1]").arg(i + 1);
        indexByLabel[label] = i;
        labels << label;
    }

    const QString selected = QInputDialog::getItem(
        owner,
        "Скриншот результата",
        "Какое окно снимать:",
        labels,
        0,
        false,
        &ok);
    if (!ok)
        return {};

    const int idx = indexByLabel.value(selected, -1);
    if (idx < 0)
        return {};

    const WindowItem item = windows.at(idx);
    RECT rc{};
    if (!GetWindowRect(item.hwnd, &rc))
        return {};

    const QPoint center((rc.left + rc.right) / 2, (rc.top + rc.bottom) / 2);
    QScreen* screen = QGuiApplication::screenAt(center);
    if (!screen)
        screen = QGuiApplication::primaryScreen();
    if (!screen)
        return {};

    hideStudent(owner, &wasVisible, &wasMaximized);

    // Bring the target window to the front before capture.
    ShowWindow(item.hwnd, SW_RESTORE);
    SetForegroundWindow(item.hwnd);
    settleDesktop(250);

    const QImage image = screen->grabWindow(reinterpret_cast<WId>(item.hwnd)).toImage();
    restoreStudent(owner, wasVisible, wasMaximized);

    if (description)
        *description = "окно «" + item.title + "»";
    return image;
#else
    Q_UNUSED(owner);
    return {};
#endif
}
