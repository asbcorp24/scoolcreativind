#pragma once

#include <QObject>
#include <QByteArray>
#include <QHash>
#include <QJsonObject>
#include <QStringList>
#include <memory>

namespace rtc {
class PeerConnection;
class DataChannel;
}

class WsClient;

class RtcRelayClient final : public QObject {
    Q_OBJECT
public:
    enum FrameKind : quint8 {
        LectureVideo = 1,
        LectureAudio = 2,
        StudentVideo = 3,
        StudentAudio = 4
    };

    explicit RtcRelayClient(WsClient* signaling, QObject* parent = nullptr);
    ~RtcRelayClient() override;

    void start(const QStringList& iceServers = {});
    void stop();
    bool isOpen() const;

    void sendFrame(FrameKind kind, const QByteArray& payload);
    void sendText(const QJsonObject& object);

signals:
    void opened();
    void closed();
    void stateText(const QString& state);
    void frameReceived(quint8 kind, const QString& sourceId, const QByteArray& payload);
    void textReceived(const QJsonObject& object);

private:
    struct PendingFrame {
        quint16 chunkCount{};
        quint16 received{};
        QVector<QByteArray> chunks;
    };

    void handleSignal(const QJsonObject& object);
    void installDataChannelCallbacks(const std::shared_ptr<rtc::DataChannel>& dc);
    void handleBinary(const rtc::binary& data);

    WsClient* signaling_{};
    std::shared_ptr<rtc::PeerConnection> pc_;
    std::shared_ptr<rtc::DataChannel> dc_;
    bool offerSent_{false};
    quint32 nextFrameId_{1};
    QHash<QString, PendingFrame> pendingFrames_;
};
