#include "WsClient.h"
#include <QJsonDocument>
#include <QNetworkRequest>
#include <QUrlQuery>

WsClient::WsClient(QObject* parent) : QObject(parent) {
    connect(&socket_, &QWebSocket::connected, this, &WsClient::connected);
    connect(&socket_, &QWebSocket::disconnected, this, &WsClient::disconnected);
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
    QNetworkRequest request(u);
    request.setRawHeader("Authorization", "Bearer " + token.toUtf8());
    socket_.open(request);
}

void WsClient::send(const QJsonObject& obj) {
    if (socket_.state() == QAbstractSocket::ConnectedState)
        socket_.sendTextMessage(QString::fromUtf8(QJsonDocument(obj).toJson(QJsonDocument::Compact)));
}

bool WsClient::isConnected() const {
    return socket_.state() == QAbstractSocket::ConnectedState;
}
