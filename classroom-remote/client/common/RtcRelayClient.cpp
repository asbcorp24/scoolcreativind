#include "RtcRelayClient.h"
#include "WsClient.h"

#include <QJsonDocument>
#include <QMetaObject>
#include <cstring>
#include <rtc/rtc.hpp>

namespace {
constexpr qsizetype kChunkPayload = 48 * 1024;

void put16(rtc::binary& data, size_t offset, quint16 value) {
    data[offset] = static_cast<std::byte>((value >> 8) & 0xff);
    data[offset + 1] = static_cast<std::byte>(value & 0xff);
}

void put32(rtc::binary& data, size_t offset, quint32 value) {
    data[offset] = static_cast<std::byte>((value >> 24) & 0xff);
    data[offset + 1] = static_cast<std::byte>((value >> 16) & 0xff);
    data[offset + 2] = static_cast<std::byte>((value >> 8) & 0xff);
    data[offset + 3] = static_cast<std::byte>(value & 0xff);
}

quint16 get16(const unsigned char* p) {
    return static_cast<quint16>((quint16(p[0]) << 8) | quint16(p[1]));
}

quint32 get32(const unsigned char* p) {
    return (quint32(p[0]) << 24) | (quint32(p[1]) << 16) |
           (quint32(p[2]) << 8) | quint32(p[3]);
}
}

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
    pendingFrames_.clear();
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
    if (!isOpen() || payload.isEmpty())
        return;

    const bool isVideo = kind == LectureVideo || kind == StudentVideo;
    if (isVideo && dc_->bufferedAmount() > 2 * 1024 * 1024)
        return;

    const quint32 frameId = nextFrameId_++;
    const quint16 count = static_cast<quint16>(
        qMax<qsizetype>(1, (payload.size() + kChunkPayload - 1) / kChunkPayload));

    for (quint16 index = 0; index < count; ++index) {
        const qsizetype offset = qsizetype(index) * kChunkPayload;
        const qsizetype size = qMin(kChunkPayload, payload.size() - offset);

        // client -> server:
        // kind(1), frameId(4), chunkIndex(2), chunkCount(2), payload
        rtc::binary data(static_cast<size_t>(9 + size));
        data[0] = static_cast<std::byte>(kind);
        put32(data, 1, frameId);
        put16(data, 5, index);
        put16(data, 7, count);
        std::memcpy(data.data() + 9, payload.constData() + offset, static_cast<size_t>(size));

        try {
            if (!dc_->send(std::move(data)))
                emit stateText("WebRTC: очередь DataChannel заполнена");
        } catch (...) {
            emit stateText("WebRTC: ошибка отправки кадра");
            return;
        }
    }
}

void RtcRelayClient::sendTargetedFrame(FrameKind kind, const QString& target, const QByteArray& payload) {
    if (!isOpen() || target.isEmpty() || payload.isEmpty())
        return;

    if (dc_->bufferedAmount() > 2 * 1024 * 1024 && kind != TeacherPrivateAudio)
        return;

    const QByteArray targetUtf8 = target.toUtf8();
    if (targetUtf8.size() > 65535)
        return;

    const quint32 frameId = nextFrameId_++;
    const quint16 count = static_cast<quint16>(
        qMax<qsizetype>(1, (payload.size() + kChunkPayload - 1) / kChunkPayload));

    for (quint16 index = 0; index < count; ++index) {
        const qsizetype offset = qsizetype(index) * kChunkPayload;
        const qsizetype size = qMin(kChunkPayload, payload.size() - offset);

        // targeted client -> server:
        // kind(1), targetLen(2), target, frameId(4), chunkIndex(2), chunkCount(2), payload
        rtc::binary data(static_cast<size_t>(11 + targetUtf8.size() + size));
        data[0] = static_cast<std::byte>(kind);
        put16(data, 1, static_cast<quint16>(targetUtf8.size()));
        std::memcpy(data.data() + 3, targetUtf8.constData(), static_cast<size_t>(targetUtf8.size()));

        const size_t meta = static_cast<size_t>(3 + targetUtf8.size());
        put32(data, meta, frameId);
        put16(data, meta + 4, index);
        put16(data, meta + 6, count);
        std::memcpy(data.data() + meta + 8, payload.constData() + offset, static_cast<size_t>(size));

        try {
            dc_->send(std::move(data));
        } catch (...) {
            emit stateText("WebRTC: ошибка приватного аудио");
            return;
        }
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
            const auto doc = QJsonDocument::fromJson(
                QByteArray(s.data(), static_cast<qsizetype>(s.size())));
            if (!doc.isObject())
                return;
            const auto obj = doc.object();
            QMetaObject::invokeMethod(this, [this, obj] {
                emit textReceived(obj);
            }, Qt::QueuedConnection);
            return;
        }

        const auto& binary = std::get<rtc::binary>(data);
        QByteArray bytes(reinterpret_cast<const char*>(binary.data()),
                         static_cast<qsizetype>(binary.size()));
        QMetaObject::invokeMethod(this, [this, bytes] {
            handleBinary(bytes);
        }, Qt::QueuedConnection);
    });
}

void RtcRelayClient::handleBinary(const QByteArray& b) {
    // server -> client:
    // kind(1), sourceLen(2), source, frameId(4), chunkIndex(2), chunkCount(2), payload
    if (b.size() < 11)
        return;

    const auto* raw = reinterpret_cast<const unsigned char*>(b.constData());
    const quint8 kind = raw[0];
    const quint16 idLen = get16(raw + 1);
    const size_t metaOffset = 3 + idLen;
    if (b.size() < metaOffset + 8)
        return;

    const QString source = QString::fromUtf8(
        reinterpret_cast<const char*>(raw + 3),
        static_cast<qsizetype>(idLen));
    const quint32 frameId = get32(raw + metaOffset);
    const quint16 chunkIndex = get16(raw + metaOffset + 4);
    const quint16 chunkCount = get16(raw + metaOffset + 6);
    if (chunkCount == 0 || chunkIndex >= chunkCount || chunkCount > 4096)
        return;

    const size_t payloadOffset = metaOffset + 8;
    QByteArray chunk(
        reinterpret_cast<const char*>(raw + payloadOffset),
        static_cast<qsizetype>(b.size() - payloadOffset));

    const QString key = QString("%1:%2:%3").arg(source).arg(kind).arg(frameId);
    if (!pendingFrames_.contains(key) && pendingFrames_.size() >= 128)
        pendingFrames_.clear();
    auto& pending = pendingFrames_[key];
    if (pending.chunks.isEmpty()) {
        pending.chunkCount = chunkCount;
        pending.chunks.resize(chunkCount);
    }
    if (pending.chunkCount != chunkCount)
        return;

    if (pending.chunks[chunkIndex].isEmpty()) {
        pending.chunks[chunkIndex] = std::move(chunk);
        ++pending.received;
    }
    if (pending.received != pending.chunkCount)
        return;

    QByteArray payload;
    qsizetype total = 0;
    for (const auto& part : pending.chunks)
        total += part.size();
    payload.reserve(total);
    for (const auto& part : pending.chunks)
        payload.append(part);

    pendingFrames_.remove(key);
    QMetaObject::invokeMethod(this, [this, kind, source, payload] {
        emit frameReceived(kind, source, payload);
    }, Qt::QueuedConnection);
}
