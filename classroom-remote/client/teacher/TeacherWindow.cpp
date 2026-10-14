#include "TeacherWindow.h"
#include "RemoteView.h"
#include "TeacherLessonsWidget.h"
#include "../common/WsClient.h"
#include "../common/RtcRelayClient.h"
#include "../common/AudioPipe.h"
#include "../common/MediaCodec.h"
#include "../common/InviteLink.h"

#include <QBuffer>
#include <QScreenCapture>
#include <QMediaRecorder>
#include <QMediaPlayer>
#include <QMediaFormat>
#include <QAudioInput>
#include <QAudioFormat>
#include <QAudioBufferOutput>
#include <QAudioBuffer>
#include <QCamera>
#include <QDateTime>
#include <QGuiApplication>
#include <QHBoxLayout>
#include <QImage>
#include <QJsonArray>
#include <QJsonDocument>
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
      codec_(new MediaCodec),
      screenTimer_(new QTimer(this)),
      network_(new QNetworkAccessManager(this)) {

    auto* root = new QWidget;
    auto* outer = new QVBoxLayout(root);

    invite_ = new QLineEdit;
    invite_->setPlaceholderText("Вставьте ссылку преподавателя");
    teacherName_ = new QLineEdit("Преподаватель");
    auto* inviteBtn = new QPushButton("Войти по ссылке");
    auto* advancedBtn = new QPushButton("Ручная настройка");

    auto* inviteRow = new QHBoxLayout;
    inviteRow->addWidget(new QLabel("Ссылка:"));
    inviteRow->addWidget(invite_, 3);
    inviteRow->addWidget(new QLabel("Имя:"));
    inviteRow->addWidget(teacherName_);
    inviteRow->addWidget(inviteBtn);
    inviteRow->addWidget(advancedBtn);
    outer->addLayout(inviteRow);

    auto* advanced = new QWidget;
    auto* form = new QHBoxLayout(advanced);
    form->setContentsMargins(0, 0, 0, 0);
    server_ = new QLineEdit("ws://127.0.0.1:8080/ws");
    lesson_ = new QLineEdit("101");
    token_ = new QLineEdit;
    token_->setPlaceholderText("Ключ доступа");
    token_->setEchoMode(QLineEdit::Password);
    auto* connectBtn = new QPushButton("Подключиться вручную");
    form->addWidget(new QLabel("Сервер:"));
    form->addWidget(server_, 2);
    form->addWidget(new QLabel("Занятие:"));
    form->addWidget(lesson_);
    form->addWidget(new QLabel("Ключ:"));
    form->addWidget(token_);
    form->addWidget(connectBtn);
    advanced->setVisible(false);
    outer->addWidget(advanced);

    connect(inviteBtn, &QPushButton::clicked, this, [this] {
        openInviteUrl(QUrl::fromUserInput(invite_->text()));
    });
    connect(invite_, &QLineEdit::returnPressed, this, [this] {
        openInviteUrl(QUrl::fromUserInput(invite_->text()));
    });
    connect(advancedBtn, &QPushButton::clicked, this, [advanced] {
        advanced->setVisible(!advanced->isVisible());
    });

    auto* toolbar = new QHBoxLayout;
    auto* screenBtn = new QPushButton("🖥 Экран");
    auto* cameraBtn = new QPushButton("🎥 Камера");
    auto* fileBtn = new QPushButton("▶ Видео-файл");
    auto* stopLectureBtn = new QPushButton("⏹ Остановить показ");
    micButton_ = new QPushButton("🎤 Микрофон");
    toolbar->addWidget(screenBtn);
    toolbar->addWidget(cameraBtn);
    toolbar->addWidget(fileBtn);
    toolbar->addWidget(stopLectureBtn);
    toolbar->addWidget(micButton_);
    toolbar->addStretch();
    outer->addLayout(toolbar);

    auto* recordToolbar = new QHBoxLayout;
    auto* recordCameraBtn = new QPushButton("⏺ Записать камеру");
    auto* recordScreenBtn = new QPushButton("⏺ Записать экран");
    auto* stopRecordBtn = new QPushButton("⏹ Стоп записи");
    recordToolbar->addWidget(new QLabel("<b>Локальная запись:</b>"));
    recordToolbar->addWidget(recordCameraBtn);
    recordToolbar->addWidget(recordScreenBtn);
    recordToolbar->addWidget(stopRecordBtn);
    recordToolbar->addStretch();
    outer->addLayout(recordToolbar);

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

    lessonsWidget_ = new TeacherLessonsWidget;
    tabs->addTab(lessonsWidget_, "Уроки и ДЗ");

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
    auto* downloadZipBtn = new QPushButton("Скачать кадры ZIP");
    rv->addWidget(viewBtn);
    rv->addWidget(controlBtn);
    rv->addWidget(audioBtn);
    rv->addWidget(downloadBtn);
    rv->addWidget(downloadZipBtn);
    rv->addWidget(stopBtn);

    split->addWidget(tabs);
    split->addWidget(right);
    split->setStretchFactor(0, 4);
    split->setStretchFactor(1, 1);
    outer->addWidget(split, 1);

    setCentralWidget(root);
    setWindowTitle("Classroom Teacher");

    if (!codec_->initialize())
        status_->setText("Ошибка инициализации OpenH264/Opus");

    screenTimer_->setInterval(100);

    connect(connectBtn, &QPushButton::clicked, this, &TeacherWindow::connectServer);
    connect(screenBtn, &QPushButton::clicked, this, &TeacherWindow::startScreenLecture);
    connect(cameraBtn, &QPushButton::clicked, this, &TeacherWindow::startCameraLecture);
    connect(fileBtn, &QPushButton::clicked, this, &TeacherWindow::startFileLecture);
    connect(stopLectureBtn, &QPushButton::clicked, this, &TeacherWindow::stopLecture);
    connect(recordCameraBtn, &QPushButton::clicked, this, &TeacherWindow::startCameraRecording);
    connect(recordScreenBtn, &QPushButton::clicked, this, &TeacherWindow::startScreenRecording);
    connect(stopRecordBtn, &QPushButton::clicked, this, &TeacherWindow::stopRecording);

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
        const QString track = !privateAudioTarget_.isEmpty()
            ? RtcRelayClient::PrivateAudioTrack
            : RtcRelayClient::LectureAudioTrack;
        const auto packets = codec_->encodeAudio(pcm, track);

        if (privateAudioTarget_.isEmpty() && !micOn_)
            return;

        for (const auto& packet : packets)
            rtc_->sendAudio(track, packet, 20);
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
    connect(downloadZipBtn, &QPushButton::clicked, this, &TeacherWindow::downloadFramesZip);

    connect(remoteView_, &RemoteView::remoteEvent, this, [this](const QJsonObject& e) {
        rtc_->sendText(e);
    });

    connect(ws_, &WsClient::connected, this, [this] {
        status_->setText("● Сигналинг подключён, получаю ICE/TURN конфигурацию...");
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
    connect(rtc_, &RtcRelayClient::videoFrameReceived, this, &TeacherWindow::onRtcVideo);
    connect(rtc_, &RtcRelayClient::audioFrameReceived, this, &TeacherWindow::onRtcAudio);
}

TeacherWindow::~TeacherWindow() {
    stopRecording();
    stopLecture();
    delete codec_;
}

void TeacherWindow::openInviteUrl(const QUrl& rawUrl) {
    const QUrl inviteUrl = InviteLink::normalizeInviteUrl(rawUrl);
    const QUrl endpoint = InviteLink::resolveEndpoint(inviteUrl);
    if (!endpoint.isValid() || endpoint.scheme().isEmpty()) {
        QMessageBox::warning(this, "Ссылка", "Некорректная ссылка приглашения.");
        return;
    }

    invite_->setText(inviteUrl.toString());
    status_->setText("Проверяю ссылку приглашения...");

    auto* reply = network_->get(QNetworkRequest(endpoint));
    connect(reply, &QNetworkReply::finished, this, [this, reply] {
        const QByteArray body = reply->readAll();
        if (reply->error() != QNetworkReply::NoError) {
            QMessageBox::warning(this, "Ссылка",
                "Ссылка недействительна, отозвана или сервер недоступен.");
            status_->setText("Не удалось войти по ссылке");
            reply->deleteLater();
            return;
        }

        const QJsonDocument doc = QJsonDocument::fromJson(body);
        if (!doc.isObject()) {
            QMessageBox::warning(this, "Ссылка", "Сервер вернул некорректный ответ.");
            reply->deleteLater();
            return;
        }

        const QJsonObject obj = doc.object();
        if (obj.value("role").toString() != "teacher") {
            QMessageBox::warning(this, "Ссылка",
                "Это ссылка ученика. Откройте её через ClassroomStudent.");
            reply->deleteLater();
            return;
        }

        server_->setText(obj.value("ws_url").toString());
        lesson_->setText(obj.value("lesson_id").toString());
        token_->setText(obj.value("access_token").toString());
        lessonsWidget_->setMaxUploadMB(obj.value("max_upload_mb").toInt(50));
        status_->setText("Ссылка принята, подключаюсь...");
        reply->deleteLater();
        connectServer();
    });
}

void TeacherWindow::connectServer() {
    QUrl wsUrl(server_->text());
    QUrl httpUrl = wsUrl;
    if (httpUrl.scheme() == "wss") httpUrl.setScheme("https");
    else if (httpUrl.scheme() == "ws") httpUrl.setScheme("http");
    httpUrl.setPath("");
    httpUrl.setQuery(QString());
    lessonsWidget_->setSession(httpUrl, lesson_->text(), token_->text());

    ws_->connectTo(wsUrl, lesson_->text(),
                   "teacher-" + QUuid::createUuid().toString(QUuid::WithoutBraces),
                   "teacher", teacherName_->text(), token_->text());
}

void TeacherWindow::onMessage(const QJsonObject& obj) {
    const auto type = obj.value("type").toString();
    if (type == "server_config") {
        lessonsWidget_->setMaxUploadMB(obj.value("max_upload_mb").toInt(50));
        QStringList iceServers;
        for (const auto& v : obj.value("ice_servers").toArray())
            iceServers << v.toString();
        status_->setText("● ICE/TURN получен, устанавливаю WebRTC...");
        rtc_->start("teacher", iceServers);
        return;
    }
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
    } else if (type == "keyframe_request") {
        codec_->requestKeyframe();
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

void TeacherWindow::startFileLecture() {
    if (!privateAudioTarget_.isEmpty()) {
        QMessageBox::information(this, "Видео-файл",
            "Сначала завершите приватный аудиодиалог.");
        return;
    }

    const QString path = QFileDialog::getOpenFileName(
        this,
        "Выберите видео для трансляции",
        QString(),
        "Видео (*.mp4 *.mkv *.avi *.mov *.webm);;Все файлы (*.*)");
    if (path.isEmpty())
        return;

    stopLecture();

    if (micOn_) {
        micOn_ = false;
        audio_->stopCapture();
        micButton_->setText("🎤 Микрофон");
    }

    filePlayer_ = new QMediaPlayer(this);
    fileVideoSink_ = new QVideoSink(this);

    QAudioFormat audioFormat;
    audioFormat.setSampleRate(48000);
    audioFormat.setChannelCount(1);
    audioFormat.setSampleFormat(QAudioFormat::Int16);
    fileAudioBuffer_ = new QAudioBufferOutput(audioFormat, this);

    filePlayer_->setVideoOutput(fileVideoSink_);
    filePlayer_->setAudioBufferOutput(fileAudioBuffer_);

    lastFileVideoSendMs_ = 0;
    codec_->requestKeyframe();

    connect(fileVideoSink_, &QVideoSink::videoFrameChanged, this,
            [this](const QVideoFrame& frame) {
        if (!rtc_->isOpen())
            return;

        const qint64 now = QDateTime::currentMSecsSinceEpoch();
        if (now - lastFileVideoSendMs_ < 90)
            return;
        lastFileVideoSendMs_ = now;

        const QImage image = frame.toImage();
        if (!image.isNull())
            sendLectureImage(image);
    });

    connect(fileAudioBuffer_, &QAudioBufferOutput::audioBufferReceived, this,
            [this](const QAudioBuffer& buffer) {
        if (!rtc_->isOpen() || !buffer.isValid() || buffer.byteCount() <= 0)
            return;

        const QByteArray pcm(
            reinterpret_cast<const char*>(buffer.constData<char>()),
            buffer.byteCount());
        const auto packets = codec_->encodeAudio(pcm, "lecture-file");
        for (const auto& packet : packets)
            rtc_->sendAudio(RtcRelayClient::LectureAudioTrack, packet, 20);
    });

    connect(filePlayer_, &QMediaPlayer::mediaStatusChanged, this,
            [this](QMediaPlayer::MediaStatus status) {
        if (status == QMediaPlayer::EndOfMedia) {
            status_->setText("Видео завершено");
            stopLecture();
        } else if (status == QMediaPlayer::InvalidMedia) {
            QMessageBox::warning(this, "Видео-файл",
                "Не удалось открыть видео: " + filePlayer_->errorString());
            stopLecture();
        }
    });

    filePlayer_->setSource(QUrl::fromLocalFile(path));
    filePlayer_->play();

    status_->setText("▶ Трансляция видео: " + QFileInfo(path).fileName());
    ws_->send(QJsonObject{
        {"type", "lecture_state"},
        {"mode", "file"},
        {"name", QFileInfo(path).fileName()},
        {"active", true}
    });
}

void TeacherWindow::startCameraRecording() {
    startRecording(false);
}

void TeacherWindow::startScreenRecording() {
    startRecording(true);
}

void TeacherWindow::startRecording(bool screen) {
    if (mediaRecorder_ &&
        mediaRecorder_->recorderState() == QMediaRecorder::RecordingState) {
        QMessageBox::information(this, "Запись",
            "Сначала остановите текущую запись.");
        return;
    }

    const QString suggested = QString("lesson-%1-%2.mp4")
        .arg(lesson_->text(),
             QDateTime::currentDateTime().toString("yyyyMMdd-HHmmss"));
    const QString path = QFileDialog::getSaveFileName(
        this,
        screen ? "Записать экран" : "Записать камеру",
        suggested,
        "MP4 (*.mp4)");
    if (path.isEmpty())
        return;

    stopLecture();
    if (mediaRecorder_) {
        QMessageBox::information(this, "Запись",
            "Дождитесь завершения предыдущей записи.");
        return;
    }

    recordingPath_ = path;
    recordingSession_ = new QMediaCaptureSession(this);
    mediaRecorder_ = new QMediaRecorder(this);
    recordingAudio_ = new QAudioInput(this);
    recordingPreviewSink_ = new QVideoSink(this);

    recordingSession_->setRecorder(mediaRecorder_);
    recordingSession_->setAudioInput(recordingAudio_);
    recordingSession_->setVideoSink(recordingPreviewSink_);

    connect(recordingPreviewSink_, &QVideoSink::videoFrameChanged, this,
            [this](const QVideoFrame& frame) {
        const QImage image = frame.toImage();
        if (!image.isNull())
            showLocalPreview(image);
    });

    if (screen) {
        recordingScreen_ = new QScreenCapture(this);
        recordingScreen_->setScreen(QGuiApplication::primaryScreen());
        recordingSession_->setScreenCapture(recordingScreen_);
        recordingScreen_->start();
    } else {
        const auto device = QMediaDevices::defaultVideoInput();
        if (device.isNull()) {
            QMessageBox::warning(this, "Запись", "Камера не найдена.");
            stopRecording();
            return;
        }
        recordingCamera_ = new QCamera(device, this);
        recordingSession_->setCamera(recordingCamera_);
        recordingCamera_->start();
    }

    QMediaFormat format(QMediaFormat::MPEG4);
    format.setVideoCodec(QMediaFormat::VideoCodec::H264);
    format.setAudioCodec(QMediaFormat::AudioCodec::AAC);
    format.resolveForEncoding(QMediaFormat::RequiresVideo);

    mediaRecorder_->setMediaFormat(format);
    mediaRecorder_->setQuality(QMediaRecorder::HighQuality);
    mediaRecorder_->setVideoResolution(1280, 720);
    mediaRecorder_->setVideoFrameRate(30.0);
    mediaRecorder_->setOutputLocation(QUrl::fromLocalFile(path));

    connect(mediaRecorder_, &QMediaRecorder::errorOccurred, this,
            [this](QMediaRecorder::Error, const QString& errorString) {
        QMessageBox::warning(this, "Запись",
            "Ошибка записи: " + errorString);
        status_->setText("Ошибка записи");
    });

    connect(mediaRecorder_, &QMediaRecorder::recorderStateChanged, this,
            [this](QMediaRecorder::RecorderState state) {
        if (state == QMediaRecorder::RecordingState) {
            status_->setText("⏺ Идёт запись: " + recordingPath_);
        } else if (state == QMediaRecorder::StoppedState &&
                   !recordingPath_.isEmpty()) {
            status_->setText("Запись сохранена: " + recordingPath_);
            cleanupRecording();
        }
    });

    mediaRecorder_->record();
}

void TeacherWindow::stopRecording() {
    if (!mediaRecorder_)
        return;

    if (recordingCamera_)
        recordingCamera_->stop();
    if (recordingScreen_)
        recordingScreen_->stop();

    if (mediaRecorder_->recorderState() != QMediaRecorder::StoppedState) {
        status_->setText("Завершаю запись...");
        mediaRecorder_->stop();
        return;
    }

    cleanupRecording();
}

void TeacherWindow::cleanupRecording() {
    if (recordingCamera_) {
        recordingCamera_->deleteLater();
        recordingCamera_ = nullptr;
    }
    if (recordingScreen_) {
        recordingScreen_->deleteLater();
        recordingScreen_ = nullptr;
    }
    if (recordingAudio_) {
        recordingAudio_->deleteLater();
        recordingAudio_ = nullptr;
    }
    if (recordingPreviewSink_) {
        recordingPreviewSink_->deleteLater();
        recordingPreviewSink_ = nullptr;
    }
    if (recordingSession_) {
        recordingSession_->deleteLater();
        recordingSession_ = nullptr;
    }
    if (mediaRecorder_) {
        mediaRecorder_->deleteLater();
        mediaRecorder_ = nullptr;
    }
    recordingPath_.clear();
}

void TeacherWindow::stopLecture() {
    screenTimer_->stop();

    if (filePlayer_) {
        filePlayer_->stop();
        filePlayer_->deleteLater();
        filePlayer_ = nullptr;
    }
    if (fileVideoSink_) {
        fileVideoSink_->deleteLater();
        fileVideoSink_ = nullptr;
    }
    if (fileAudioBuffer_) {
        fileAudioBuffer_->deleteLater();
        fileAudioBuffer_ = nullptr;
    }

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

    const QByteArray h264 = codec_->encodeVideo(image);
    if (!h264.isEmpty())
        rtc_->sendVideo(RtcRelayClient::LectureVideoTrack, h264, 100);
}

void TeacherWindow::showLocalPreview(const QImage& image) {
    lecturePreview_->setPixmap(QPixmap::fromImage(image).scaled(
        lecturePreview_->size(), Qt::KeepAspectRatio, Qt::SmoothTransformation));
}

void TeacherWindow::onRtcVideo(const QString& trackId, const QByteArray& h264) {
    if (trackId != RtcRelayClient::RemoteVideoTrack)
        return;

    const QImage image = codec_->decodeVideo(h264);
    if (!image.isNull()) {
        remoteView_->setPixmap(QPixmap::fromImage(image).scaled(
            remoteView_->size(), Qt::KeepAspectRatio, Qt::SmoothTransformation));
    }
}

void TeacherWindow::onRtcAudio(const QString& trackId, const QByteArray& opus) {
    if (trackId != RtcRelayClient::RemoteAudioTrack)
        return;

    const QByteArray pcm = codec_->decodeAudio(opus, trackId);
    if (!pcm.isEmpty())
        audio_->play(pcm);
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


void TeacherWindow::downloadFramesZip() {
    const QString studentId = selectedStudentId();
    if (studentId.isEmpty()) {
        QMessageBox::information(this, "Запись", "Сначала выберите ученика.");
        return;
    }

    const QString fileName = QFileDialog::getSaveFileName(
        this,
        "Сохранить архив кадров",
        QString("lesson-%1-%2.zip").arg(lesson_->text(), names_.value(studentId, studentId)),
        "ZIP (*.zip)");
    if (fileName.isEmpty())
        return;

    QUrl url(server_->text());
    if (url.scheme() == "wss")
        url.setScheme("https");
    else if (url.scheme() == "ws")
        url.setScheme("http");
    url.setPath(QString("/api/v1/lessons/%1/students/%2/recording.zip")
                    .arg(lesson_->text(), studentId));
    url.setQuery(QString());

    QNetworkRequest request(url);
    request.setRawHeader("Authorization", "Bearer " + token_->text().toUtf8());
    auto* reply = network_->get(request);
    status_->setText("Скачиваю архив кадров...");

    connect(reply, &QNetworkReply::finished, this, [this, reply, fileName] {
        const auto data = reply->readAll();
        if (reply->error() != QNetworkReply::NoError) {
            QMessageBox::warning(this, "Запись", "Ошибка скачивания: " + reply->errorString());
            status_->setText("Ошибка скачивания архива");
            reply->deleteLater();
            return;
        }

        QFile file(fileName);
        if (!file.open(QIODevice::WriteOnly) || file.write(data) != data.size()) {
            QMessageBox::warning(this, "Запись", "Не удалось сохранить ZIP.");
            status_->setText("Ошибка сохранения архива");
            reply->deleteLater();
            return;
        }
        file.close();
        status_->setText("Архив сохранён: " + fileName);
        reply->deleteLater();
    });
}
