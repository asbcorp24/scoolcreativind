#pragma once

#include <QLabel>
#include <QJsonObject>
#include <QString>

class RemoteView final : public QLabel {
    Q_OBJECT
public:
    explicit RemoteView(QWidget* parent = nullptr);
    void setTarget(const QString& target);
    QString target() const { return target_; }

signals:
    void remoteEvent(const QJsonObject& event);

protected:
    void mouseMoveEvent(QMouseEvent* event) override;
    void mousePressEvent(QMouseEvent* event) override;
    void mouseReleaseEvent(QMouseEvent* event) override;
    void wheelEvent(QWheelEvent* event) override;
    void keyPressEvent(QKeyEvent* event) override;
    void keyReleaseEvent(QKeyEvent* event) override;

private:
    void emitMouse(const QString& type, QMouseEvent* event);
    QString target_;
};
