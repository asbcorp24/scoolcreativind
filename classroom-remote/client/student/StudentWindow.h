#pragma once

#include <QMainWindow>
#include <QJsonObject>

class QLabel;
class QLineEdit;
class QPushButton;
class QTimer;
class QNetworkAccessManager;
class WsClient;
class RtcRelayClient;
class AudioPipe;
class MediaCodec;

class StudentWindow final : public QMainWindow {
    Q_OBJECT
public:
    explicit StudentWindow(QWidget* parent = nullptr);
    ~StudentWindow() override;

private:
    void connectServer();
    void onMessage(const QJsonObject& obj);
    void uploadSnapshot();
    void sendRealtimeScreen();
    void setRemoteActive(bool active);
    void onRtcVideo(const QString& trackId, const QByteArray& h264);
    void onRtcAudio(const QString& trackId, const QByteArray& opus);

    WsClient* ws_{};
    RtcRelayClient* rtc_{};
    AudioPipe* audio_{};
    MediaCodec* codec_{};

    QLineEdit* wsUrl_{};
    QLineEdit* httpUrl_{};
    QLineEdit* lesson_{};
    QLineEdit* token_{};
    QLineEdit* name_{};
    QLabel* status_{};
    QLabel* remoteBanner_{};
    QLabel* lecture_{};
    QPushButton* hand_{};

    QTimer* snapshotTimer_{};
    QTimer* remoteScreenTimer_{};
    QNetworkAccessManager* network_{};

    QString studentId_;
    bool handRaised_{false};
    bool remoteActive_{false};
    bool controlAllowed_{false};
    bool audioTalkActive_{false};
};
