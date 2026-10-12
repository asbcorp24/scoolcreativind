#include "TeacherWindow.h"
#include "RemoteView.h"
#include "../common/WsClient.h"
#include "../common/RtcRelayClient.h"
#include "../common/AudioPipe.h"

#include <QBuffer>
#include <QCamera>
#include <QDateTime>
#include <QGuiApplication>
#include <QHBoxLayout>
#include <QImage>
#include <QJsonArray>
#include <QJsonObject>
#include <QLabel>
#include <QLineEdit>
#include <QListWidget>
#include <QListWidgetItem>
#include <QMediaCaptureSession>
#include <QMediaDevices>
#include <QFile>
#include <QFileDialog>
#include <QNetworkAccessManager>
#include <QNetworkReply>
#include <QNetworkRequest>
#include <QUrl>
#include <QMessageBox>
#include <QPixmap>
#include <QPushButton>
#include <QScreen>
#include <QSplitter>
#include <QTabWidget>
#include <QTimer>
#include <QUuid>
#include <QVBoxLayout>
#include <QVideoFrame>
#include <QVideoSink>
#include <QWidget>

TeacherWindow::TeacherWindow(QWidget* parent)
    : QMainWindow(parent),
      ws_(new WsClient(this)),
      rtc_(new RtcRelayClient(ws_, this)),
      audio_(new AudioPipe(this)),
      screenTimer_(new QTimer(this)),
      network_(new QNetworkAccessManager(this)) {

    auto* root = new QWidget;
    auto* outer = new QVBoxLayout(root);

    auto* form = new QHBoxLayout;
    server_ = new QLineEdit("wss://classroom.example.ru/ws");
    lesson_ = new QLineEdit("101");
    token_ = new QLineEdit;
    token_->setPlaceholderText("TEACHER_TOKEN");
    token_->setEchoMode(QLineEdit::Password);
    teacherName_ = new QLineEdit("Преподаватель");
    auto* connectBtn = new QPushButton("Подключиться");
    form->addWidget(new QLabel("Сервер:"));
    form->addWidget(server_, 2);
    form->addWidget(new QLabel("Занятие:"));
    form->addWidget(lesson_);
    form->addWidget(new QLabel("Токен:"));
    form->addWidget(token_);
    form->addWidget(new QLabel("Имя:"));
    form->addWidget(teacherName_);
    form->addWidget(connectBtn);
    outer->addLayout(form);

    auto* toolbar = new QHBoxLayout;
    auto* screenBtn = new QPushButton("🖥 Экран");
    auto* cameraBtn = new QPushButton("🎥 Камера");
    auto* stopLectureBtn = new QPushButton("⏹ Остановить показ");
    micButton_ = new QPushButton("🎤 Микрофон");
    toolbar->addWidget(screenBtn);
    toolbar->addWidget(cameraBtn);
    toolbar->addWidget(stopLectureBtn);
    toolbar->addWidget(micButton_);
    toolbar->addStretch();
    outer->addLayout(toolbar);

    status_ = new QLabel("Не подключено");
    outer->addWidget(status_);

    auto* split = new QSplitter;

    auto* tabs = new QTabWidget;
    lecturePreview_ = new QLabel("Предпросмотр лекции");
    lecturePreview_->setAlignment(Qt::AlignCenter);
    lecturePreview_->setMinimumSize(640, 360);
    lecturePreview_->setStyleSheet("background:#111827;color:#e5e7eb;font-size:22px;border-radius:8px;");
    tabs->addTab(lecturePreview_, "Моя трансляция");

    remoteView_ = new RemoteView;
    tabs->addTab(remoteView_, "Экран ученика");

    auto* right = new QWidget;
    auto* rv = new QVBoxLayout(right);
    rv->addWidget(new QLabel("<b>Участники</b>"));
    students_ = new QListWidget;
    rv->addWidget(students_, 1);

    auto* viewBtn = new QPushButton("Посмотреть экран");
    auto* controlBtn = new QPushButton("Управлять");
    auto* stopBtn = new QPushButton("Отключиться от ученика");
    auto* audioBtn = new QPushButton("Аудиодиалог");
    auto* downloadBtn = new QPushButton("Скачать запись MP4");
    rv->addWidget(viewBtn);
    rv->addWidget(controlBtn);
    rv->addWidget(audioBtn);
    rv->addWidget(downloadBtn);
    rv->addWidget(stopBtn);

    split->addWidget(tabs);
    split->addWidget(right);
    split->setStretchFactor(0, 4);
    split->setStretchFactor(1, 1);
    outer->addWidget(split, 1);

    setCentralWidget(root);
    setWindowTitle("Classroom Teacher");

    screenTimer_->setInterval(100);

    connect(connectBtn, &QPushButton::clicked, this, &TeacherWindow::connectServer);
    connect(screenBtn, &QPushButton::clicked, this, &TeacherWindow::startScreenLecture);
    connect(cameraBtn, &QPushButton::clicked, this, &TeacherWindow::startCameraLecture);
    connect(stopLectureBtn, &QPushButton::clicked, this, &TeacherWindow::stopLecture);

    connect(micButton_, &QPushButton::clicked, this, [this] {
        if (!privateAudioTarget_.isEmpty()) {
            QMessageBox::information(this, "Микрофон",
                "Сначала завершите приватный аудиодиалог.");
            return;
        }
        micOn_ = !micOn_;
        if (micOn_) {
            if (!audio_->startCapture()) {
                micOn_ = false;
                QMessageBox::warning(this, "Микрофон", "Не удалось открыть микрофон.");
            }
        } else {
            audio_->stopCapture();
        }
        micButton_->setText(micOn_ ? "🎤 Микрофон: ВКЛ" : "🎤 Микрофон");
    });

    connect(audio_, &AudioPipe::captured, this, [this](const QByteArray& pcm) {
        if (!privateAudioTarget_.isEmpty())
            rtc_->sendTargetedFrame(RtcRelayClient::TeacherPrivateAudio, privateAudioTarget_, pcm);
        else if (micOn_)
            rtc_->sendFrame(RtcRelayClient::LectureAudio, pcm);
    });

    connect(screenTimer_, &QTimer::timeout, this, [this] {
        auto* screen = QGuiApplication::primaryScreen();
        if (!screen || !rtc_->isOpen())
            return;
        sendLectureImage(screen->grabWindow(0).toImage());
    });

    connect(viewBtn, &QPushButton::clicked, this, [this] {
        const auto id = selectedStudentId();
        if (id.isEmpty()) {
            QMessageBox::information(this, "Ученик", "Сначала выберите ученика.");
            return;
        }
        activeStudent_ = id;
        remoteView_->setTarget({});
        ws_->send(QJsonObject{{"type", "remote_request"}, {"target", id}, {"mode", "view"}});
    });
    connect(controlBtn, &QPushButton::clicked, this, [this] {
        const auto id = selectedStudentId();
        if (id.isEmpty()) {
            QMessageBox::information(this, "Ученик", "Сначала выберите ученика.");
            return;
        }
        activeStudent_ = id;
        remoteView_->setTarget(id);
        ws_->send(QJsonObject{{"type", "remote_request"}, {"target", id}, {"mode", "control"}});
        remoteView_->setFocus();
    });
    connect(stopBtn, &QPushButton::clicked, this, [this] {
        sendToSelected("remote_stop");
        activeStudent_.clear();
        remoteView_->setTarget({});
        remoteView_->setText("Экран выбранного ученика");
    });
    connect(audioBtn, &QPushButton::clicked, this, [this, audioBtn] {
        const auto id = selectedStudentId();
        if (id.isEmpty()) {
            QMessageBox::information(this, "Ученик", "Сначала выберите ученика.");
            return;
        }

        if (privateAudioTarget_ == id) {
            ws_->send(QJsonObject{{"type", "audio_stop"}, {"target", id}});
            privateAudioTarget_.clear();
            audio_->stopCapture();
            if (micOn_)
                audio_->startCapture();
            audioBtn->setText("Аудиодиалог");
            return;
        }

        if (!privateAudioTarget_.isEmpty())
            ws_->send(QJsonObject{{"type", "audio_stop"}, {"target", privateAudioTarget_}});

        privateAudioTarget_ = id;
        ws_->send(QJsonObject{{"type", "audio_request"}, {"target", id}});
        audio_->startPlayback();
        audio_->startCapture();
        audioBtn->setText("Завершить аудиодиалог");
    });
    connect(downloadBtn, &QPushButton::clicked, this, &TeacherWindow::downloadRecording);

    connect(remoteView_, &RemoteView::remoteEvent, this, [this](const QJsonObject& e) {
        rtc_->sendText(e);
    });

    connect(ws_, &WsClient::connected, this, [this] {
        status_->setText("● Сигналинг подключён, устанавливаю WebRTC...");
        rtc_->start();
    });
    connect(ws_, &WsClient::disconnected, this, [this] {
        status_->setText("○ Отключено");
        rtc_->stop();
        stopLecture();
    });
    connect(ws_, &WsClient::errorText, this, [this](const QString& e) {
        status_->setText("Ошибка: " + e);
    });
    connect(ws_, &WsClient::message, this, &TeacherWindow::onMessage);

    connect(rtc_, &RtcRelayClient::opened, this, [this] {
        status_->setText("● WebRTC готов");
    });
    connect(rtc_, &RtcRelayClient::stateText, this, [this](const QString& s) {
        status_->setText(s);
    });
    connect(rtc_, &RtcRelayClient::frameReceived, this, &TeacherWindow::onRtcFrame);
}

