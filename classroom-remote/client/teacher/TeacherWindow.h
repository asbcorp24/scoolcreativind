#pragma once

#include <QMainWindow>
#include <QHash>
#include <QByteArray>
#include <QJsonObject>
#include "../common/ClientConfig.h"

class QListWidget;
class QLabel;
class QPushButton;
class QLineEdit;
class QTimer;
class QCamera;
class QMediaCaptureSession;
class QVideoSink;
class QImage;
class QMediaRecorder;
class QScreenCapture;
class QAudioInput;
class QMediaPlayer;
class QAudioBufferOutput;
class QNetworkAccessManager;
class QUrl;
class WsClient;
class RtcRelayClient;
class AudioPipe;
class MediaCodec;
class RemoteView;
class TeacherLessonsWidget;
class AnnotationOverlay;
class QPlainTextEdit;
class QButtonGroup;
class QRadioButton;
class QComboBox;
class QSlider;
class QProgressBar;
class QSpinBox;
class QWindowCapture;
class QCheckBox;
class SystemAudioCapture;
class QDialog;
class QProcess;
class QUdpSocket;
class QSystemTrayIcon;
class QEvent;

class TeacherWindow final : public QMainWindow {
    Q_OBJECT
public:
    explicit TeacherWindow(QWidget* parent = nullptr);
    ~TeacherWindow() override;
    void openInviteUrl(const QUrl& url);

protected:
    void changeEvent(QEvent* event) override;

private:
    void loginWithPIN();
    void editConnectionSettings();
    void connectServer();
    void onMessage(const QJsonObject& obj);
    QString selectedStudentId() const;
    void sendToSelected(const QString& type);

    void startScreenLecture();
    void startCameraLecture();
    void startFileLecture();
    void stopLecture();

    void startCameraRecording();
    void startScreenRecording();
    void stopRecording();
    void startRecording(bool screen);
    void cleanupRecording();
    void sendLectureImage(const QImage& image);
    void showLocalPreview(const QImage& image);
    void onRtcVideo(const QString& trackId, const QByteArray& h264);
    void onRtcAudio(const QString& trackId, const QByteArray& opus);
    void downloadRecording();
    void downloadFramesZip();
    void startPoll();
    void uploadQuickFile();
    void showAttendance();
    void sendChat(bool privateMessage);
    QUrl httpBaseUrl() const;
    void updatePollStatus();
    void showPollResults();
    void launchQuickPoll(const QString& question, const QStringList& options);
    void refreshCaptureSources();
    void applySelectedQuality();
    void openTVWindow();
    void sendScreenshotTask();
    void uploadScreenshotTask(const QByteArray& imageData, const QString& fileName,
                              const QString& contentType);
    void startLocalServer();
    void stopLocalServer();
    void checkLocalServerReady(int attempt = 1);
    void advertiseLocalServer();
    int findAvailableLocalPort() const;
    QString localIPv4Address() const;
    QString localServerExecutable() const;
    void setBroadcastState(bool active, const QString& mode = {});
    void restoreFromTray();

    WsClient* ws_{};
    RtcRelayClient* rtc_{};
    AudioPipe* audio_{};
    SystemAudioCapture* systemAudio_{};
    MediaCodec* codec_{};

    ClientConfig config_;
    QLineEdit* pin_{};
    QLineEdit* invite_{};
    QLineEdit* server_{};
    QLineEdit* lesson_{};
    QLineEdit* token_{};
    QLineEdit* teacherName_{};
    QLabel* status_{};
    QLabel* broadcastStatus_{};
    QLabel* lecturePreview_{};
    QDialog* tvWindow_{};
    QLabel* tvPreview_{};
    RemoteView* remoteView_{};
    TeacherLessonsWidget* lessonsWidget_{};
    AnnotationOverlay* annotationOverlay_{};
    QListWidget* students_{};
    QButtonGroup* studentButtons_{};
    QLabel* selectedStudentLabel_{};
    QPushButton* micButton_{};
    QPushButton* broadcastToggleButton_{};
    QPushButton* muteButton_{};
    QComboBox* cameraCombo_{};
    QComboBox* micCombo_{};
    QComboBox* sourceCombo_{};
    QComboBox* qualityCombo_{};
    QSlider* micVolume_{};
    QProgressBar* micLevel_{};
    QCheckBox* systemAudioCheck_{};
    QLabel* lessonTimerLabel_{};
    QLabel* taskTimerLabel_{};
    QSpinBox* taskMinutes_{};

    QPlainTextEdit* chatLog_{};
    QLineEdit* chatInput_{};
    QPushButton* chatToggle_{};
    QLabel* pollStatus_{};
    bool publicChatEnabled_{true};
    QString activePollId_;
    QString activePollQuestion_;
    QHash<int, int> pollCounts_;
    QHash<QString, int> pollStudentAnswers_;
    QHash<QString, QString> pollStudentNames_;
    int pollResponses_{0};

    QTimer* screenTimer_{};
    QTimer* lessonTimer_{};
    QTimer* taskTimer_{};
    QNetworkAccessManager* network_{};
    QProcess* localServerProcess_{};
    QUdpSocket* lanDiscovery_{};
    QSystemTrayIcon* trayIcon_{};
    QCamera* camera_{};
    QMediaCaptureSession* captureSession_{};
    QVideoSink* videoSink_{};
    QWindowCapture* windowCapture_{};
    QMediaCaptureSession* windowCaptureSession_{};
    QVideoSink* windowCaptureSink_{};

    QMediaCaptureSession* recordingSession_{};
    QMediaRecorder* mediaRecorder_{};
    QCamera* recordingCamera_{};
    QScreenCapture* recordingScreen_{};
    QAudioInput* recordingAudio_{};
    QVideoSink* recordingPreviewSink_{};
    QString recordingPath_;

    QMediaPlayer* filePlayer_{};
    QVideoSink* fileVideoSink_{};
    QAudioBufferOutput* fileAudioBuffer_{};

    QHash<QString, QString> names_;
    QString selectedStudentId_;
    QString activeStudent_;
    QString privateAudioTarget_;
    bool micOn_{false};
    bool micMuted_{false};
    int lessonElapsedSeconds_{0};
    int taskRemainingSeconds_{0};
    QByteArray systemAudioBuffer_;
    qint64 lastCameraSendMs_{0};
    qint64 lastWindowSendMs_{0};
    qint64 lastFileVideoSendMs_{0};
    int selectedFrameIntervalMs_{100};
    bool broadcastActive_{false};
    QString broadcastMode_;
    QString selectedBroadcastMode_{"screen"};
    QString localStudentPin_;
    int localServerPort_{0};
    QString localTeacherPin_;
};
