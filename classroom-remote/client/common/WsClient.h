#pragma once
#include <QObject>
#include <QJsonObject>
#include <QUrl>
#include <QWebSocket>

class WsClient final : public QObject {
    Q_OBJECT
public:
    explicit WsClient(QObject* parent = nullptr);
    void connectTo(const QUrl& baseWs, const QString& lesson, const QString& id,
                   const QString& role, const QString& name);
    void send(const QJsonObject& obj);
    bool isConnected() const;

signals:
    void connected();
    void disconnected();
    void message(const QJsonObject& obj);
    void errorText(const QString& text);

private:
    QWebSocket socket_;
};
