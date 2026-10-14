#pragma once

#include <QWidget>
#include <QJsonObject>
#include <QVector>

class AnnotationOverlay final : public QWidget {
    Q_OBJECT
public:
    enum class Tool { Off, Marker, Arrow, Rectangle, Text, Laser };

    explicit AnnotationOverlay(QWidget* parent = nullptr);

    void setEditable(bool editable);
    void setTool(Tool tool);
    void applyRemote(const QJsonObject& obj);
    void clearAll();

signals:
    void annotationCreated(const QJsonObject& obj);
    void clearRequested();

protected:
    void paintEvent(QPaintEvent*) override;
    void mousePressEvent(QMouseEvent* e) override;
    void mouseMoveEvent(QMouseEvent* e) override;
    void mouseReleaseEvent(QMouseEvent* e) override;

private:
    QPointF normalized(const QPointF& p) const;
    QPointF denormalized(double x, double y) const;
    void emitShape(const QString& kind, const QPointF& a, const QPointF& b,
                   const QString& text = {});
    void addShape(const QJsonObject& obj);

    bool editable_{false};
    Tool tool_{Tool::Off};
    bool dragging_{false};
    QPointF start_;
    QPointF last_;
    QVector<QJsonObject> shapes_;
};
