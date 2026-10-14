#pragma once
#include <QObject>
#include <QJsonObject>
#include <QUrl>
#include <QWebSocket>
#include <QTimer>

class WsClient final : public QObject {
    Q_OBJECT
public:
    explicit WsClient(QObject* parent = nullptr);
    void connectTo(const QUrl& baseWs, const QString& lesson, const QString& id,
                   const QString& role, const QString& name, const QString& token);
    void send(const QJsonObject& obj);
    bool isConnected() const;
    void setAutoReconnect(bool enabled);
    void disconnectFromServer();

signals:
    void connected();
    void disconnected();
    void message(const QJsonObject& obj);
    void errorText(const QString& text);

private:
    void openSaved();

    QWebSocket socket_;
    QTimer reconnectTimer_;
    QUrl savedUrl_;
    QString savedToken_;
    bool autoReconnect_{true};
    bool manualClose_{false};
    int reconnectAttempt_{0};
};
