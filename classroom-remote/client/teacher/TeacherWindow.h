#pragma once

#include <QMainWindow>
#include <QHash>
#include <QJsonObject>

class QListWidget;
class QLabel;
class QPushButton;
class QLineEdit;
class QTimer;
class QCamera;
class QMediaCaptureSession;
class QVideoSink;
class QImage;
class WsClient;
class RtcRelayClient;
class AudioPipe;
class RemoteView;

class TeacherWindow final : public QMainWindow {
    Q_OBJECT
public:
    explicit TeacherWindow(QWidget* parent = nullptr);
    ~TeacherWindow() override;

private:
    void connectServer();
    void onMessage(const QJsonObject& obj);
    QString selectedStudentId() const;
    void sendToSelected(const QString& type);

    void startScreenLecture();
    void startCameraLecture();
    void stopLecture();
    void sendLectureImage(const QImage& image);
    void showLocalPreview(const QImage& image);
    void onRtcFrame(quint8 kind, const QString& source, const QByteArray& payload);

    WsClient* ws_{};
    RtcRelayClient* rtc_{};
    AudioPipe* audio_{};

    QLineEdit* server_{};
    QLineEdit* lesson_{};
    QLineEdit* teacherName_{};
    QLabel* status_{};
    QLabel* lecturePreview_{};
    RemoteView* remoteView_{};
    QListWidget* students_{};
    QPushButton* micButton_{};

    QTimer* screenTimer_{};
    QCamera* camera_{};
    QMediaCaptureSession* captureSession_{};
    QVideoSink* videoSink_{};

    QHash<QString, QString> names_;
    QString activeStudent_;
    bool micOn_{false};
};