TeacherWindow::~TeacherWindow() {
    stopLecture();
}

void TeacherWindow::connectServer() {
    ws_->connectTo(QUrl(server_->text()), lesson_->text(),
                   "teacher-" + QUuid::createUuid().toString(QUuid::WithoutBraces),
                   "teacher", teacherName_->text(), token_->text());
}

void TeacherWindow::onMessage(const QJsonObject& obj) {
    const auto type = obj.value("type").toString();
    if (type == "presence") {
        students_->clear();
        names_.clear();
        for (const auto& v : obj.value("users").toArray()) {
            const auto u = v.toObject();
            if (u.value("role").toString() != "student")
                continue;
            const auto id = u.value("id").toString();
            const auto name = u.value("name").toString();
            names_[id] = name;
            auto* item = new QListWidgetItem("● " + name);
            item->setData(Qt::UserRole, id);
            students_->addItem(item);
        }
    } else if (type == "hand_raise") {
        const auto id = obj.value("student_id").toString();
        for (int i = 0; i < students_->count(); ++i) {
            auto* item = students_->item(i);
            if (item->data(Qt::UserRole).toString() == id)
                item->setText("✋ " + names_.value(id, id));
        }
    } else if (type == "hand_down") {
        const auto id = obj.value("student_id").toString();
        for (int i = 0; i < students_->count(); ++i) {
            auto* item = students_->item(i);
            if (item->data(Qt::UserRole).toString() == id)
                item->setText("● " + names_.value(id, id));
        }
    }
}

