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
class Track;
}

class WsClient;

class RtcRelayClient final : public QObject {
    Q_OBJECT
public:
    static inline const QString LectureVideoTrack = "lecture-video";
    static inline const QString LectureAudioTrack = "lecture-audio";
    static inline const QString PrivateAudioTrack = "private-audio";
    static inline const QString RemoteVideoTrack = "remote-video";
    static inline const QString RemoteAudioTrack = "remote-audio";

    explicit RtcRelayClient(WsClient* signaling, QObject* parent = nullptr);
    ~RtcRelayClient() override;

    void start(const QString& role, const QStringList& iceServers = {});
    void stop();
    bool isOpen() const;

    void sendVideo(const QString& trackId, const QByteArray& h264, int frameDurationMs = 100);
    void sendAudio(const QString& trackId, const QByteArray& opus, int frameDurationMs = 20);
    void sendText(const QJsonObject& object);

signals:
    void opened();
    void closed();
    void stateText(const QString& state);
    void videoFrameReceived(const QString& trackId, const QByteArray& h264);
    void audioFrameReceived(const QString& trackId, const QByteArray& opus);
    void textReceived(const QJsonObject& object);

private:
    void handleSignal(const QJsonObject& object);
    void installDataChannelCallbacks(const std::shared_ptr<rtc::DataChannel>& dc);
    void setupMediaTracks(const QString& role);
    void addVideoTrack(const QString& mid, bool sending, int payloadType, uint32_t ssrc);
    void addAudioTrack(const QString& mid, bool sending, int payloadType, uint32_t ssrc);

    WsClient* signaling_{};
    std::shared_ptr<rtc::PeerConnection> pc_;
    std::shared_ptr<rtc::DataChannel> dc_;
    QHash<QString, std::shared_ptr<rtc::Track>> mediaTracks_;
    QHash<QString, qint64> mediaPtsMs_;
    bool offerSent_{false};
};
