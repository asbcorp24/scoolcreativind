#include "RtcRelayClient.h"
#include "WsClient.h"

#include <QJsonDocument>
#include <QMetaObject>
#include <cstring>
#include <rtc/rtc.hpp>

RtcRelayClient::RtcRelayClient(WsClient* signaling, QObject* parent)
    : QObject(parent), signaling_(signaling) {
    connect(signaling_, &WsClient::message, this, &RtcRelayClient::handleSignal);
}

RtcRelayClient::~RtcRelayClient() {
    stop();
}

void RtcRelayClient::start(const QStringList& iceServers) {
    stop();

    rtc::Configuration config;
    const QStringList servers = iceServers.isEmpty()
        ? QStringList{"stun:stun.l.google.com:19302"}
        : iceServers;
    for (const auto& server : servers)
        config.iceServers.emplace_back(server.toStdString());

    pc_ = std::make_shared<rtc::PeerConnection>(config);
    offerSent_ = false;

    pc_->onStateChange([this](rtc::PeerConnection::State state) {
        const auto text = QString::fromStdString(std::to_string(static_cast<int>(state)));
        QMetaObject::invokeMethod(this, [this, text] {
            emit stateText("WebRTC state=" + text);
        }, Qt::QueuedConnection);
    });

    pc_->onGatheringStateChange([this](rtc::PeerConnection::GatheringState state) {
        if (state != rtc::PeerConnection::GatheringState::Complete || offerSent_ || !pc_)
            return;

        const auto local = pc_->localDescription();
        if (!local)
            return;

        offerSent_ = true;
        const QString sdp = QString::fromStdString(std::string(*local));
        QMetaObject::invokeMethod(signaling_, [this, sdp] {
            signaling_->send(QJsonObject{
                {"type", "rtc_offer"},
                {"sdp", sdp}
            });
        }, Qt::QueuedConnection);
    });

    dc_ = pc_->createDataChannel("classroom");
    installDataChannelCallbacks(dc_);
    pc_->setLocalDescription();
}

void RtcRelayClient::stop() {
    if (dc_) {
        try { dc_->close(); } catch (...) {}
        dc_.reset();
    }
    if (pc_) {
        try { pc_->close(); } catch (...) {}
        pc_.reset();
    }
    offerSent_ = false;
}

bool RtcRelayClient::isOpen() const {
    return dc_ && dc_->isOpen();
}

void RtcRelayClient::sendFrame(FrameKind kind, const QByteArray& payload) {
    if (!isOpen())
        return;

    rtc::binary data;
    data.resize(static_cast<size_t>(payload.size() + 1));
    data[0] = static_cast<std::byte>(kind);
    if (!payload.isEmpty())
        std::memcpy(data.data() + 1, payload.constData(), static_cast<size_t>(payload.size()));

    try {
        dc_->send(data);
    } catch (...) {
    }
}

void RtcRelayClient::sendText(const QJsonObject& object) {
    if (!isOpen())
        return;
    const auto json = QJsonDocument(object).toJson(QJsonDocument::Compact).toStdString();
    try {
        dc_->send(json);
    } catch (...) {
    }
}

void RtcRelayClient::handleSignal(const QJsonObject& object) {
    const auto type = object.value("type").toString();
    if (type == "rtc_error") {
        emit stateText("WebRTC error: " + object.value("message").toString());
        return;
    }
    if (type != "rtc_answer" || !pc_)
        return;

    const auto sdp = object.value("sdp").toString();
    if (sdp.isEmpty())
        return;

    try {
        pc_->setRemoteDescription(rtc::Description(sdp.toStdString(), "answer"));
    } catch (const std::exception& e) {
        emit stateText(QString("WebRTC answer error: %1").arg(e.what()));
    }
}

void RtcRelayClient::installDataChannelCallbacks(const std::shared_ptr<rtc::DataChannel>& dc) {
    dc->onOpen([this] {
        QMetaObject::invokeMethod(this, [this] {
            emit opened();
            emit stateText("WebRTC DataChannel открыт");
        }, Qt::QueuedConnection);
    });

    dc->onClosed([this] {
        QMetaObject::invokeMethod(this, [this] {
            emit closed();
        }, Qt::QueuedConnection);
    });

    dc->onMessage([this](std::variant<rtc::binary, std::string> data) {
        if (std::holds_alternative<std::string>(data)) {
            const auto& s = std::get<std::string>(data);
            const auto doc = QJsonDocument::fromJson(QByteArray(s.data(), static_cast<qsizetype>(s.size())));
            if (!doc.isObject())
                return;
            const auto obj = doc.object();
            QMetaObject::invokeMethod(this, [this, obj] {
                emit textReceived(obj);
            }, Qt::QueuedConnection);
            return;
        }

        const auto& b = std::get<rtc::binary>(data);
        if (b.size() < 3)
            return;

        const auto* raw = reinterpret_cast<const unsigned char*>(b.data());
        const quint8 kind = raw[0];
        const quint16 idLen = static_cast<quint16>((raw[1] << 8) | raw[2]);
        if (b.size() < static_cast<size_t>(3 + idLen))
            return;

        const QString source = QString::fromUtf8(
            reinterpret_cast<const char*>(raw + 3),
            static_cast<qsizetype>(idLen));

        const auto payloadOffset = static_cast<size_t>(3 + idLen);
        QByteArray payload(
            reinterpret_cast<const char*>(raw + payloadOffset),
            static_cast<qsizetype>(b.size() - payloadOffset));

        QMetaObject::invokeMethod(this, [this, kind, source, payload] {
            emit frameReceived(kind, source, payload);
        }, Qt::QueuedConnection);
    });
}
