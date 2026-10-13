#include "RtcRelayClient.h"
#include "WsClient.h"

#include <QJsonDocument>
#include <QMetaObject>

#include <chrono>
#include <cstring>
#include <rtc/rtc.hpp>

namespace {

rtc::binary toBinary(const QByteArray& bytes) {
    rtc::binary out(static_cast<size_t>(bytes.size()));
    if (!bytes.isEmpty())
        std::memcpy(out.data(), bytes.constData(), static_cast<size_t>(bytes.size()));
    return out;
}

QByteArray toByteArray(const rtc::binary& bytes) {
    return QByteArray(reinterpret_cast<const char*>(bytes.data()),
                      static_cast<qsizetype>(bytes.size()));
}

} // namespace

RtcRelayClient::RtcRelayClient(WsClient* signaling, QObject* parent)
    : QObject(parent), signaling_(signaling) {
    connect(signaling_, &WsClient::message, this, &RtcRelayClient::handleSignal);
}

RtcRelayClient::~RtcRelayClient() {
    stop();
}

void RtcRelayClient::start(const QString& role, const QStringList& iceServers) {
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
        QMetaObject::invokeMethod(this, [this, state, text] {
            emit stateText("WebRTC state=" + text);
            if (state == rtc::PeerConnection::State::Connected)
                emit opened();
            else if (state == rtc::PeerConnection::State::Closed ||
                     state == rtc::PeerConnection::State::Disconnected ||
                     state == rtc::PeerConnection::State::Failed)
                emit closed();
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

    setupMediaTracks(role);

    dc_ = pc_->createDataChannel("classroom");
    installDataChannelCallbacks(dc_);

    pc_->setLocalDescription();
}

void RtcRelayClient::stop() {
    mediaPtsMs_.clear();
    mediaTracks_.clear();

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
    return pc_ && pc_->state() == rtc::PeerConnection::State::Connected;
}

void RtcRelayClient::setupMediaTracks(const QString& role) {
    const bool teacher = role == "teacher";

    // Keep the same m-line order for every peer.
    addVideoTrack(LectureVideoTrack, teacher, 102, 0x11010001);
    addAudioTrack(LectureAudioTrack, teacher, 111, 0x11010002);
    addAudioTrack(PrivateAudioTrack, teacher, 111, 0x11010003);
    addVideoTrack(RemoteVideoTrack, !teacher, 102, 0x12010001);
    addAudioTrack(RemoteAudioTrack, !teacher, 111, 0x12010002);
}

void RtcRelayClient::addVideoTrack(const QString& mid, bool sending,
                                   int payloadType, uint32_t ssrc) {
    if (!pc_)
        return;

    auto desc = rtc::Description::Video(
        mid.toStdString(),
        sending ? rtc::Description::Direction::SendOnly
                : rtc::Description::Direction::RecvOnly);
    desc.addH264Codec(payloadType);

    if (sending)
        desc.addSSRC(ssrc, mid.toStdString(), "classroom", mid.toStdString());

    auto track = pc_->addTrack(desc);
    mediaTracks_.insert(mid, track);

    if (sending) {
        auto config = std::make_shared<rtc::RtpPacketizationConfig>(
            ssrc, mid.toStdString(), static_cast<uint8_t>(payloadType),
            rtc::H264RtpPacketizer::ClockRate);

        auto packetizer = std::make_shared<rtc::H264RtpPacketizer>(
            rtc::NalUnit::Separator::StartSequence, config);
        packetizer->addToChain(std::make_shared<rtc::RtcpSrReporter>(config));
        packetizer->addToChain(std::make_shared<rtc::RtcpNackResponder>());
        track->setMediaHandler(packetizer);
    } else {
        track->setMediaHandler(std::make_shared<rtc::H264RtpDepacketizer>(
            rtc::NalUnit::Separator::StartSequence));

        track->onFrame([this, mid](rtc::binary data, rtc::FrameInfo) {
            const QByteArray frame = toByteArray(data);
            QMetaObject::invokeMethod(this, [this, mid, frame] {
                emit videoFrameReceived(mid, frame);
            }, Qt::QueuedConnection);
        });
    }
}

void RtcRelayClient::addAudioTrack(const QString& mid, bool sending,
                                   int payloadType, uint32_t ssrc) {
    if (!pc_)
        return;

    auto desc = rtc::Description::Audio(
        mid.toStdString(),
        sending ? rtc::Description::Direction::SendOnly
                : rtc::Description::Direction::RecvOnly);
    desc.addOpusCodec(payloadType);

    if (sending)
        desc.addSSRC(ssrc, mid.toStdString(), "classroom", mid.toStdString());

    auto track = pc_->addTrack(desc);
    mediaTracks_.insert(mid, track);

    if (sending) {
        auto config = std::make_shared<rtc::RtpPacketizationConfig>(
            ssrc, mid.toStdString(), static_cast<uint8_t>(payloadType),
            rtc::OpusRtpPacketizer::DefaultClockRate);

        auto packetizer = std::make_shared<rtc::OpusRtpPacketizer>(config);
        packetizer->addToChain(std::make_shared<rtc::RtcpSrReporter>(config));
        packetizer->addToChain(std::make_shared<rtc::RtcpNackResponder>());
        track->setMediaHandler(packetizer);
    } else {
        track->setMediaHandler(std::make_shared<rtc::OpusRtpDepacketizer>());

        track->onFrame([this, mid](rtc::binary data, rtc::FrameInfo) {
            const QByteArray frame = toByteArray(data);
            QMetaObject::invokeMethod(this, [this, mid, frame] {
                emit audioFrameReceived(mid, frame);
            }, Qt::QueuedConnection);
        });
    }
}

void RtcRelayClient::sendVideo(const QString& trackId, const QByteArray& h264,
                               int frameDurationMs) {
    auto it = mediaTracks_.find(trackId);
    if (it == mediaTracks_.end() || !it.value() || !it.value()->isOpen() || h264.isEmpty())
        return;

    qint64& pts = mediaPtsMs_[trackId];
    try {
        it.value()->sendFrame(
            toBinary(h264),
            std::chrono::duration<double>(double(pts) / 1000.0));
        pts += qMax(1, frameDurationMs);
    } catch (const std::exception& e) {
        emit stateText(QString("RTP video send error: %1").arg(e.what()));
    }
}

void RtcRelayClient::sendAudio(const QString& trackId, const QByteArray& opus,
                               int frameDurationMs) {
    auto it = mediaTracks_.find(trackId);
    if (it == mediaTracks_.end() || !it.value() || !it.value()->isOpen() || opus.isEmpty())
        return;

    qint64& pts = mediaPtsMs_[trackId];
    try {
        it.value()->sendFrame(
            toBinary(opus),
            std::chrono::duration<double>(double(pts) / 1000.0));
        pts += qMax(1, frameDurationMs);
    } catch (const std::exception& e) {
        emit stateText(QString("RTP audio send error: %1").arg(e.what()));
    }
}

void RtcRelayClient::sendText(const QJsonObject& object) {
    if (!dc_ || !dc_->isOpen())
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
            emit stateText("WebRTC DataChannel управления открыт");
        }, Qt::QueuedConnection);
    });

    dc->onMessage([this](std::variant<rtc::binary, std::string> data) {
        if (!std::holds_alternative<std::string>(data))
            return;

        const auto& text = std::get<std::string>(data);
        const auto doc = QJsonDocument::fromJson(
            QByteArray(text.data(), static_cast<qsizetype>(text.size())));
        if (!doc.isObject())
            return;

        const auto obj = doc.object();
        QMetaObject::invokeMethod(this, [this, obj] {
            emit textReceived(obj);
        }, Qt::QueuedConnection);
    });
}
