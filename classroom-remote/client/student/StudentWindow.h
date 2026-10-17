#pragma once

#include <QMainWindow>
#include <QJsonObject>
#include "../common/ClientConfig.h"

class QLabel;
class QLineEdit;
class QPushButton;
class QTimer;
class QNetworkAccessManager;
class QUrl;
class WsClient;
class RtcRelayClient;
class AudioPipe;
class MediaCodec;
class StudentLessonsWidget;
class AnnotationOverlay;
class QPlainTextEdit;
class QListWidget;
class QTabWidget;
class QDialog;
class QUdpSocket;

class StudentWindow final : public QMainWindow {
    Q_OBJECT
public:
    explicit StudentWindow(QWidget* parent = nullptr);
    ~StudentWindow() override;
    void openInviteUrl(const QUrl& url);

private:
    void loginWithPIN();
    void editConnectionSettings();
    void connectServer();
    void onMessage(const QJsonObject& obj);
    void uploadSnapshot();
    void sendRealtimeScreen();
    void setRemoteActive(bool active);
    void onRtcVideo(const QString& trackId, const QByteArray& h264);
    void onRtcAudio(const QString& trackId, const QByteArray& opus);
    void sendStudentStatus();
    void sendChat();
    void loadQuickFiles();
    void downloadQuickFile();
    void discoverLocalTeacher();
    void showScreenshotTask(const QJsonObject& obj);
    void showLanConnectDialog(const QString& text);
    void closeLanConnectDialog();
    void verifyLocalServerAndLogin();
    QUrl quickFileUrl(qint64 fileId = 0) const;

    WsClient* ws_{};
    RtcRelayClient* rtc_{};
    AudioPipe* audio_{};
    MediaCodec* codec_{};

    ClientConfig config_;
    QLineEdit* pin_{};
    QLineEdit* invite_{};
    QLineEdit* wsUrl_{};
    QLineEdit* httpUrl_{};
    QLineEdit* lesson_{};
    QLineEdit* token_{};
    QLineEdit* name_{};
    QLabel* status_{};
    QLabel* remoteBanner_{};
    QLabel* lecture_{};
    AnnotationOverlay* annotationOverlay_{};
    StudentLessonsWidget* lessonsWidget_{};
    QPushButton* hand_{};
    QTabWidget* mainTabs_{};
    QDialog* attentionDialog_{};
    QDialog* lanConnectDialog_{};
    QLabel* lanConnectStatus_{};

    QLabel* pollQuestion_{};
    QListWidget* pollOptions_{};
    QPushButton* pollAnswerButton_{};
    QPlainTextEdit* chatLog_{};
    QLineEdit* chatInput_{};
    QLabel* chatState_{};
    QListWidget* quickFiles_{};

    QString activePollId_;
    bool publicChatEnabled_{true};

    QTimer* snapshotTimer_{};
    QTimer* remoteScreenTimer_{};
    QTimer* statusTimer_{};
    QNetworkAccessManager* network_{};
    QUdpSocket* lanDiscovery_{};

    QString studentId_;
    bool handRaised_{false};
    bool remoteActive_{false};
    bool controlAllowed_{false};
    bool audioTalkActive_{false};
    bool localDiscoveryResolved_{false};
};
