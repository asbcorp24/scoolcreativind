#include "WsClient.h"
#include <QJsonDocument>
#include <QNetworkRequest>
#include <QUrlQuery>

WsClient::WsClient(QObject* parent) : QObject(parent) {
    reconnectTimer_.setSingleShot(true);
    connect(&reconnectTimer_, &QTimer::timeout, this, &WsClient::openSaved);

    connect(&socket_, &QWebSocket::connected, this, [this] {
        reconnectAttempt_ = 0;
        emit connected();
    });
    connect(&socket_, &QWebSocket::disconnected, this, [this] {
        emit disconnected();
        if (autoReconnect_ && !manualClose_ && savedUrl_.isValid()) {
            const int delay = qMin(10000, 1000 * (1 << qMin(reconnectAttempt_, 3)));
            ++reconnectAttempt_;
            reconnectTimer_.start(delay);
        }
    });
    connect(&socket_, &QWebSocket::textMessageReceived, this, [this](const QString& text) {
        const auto doc = QJsonDocument::fromJson(text.toUtf8());
        if (doc.isObject()) emit message(doc.object());
    });
    connect(&socket_, &QWebSocket::errorOccurred, this, [this](QAbstractSocket::SocketError) {
        emit errorText(socket_.errorString());
    });
}

void WsClient::connectTo(const QUrl& baseWs, const QString& lesson, const QString& id,
                         const QString& role, const QString& name, const QString& token) {
    QUrl u = baseWs;
    QUrlQuery q;
    q.addQueryItem("lesson", lesson);
    q.addQueryItem("id", id);
    q.addQueryItem("role", role);
    q.addQueryItem("name", name);
    u.setQuery(q);

    savedUrl_ = u;
    savedToken_ = token;
    manualClose_ = false;
    reconnectAttempt_ = 0;
    reconnectTimer_.stop();
    openSaved();
}

void WsClient::openSaved() {
    if (!savedUrl_.isValid() || socket_.state() == QAbstractSocket::ConnectedState ||
        socket_.state() == QAbstractSocket::ConnectingState)
        return;
    manualClose_ = false;
    QNetworkRequest request(savedUrl_);
    request.setRawHeader("Authorization", "Bearer " + savedToken_.toUtf8());
    socket_.open(request);
}

void WsClient::setAutoReconnect(bool enabled) {
    autoReconnect_ = enabled;
    if (!enabled)
        reconnectTimer_.stop();
}

void WsClient::disconnectFromServer() {
    manualClose_ = true;
    reconnectTimer_.stop();
    socket_.close();
}

void WsClient::send(const QJsonObject& obj) {
    if (socket_.state() == QAbstractSocket::ConnectedState)
        socket_.sendTextMessage(QString::fromUtf8(QJsonDocument(obj).toJson(QJsonDocument::Compact)));
}

bool WsClient::isConnected() const {
    return socket_.state() == QAbstractSocket::ConnectedState;
}