QString TeacherWindow::selectedStudentId() const {
    auto* item = students_->currentItem();
    return item ? item->data(Qt::UserRole).toString() : QString{};
}

void TeacherWindow::sendToSelected(const QString& type) {
    const auto id = selectedStudentId();
    if (id.isEmpty()) {
        QMessageBox::information(this, "Ученик", "Сначала выберите ученика.");
        return;
    }
    activeStudent_ = id;
    remoteView_->setTarget(id);
    ws_->send(QJsonObject{{"type", type}, {"target", id}});
}

void TeacherWindow::startScreenLecture() {
    stopLecture();
    screenTimer_->start();
    ws_->send(QJsonObject{{"type", "lecture_state"}, {"mode", "screen"}, {"active", true}});
}

void TeacherWindow::startCameraLecture() {
    stopLecture();

    const auto device = QMediaDevices::defaultVideoInput();
    if (device.isNull()) {
        QMessageBox::warning(this, "Камера", "Камера не найдена.");
        return;
    }

    camera_ = new QCamera(device, this);
    captureSession_ = new QMediaCaptureSession(this);
    videoSink_ = new QVideoSink(this);
    captureSession_->setCamera(camera_);
    captureSession_->setVideoSink(videoSink_);

    connect(videoSink_, &QVideoSink::videoFrameChanged, this, [this](const QVideoFrame& frame) {
        if (!rtc_->isOpen())
            return;
        const qint64 now = QDateTime::currentMSecsSinceEpoch();
        if (now - lastCameraSendMs_ < 100)
            return;
        lastCameraSendMs_ = now;
        const QImage image = frame.toImage();
        if (!image.isNull())
            sendLectureImage(image);
    });

    camera_->start();
    ws_->send(QJsonObject{{"type", "lecture_state"}, {"mode", "camera"}, {"active", true}});
}

