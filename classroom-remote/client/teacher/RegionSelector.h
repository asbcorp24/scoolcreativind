#pragma once

#include <QWidget>
#include <QRect>
#include <functional>

class QScreen;

class RegionSelector final : public QWidget {
    Q_OBJECT
public:
    explicit RegionSelector(QScreen* screen,
                            std::function<void(const QRect&)> callback,
                            QWidget* parent = nullptr);

protected:
    void paintEvent(QPaintEvent*) override;
    void mousePressEvent(QMouseEvent*) override;
    void mouseMoveEvent(QMouseEvent*) override;
    void mouseReleaseEvent(QMouseEvent*) override;
    void keyPressEvent(QKeyEvent*) override;

private:
    QScreen* screen_{};
    QPoint start_;
    QPoint current_;
    bool selecting_{false};
    std::function<void(const QRect&)> callback_;
};