void TeacherWindow::stopLecture() {
    screenTimer_->stop();

    if (camera_) {
        camera_->stop();
        camera_->deleteLater();
        camera_ = nullptr;
    }
    if (captureSession_) {
        captureSession_->deleteLater();
        captureSession_ = nullptr;
    }
    if (videoSink_) {
        videoSink_->deleteLater();
        videoSink_ = nullptr;
    }

    if (ws_->isConnected())
        ws_->send(QJsonObject{{"type", "lecture_state"}, {"active", false}});
}

void TeacherWindow::sendLectureImage(const QImage& source) {
    if (!rtc_->isOpen() || source.isNull())
        return;

    const QImage image = source.scaled(1280, 720, Qt::KeepAspectRatio, Qt::SmoothTransformation);
    showLocalPreview(image);

    QByteArray body;
    QBuffer buffer(&body);
    buffer.open(QIODevice::WriteOnly);
    if (!image.save(&buffer, "JPG", 65))
        return;

    rtc_->sendFrame(RtcRelayClient::LectureVideo, body);
}

void TeacherWindow::showLocalPreview(const QImage& image) {
    lecturePreview_->setPixmap(QPixmap::fromImage(image).scaled(
        lecturePreview_->size(), Qt::KeepAspectRatio, Qt::SmoothTransformation));
}

void TeacherWindow::onRtcFrame(quint8 kind, const QString& source, const QByteArray& payload) {
    if (kind == RtcRelayClient::StudentVideo) {
        if (!activeStudent_.isEmpty() && source != activeStudent_)
            return;

        QPixmap pix;
        if (pix.loadFromData(payload)) {
            remoteView_->setPixmap(pix.scaled(
                remoteView_->size(), Qt::KeepAspectRatio, Qt::SmoothTransformation));
        }
    } else if (kind == RtcRelayClient::StudentAudio) {
        if (activeStudent_.isEmpty() || source == activeStudent_)
            audio_->play(payload);
    }
}


void TeacherWindow::downloadRecording() {
    const QString studentId = selectedStudentId();
    if (studentId.isEmpty()) {
        QMessageBox::information(this, "Запись", "Сначала выберите ученика.");
        return;
    }

    const QString fileName = QFileDialog::getSaveFileName(
        this,
        "Сохранить запись",
        QString("lesson-%1-%2.mp4").arg(lesson_->text(), names_.value(studentId, studentId)),
        "MP4 (*.mp4)");
    if (fileName.isEmpty())
        return;

    QUrl url(server_->text());
    if (url.scheme() == "wss")
        url.setScheme("https");
    else if (url.scheme() == "ws")
        url.setScheme("http");
    url.setPath(QString("/api/v1/lessons/%1/students/%2/recording.mp4")
                    .arg(lesson_->text(), studentId));
    url.setQuery(QString());

    QNetworkRequest request(url);
    request.setRawHeader("Authorization", "Bearer " + token_->text().toUtf8());
    auto* reply = network_->get(request);
    status_->setText("Формируется MP4...");

    connect(reply, &QNetworkReply::finished, this, [this, reply, fileName] {
        const auto data = reply->readAll();
        if (reply->error() != QNetworkReply::NoError) {
            QMessageBox::warning(this, "Запись", "Ошибка скачивания: " + reply->errorString());
            status_->setText("Ошибка экспорта записи");
            reply->deleteLater();
            return;
        }

        QFile file(fileName);
        if (!file.open(QIODevice::WriteOnly) || file.write(data) != data.size()) {
            QMessageBox::warning(this, "Запись", "Не удалось сохранить файл.");
            status_->setText("Ошибка сохранения записи");
            reply->deleteLater();
            return;
        }
        file.close();
        status_->setText("Запись сохранена: " + fileName);
        reply->deleteLater();
    });
}
