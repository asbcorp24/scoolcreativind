#include "TeacherWindow.h"
#include "RemoteView.h"
#include "TeacherLessonsWidget.h"
#include "../common/WsClient.h"
#include "../common/RtcRelayClient.h"
#include "../common/AudioPipe.h"
#include "../common/MediaCodec.h"
#include "../common/InviteLink.h"
#include "../common/AnnotationOverlay.h"

#include <QBuffer>
#include <QRadioButton>
#include <QButtonGroup>
#include <QComboBox>
#include <QSettings>
#include <QAudioDevice>
#include <QCameraDevice>
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
#include <QJsonValue>
#include <QHttpMultiPart>
#include <QStackedLayout>
#include <QPlainTextEdit>
#include <QInputDialog>
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
#include <QFileInfo>
#include <QFileDialog>
#include <QNetworkAccessManager>
#include <QNetworkReply>
#include <QNetworkRequest>
#include <QUrl>
#include <QMessageBox>
#include <QPixmap>
#include <QPushButton>
#include <QRegularExpressionValidator>
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

    config_ = ClientConfig::load("Преподаватель");

    invite_ = new QLineEdit(this);
    invite_->hide();
    server_ = new QLineEdit(this);
    server_->hide();
    lesson_ = new QLineEdit(config_.lessonId, this);
    lesson_->hide();
    token_ = new QLineEdit(this);
    token_->hide();
    teacherName_ = new QLineEdit(config_.displayName, this);
    teacherName_->hide();

    auto* loginCard = new QWidget;
    loginCard->setObjectName("sciCard");
    auto* loginLayout = new QHBoxLayout(loginCard);
    loginLayout->setContentsMargins(18, 14, 18, 14);

    auto* loginTitle = new QLabel("<b>CLASSROOM / TEACHER</b>");
    loginTitle->setObjectName("sciEyebrow");
    pin_ = new QLineEdit;
    pin_->setPlaceholderText("Числовой пароль");
    pin_->setEchoMode(QLineEdit::Password);
    pin_->setMaxLength(10);
    pin_->setMinimumWidth(180);
    pin_->setValidator(new QRegularExpressionValidator(QRegularExpression("\\d{0,10}"), pin_));

    auto* loginBtn = new QPushButton("Войти");
    loginBtn->setProperty("accent", "primary");
    auto* settingsBtn = new QPushButton("⚙ Настройки");

    loginLayout->addWidget(loginTitle);
    loginLayout->addStretch();
    loginLayout->addWidget(new QLabel("Пароль:"));
    loginLayout->addWidget(pin_);
    loginLayout->addWidget(loginBtn);
    loginLayout->addWidget(settingsBtn);
    outer->addWidget(loginCard);

    connect(loginBtn, &QPushButton::clicked, this, &TeacherWindow::loginWithPIN);
    connect(pin_, &QLineEdit::returnPressed, this, &TeacherWindow::loginWithPIN);
    connect(settingsBtn, &QPushButton::clicked, this, &TeacherWindow::editConnectionSettings);

    auto* toolbar = new QHBoxLayout;
    auto* screenBtn = new QPushButton("🖥 Экран");
    auto* cameraBtn = new QPushButton("🎥 Камера");
    auto* fileBtn = new QPushButton("▶ Видео-файл");
    auto* stopLectureBtn = new QPushButton("⏹ Остановить показ");
    micButton_ = new QPushButton("🎤 Микрофон");

    cameraCombo_ = new QComboBox;
    micCombo_ = new QComboBox;
    cameraCombo_->setMinimumWidth(220);
    micCombo_->setMinimumWidth(220);

    QSettings deviceSettings("SHKI", "ClassroomRemoteTeacher");
    const QByteArray savedCameraId = deviceSettings.value("devices/camera_id").toByteArray();
    const QByteArray savedMicId = deviceSettings.value("devices/microphone_id").toByteArray();

    int savedCameraIndex = -1;
    const auto cameras = QMediaDevices::videoInputs();
    for (int i = 0; i < cameras.size(); ++i) {
        cameraCombo_->addItem(cameras[i].description(), cameras[i].id());
        if (cameras[i].id() == savedCameraId)
            savedCameraIndex = i;
    }
    if (savedCameraIndex >= 0)
        cameraCombo_->setCurrentIndex(savedCameraIndex);

    int savedMicIndex = -1;
    const auto microphones = QMediaDevices::audioInputs();
    for (int i = 0; i < microphones.size(); ++i) {
        micCombo_->addItem(microphones[i].description(), microphones[i].id());
        if (microphones[i].id() == savedMicId)
            savedMicIndex = i;
    }
    if (savedMicIndex >= 0)
        micCombo_->setCurrentIndex(savedMicIndex);

    connect(cameraCombo_, &QComboBox::currentIndexChanged, this, [this](int) {
        QSettings s("SHKI", "ClassroomRemoteTeacher");
        s.setValue("devices/camera_id", cameraCombo_->currentData().toByteArray());
    });
    connect(micCombo_, &QComboBox::currentIndexChanged, this, [this](int) {
        QSettings s("SHKI", "ClassroomRemoteTeacher");
        s.setValue("devices/microphone_id", micCombo_->currentData().toByteArray());
    });

    toolbar->addWidget(screenBtn);
    toolbar->addWidget(cameraBtn);
    toolbar->addWidget(fileBtn);
    toolbar->addWidget(stopLectureBtn);
    toolbar->addWidget(micButton_);
    toolbar->addStretch();
    outer->addLayout(toolbar);

    auto* devicesRow = new QHBoxLayout;
    devicesRow->addWidget(new QLabel("Камера:"));
    devicesRow->addWidget(cameraCombo_, 1);
    devicesRow->addWidget(new QLabel("Микрофон:"));
    devicesRow->addWidget(micCombo_, 1);
    outer->addLayout(devicesRow);

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

    auto* previewPage = new QWidget;
    auto* previewLayout = new QVBoxLayout(previewPage);
    auto* annotationBar = new QHBoxLayout;
    auto* annOff = new QPushButton("Курсор");
    auto* annMarker = new QPushButton("Маркер");
    auto* annArrow = new QPushButton("Стрелка");
    auto* annRect = new QPushButton("Прямоугольник");
    auto* annText = new QPushButton("Текст");
    auto* annLaser = new QPushButton("Лазер");
    auto* annClear = new QPushButton("Очистить");
    annotationBar->addWidget(new QLabel("<b>Аннотации:</b>"));
    annotationBar->addWidget(annOff);
    annotationBar->addWidget(annMarker);
    annotationBar->addWidget(annArrow);
    annotationBar->addWidget(annRect);
    annotationBar->addWidget(annText);
    annotationBar->addWidget(annLaser);
    annotationBar->addWidget(annClear);
    annotationBar->addStretch();
    previewLayout->addLayout(annotationBar);

    auto* previewStackHost = new QWidget;
    auto* previewStack = new QStackedLayout(previewStackHost);
    previewStack->setStackingMode(QStackedLayout::StackAll);
    lecturePreview_ = new QLabel("Предпросмотр лекции");
    lecturePreview_->setAlignment(Qt::AlignCenter);
    lecturePreview_->setMinimumSize(640, 360);
    lecturePreview_->setStyleSheet("background:#0c0f16;color:#f5f7ff;font-size:22px;border:1px solid rgba(255,255,255,28);border-radius:18px;");
    annotationOverlay_ = new AnnotationOverlay;
    annotationOverlay_->setEditable(true);
    previewStack->addWidget(lecturePreview_);
    previewStack->addWidget(annotationOverlay_);
    previewStack->setCurrentWidget(annotationOverlay_);
    annotationOverlay_->raise();
    previewLayout->addWidget(previewStackHost, 1);
    tabs->addTab(previewPage, "Моя трансляция");

    remoteView_ = new RemoteView;
    tabs->addTab(remoteView_, "Экран ученика");

    lessonsWidget_ = new TeacherLessonsWidget;
    tabs->addTab(lessonsWidget_, "Уроки и ДЗ");

    auto* interactivePage = new QWidget;
    auto* interactiveLayout = new QVBoxLayout(interactivePage);

    auto* pollRow = new QHBoxLayout;
    auto* pollBtn = new QPushButton("Запустить быстрый опрос");
    pollStatus_ = new QLabel("Опрос не запущен");
    pollStatus_->setWordWrap(true);
    pollRow->addWidget(pollBtn);
    pollRow->addWidget(pollStatus_, 1);
    interactiveLayout->addLayout(pollRow);

    auto* chatControls = new QHBoxLayout;
    chatToggle_ = new QPushButton("Общий чат: ВКЛ");
    auto* quickFileBtn = new QPushButton("Отправить файл сейчас");
    auto* attendanceBtn = new QPushButton("Посещаемость");
    chatControls->addWidget(chatToggle_);
    chatControls->addWidget(quickFileBtn);
    chatControls->addWidget(attendanceBtn);
    chatControls->addStretch();
    interactiveLayout->addLayout(chatControls);

    chatLog_ = new QPlainTextEdit;
    chatLog_->setReadOnly(true);
    chatLog_->setPlaceholderText("Сообщения урока");
    interactiveLayout->addWidget(chatLog_, 1);

    auto* chatRow = new QHBoxLayout;
    chatInput_ = new QLineEdit;
    chatInput_->setPlaceholderText("Сообщение...");
    auto* sendAllBtn = new QPushButton("Всем");
    auto* sendPrivateBtn = new QPushButton("Выбранному");
    chatRow->addWidget(chatInput_, 1);
    chatRow->addWidget(sendAllBtn);
    chatRow->addWidget(sendPrivateBtn);
    interactiveLayout->addLayout(chatRow);

    tabs->addTab(interactivePage, "Интерактив");

    auto* right = new QWidget;
    auto* rv = new QVBoxLayout(right);
    rv->addWidget(new QLabel("<b>Выберите ученика</b>"));

    selectedStudentLabel_ = new QLabel("Никто не выбран");
    selectedStudentLabel_->setWordWrap(true);
    selectedStudentLabel_->setStyleSheet(
        "background:#0d1017;color:#cfd5e2;padding:10px;border:1px solid rgba(255,255,255,28);border-radius:12px;");
    rv->addWidget(selectedStudentLabel_);

    students_ = new QListWidget;
    students_->setSelectionMode(QAbstractItemView::NoSelection);
    students_->setSpacing(2);
    studentButtons_ = new QButtonGroup(this);
    studentButtons_->setExclusive(true);
    rv->addWidget(students_, 1);

    auto* viewBtn = new QPushButton("Посмотреть экран");
    auto* controlBtn = new QPushButton("Управлять");
    auto* stopBtn = new QPushButton("Отключиться от ученика");
    auto* audioBtn = new QPushButton("Аудиодиалог");
    auto* downloadBtn = new QPushButton("Скачать запись MP4");
    auto* downloadZipBtn = new QPushButton("Скачать кадры ZIP");

    screenBtn->setProperty("accent", "cyan");
    cameraBtn->setProperty("accent", "cyan");
    fileBtn->setProperty("accent", "primary");
    stopLectureBtn->setProperty("accent", "danger");
    recordCameraBtn->setProperty("accent", "primary");
    recordScreenBtn->setProperty("accent", "primary");
    stopRecordBtn->setProperty("accent", "danger");
    viewBtn->setProperty("accent", "cyan");
    controlBtn->setProperty("accent", "primary");
    audioBtn->setProperty("accent", "success");
    stopBtn->setProperty("accent", "danger");
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

    connect(screenBtn, &QPushButton::clicked, this, &TeacherWindow::startScreenLecture);
    connect(cameraBtn, &QPushButton::clicked, this, &TeacherWindow::startCameraLecture);
    connect(fileBtn, &QPushButton::clicked, this, &TeacherWindow::startFileLecture);
    connect(stopLectureBtn, &QPushButton::clicked, this, &TeacherWindow::stopLecture);
    connect(recordCameraBtn, &QPushButton::clicked, this, &TeacherWindow::startCameraRecording);
    connect(recordScreenBtn, &QPushButton::clicked, this, &TeacherWindow::startScreenRecording);
    connect(stopRecordBtn, &QPushButton::clicked, this, &TeacherWindow::stopRecording);

    connect(annOff, &QPushButton::clicked, this, [this] { annotationOverlay_->setTool(AnnotationOverlay::Tool::Off); });
    connect(annMarker, &QPushButton::clicked, this, [this] { annotationOverlay_->setTool(AnnotationOverlay::Tool::Marker); });
    connect(annArrow, &QPushButton::clicked, this, [this] { annotationOverlay_->setTool(AnnotationOverlay::Tool::Arrow); });
    connect(annRect, &QPushButton::clicked, this, [this] { annotationOverlay_->setTool(AnnotationOverlay::Tool::Rectangle); });
    connect(annText, &QPushButton::clicked, this, [this] { annotationOverlay_->setTool(AnnotationOverlay::Tool::Text); });
    connect(annLaser, &QPushButton::clicked, this, [this] { annotationOverlay_->setTool(AnnotationOverlay::Tool::Laser); });
    connect(annClear, &QPushButton::clicked, this, [this] {
        annotationOverlay_->clearAll();
        ws_->send(QJsonObject{{"type", "annotation_clear"}});
    });
    connect(annotationOverlay_, &AnnotationOverlay::annotationCreated, this,
            [this](const QJsonObject& obj) { ws_->send(obj); });

    connect(pollBtn, &QPushButton::clicked, this, &TeacherWindow::startPoll);
    connect(quickFileBtn, &QPushButton::clicked, this, &TeacherWindow::uploadQuickFile);
    connect(attendanceBtn, &QPushButton::clicked, this, &TeacherWindow::showAttendance);
    connect(chatToggle_, &QPushButton::clicked, this, [this] {
        publicChatEnabled_ = !publicChatEnabled_;
        chatToggle_->setText(publicChatEnabled_ ? "Общий чат: ВКЛ" : "Общий чат: ВЫКЛ");
        ws_->send(QJsonObject{{"type", "chat_control"}, {"enabled", publicChatEnabled_}});
    });
    connect(sendAllBtn, &QPushButton::clicked, this, [this] { sendChat(false); });
    connect(sendPrivateBtn, &QPushButton::clicked, this, [this] { sendChat(true); });
    connect(chatInput_, &QLineEdit::returnPressed, this, [this] { sendChat(false); });

    connect(micButton_, &QPushButton::clicked, this, [this] {
        if (!privateAudioTarget_.isEmpty()) {
            QMessageBox::information(this, "Микрофон",
                "Сначала завершите приватный аудиодиалог.");
            return;
        }
        micOn_ = !micOn_;
        if (micOn_) {
            QAudioDevice selectedMic;
            const QByteArray wanted = micCombo_->currentData().toByteArray();
            for (const auto& d : QMediaDevices::audioInputs()) {
                if (d.id() == wanted) {
                    selectedMic = d;
                    break;
                }
            }
            if (selectedMic.isNull())
                selectedMic = QMediaDevices::defaultAudioInput();

            if (!audio_->startCapture(selectedMic)) {
                micOn_ = false;
                QMessageBox::warning(this, "Микрофон", "Не удалось открыть выбранный микрофон.");
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

    connect(students_, &QListWidget::itemClicked, this, [this](QListWidgetItem* item) {
        if (!item) return;
        if (auto* row = students_->itemWidget(item)) {
            if (auto* radio = row->findChild<QRadioButton*>())
                radio->setChecked(true);
        }
    });

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

void TeacherWindow::editConnectionSettings() {
    QString error;
    if (!config_.edit(this, "Настройки подключения", &error)) {
        if (!error.isEmpty())
            QMessageBox::warning(this, "Настройки", error);
        return;
    }
    lesson_->setText(config_.lessonId);
    teacherName_->setText(config_.displayName);
    status_->setText("Настройки сохранены: " + ClientConfig::filePath());
}

void TeacherWindow::loginWithPIN() {
    const QString pin = pin_->text().trimmed();
    if (pin.size() < 4) {
        QMessageBox::information(this, "Вход", "Введите числовой пароль.");
        pin_->setFocus();
        return;
    }

    QUrl url(config_.serverUrl);
    url.setPath("/api/client/login");
    url.setQuery(QString());
    url.setFragment(QString());

    QNetworkRequest req(url);
    req.setHeader(QNetworkRequest::ContentTypeHeader, "application/json");

    const QByteArray body = QJsonDocument(QJsonObject{
        {"role", "teacher"},
        {"pin", pin},
        {"lesson_id", config_.lessonId}
    }).toJson(QJsonDocument::Compact);

    status_->setText("Проверяю пароль...");
    auto* reply = network_->post(req, body);
    connect(reply, &QNetworkReply::finished, this, [this, reply] {
        const QByteArray data = reply->readAll();
        if (reply->error() != QNetworkReply::NoError) {
            if (reply->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt() == 429)
                QMessageBox::warning(this, "Вход", "Слишком много попыток. Повторите через минуту.");
            else
                QMessageBox::warning(this, "Вход", "Неверный пароль или сервер недоступен.");
            status_->setText("Не удалось войти");
            pin_->selectAll();
            pin_->setFocus();
            reply->deleteLater();
            return;
        }

        const QJsonDocument doc = QJsonDocument::fromJson(data);
        if (!doc.isObject()) {
            QMessageBox::warning(this, "Вход", "Сервер вернул некорректный ответ.");
            reply->deleteLater();
            return;
        }
        const QJsonObject obj = doc.object();
        token_->setText(obj.value("access_token").toString());
        lesson_->setText(obj.value("lesson_id").toString(config_.lessonId));
        server_->setText(obj.value("ws_url").toString());
        
        teacherName_->setText(config_.displayName);
        pin_->clear();
        status_->setText("Пароль принят, подключаюсь...");
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
        const QString keepSelected = selectedStudentId_;
        students_->clear();
        for (auto* button : studentButtons_->buttons())
            studentButtons_->removeButton(button);
        names_.clear();

        bool selectedStillOnline = false;
        for (const auto& v : obj.value("users").toArray()) {
            const auto u = v.toObject();
            if (u.value("role").toString() != "student")
                continue;

            const QString id = u.value("id").toString();
            const QString name = u.value("name").toString();
            names_[id] = name;

            QStringList badges;
            if (u.value("hand_raised").toBool()) badges << "✋ рука";
            if (!u.value("rtc_ready").toBool()) badges << "RTC?";
            if (!u.value("watching").toBool()) badges << "не смотрит";
            if (u.value("homework_busy").toBool()) badges << "сдаёт ДЗ";
            const QString state = u.value("status").toString();
            if (state == "minimized") badges << "свёрнуто";
            else if (state == "background") badges << "фон";

            auto* item = new QListWidgetItem;
            item->setData(Qt::UserRole, id);
            item->setSizeHint(QSize(260, badges.isEmpty() ? 46 : 62));
            students_->addItem(item);

            auto* row = new QWidget;
            auto* layout = new QVBoxLayout(row);
            layout->setContentsMargins(8, 4, 8, 4);
            layout->setSpacing(1);

            auto* radio = new QRadioButton(name);
            radio->setProperty("student_id", id);
            radio->setStyleSheet("QRadioButton{font-weight:600;font-size:14px;}");
            studentButtons_->addButton(radio);
            layout->addWidget(radio);

            auto* status = new QLabel(badges.isEmpty()
                ? "● онлайн"
                : "● онлайн · " + badges.join(" · "));
            status->setStyleSheet(badges.isEmpty()
                ? "color:#22c55e;font-size:11px;"
                : "color:#f59e0b;font-size:11px;");
            layout->addWidget(status);

            students_->setItemWidget(item, row);

            connect(radio, &QRadioButton::toggled, this,
                    [this, id, name, row](bool checked) {
                if (!checked) {
                    row->setStyleSheet(QString());
                    return;
                }
                selectedStudentId_ = id;
                selectedStudentLabel_->setText("Выбран: <b>" + name.toHtmlEscaped() + "</b>");
                selectedStudentLabel_->setStyleSheet(
                    "background:rgba(138,92,255,55);color:#ffffff;padding:10px;border:1px solid rgba(0,229,255,85);border-radius:12px;");
                row->setStyleSheet(
                    "background:rgba(138,92,255,32);border:1px solid rgba(0,229,255,90);border-radius:10px;");
            });

            if (id == keepSelected) {
                selectedStillOnline = true;
                radio->setChecked(true);
            }
        }

        if (!selectedStillOnline) {
            selectedStudentId_.clear();
            selectedStudentLabel_->setText("Никто не выбран");
            selectedStudentLabel_->setStyleSheet(
                "background:#0f172a;color:#cbd5e1;padding:8px;border-radius:6px;");
        }
    } else if (type == "hand_raise" || type == "hand_down") {
        // Актуальный статус руки приходит следом в presence и обновляет строку ученика.
    } else if (type == "poll_answer") {
        if (obj.value("poll_id").toString() == activePollId_) {
            const int answer = obj.value("answer").toInt(-1);
            if (answer >= 0) {
                pollCounts_[answer] = pollCounts_.value(answer) + 1;
                ++pollResponses_;
                updatePollStatus();
            }
        }
    } else if (type == "chat_message") {
        const QString from = obj.value("from_name").toString();
        const QString text = obj.value("text").toString();
        const QString target = obj.value("target").toString();
        chatLog_->appendPlainText(QString("%1%2: %3")
            .arg(target.isEmpty() ? "" : "[личное] ", from, text));
    } else if (type == "keyframe_request") {
        codec_->requestKeyframe();
    }
}

QString TeacherWindow::selectedStudentId() const {
    return selectedStudentId_;
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

    QAudioDevice selectedMic;
    const QByteArray wantedMic = micCombo_->currentData().toByteArray();
    for (const auto& d : QMediaDevices::audioInputs()) {
        if (d.id() == wantedMic) {
            selectedMic = d;
            break;
        }
    }
    if (selectedMic.isNull())
        selectedMic = QMediaDevices::defaultAudioInput();

    micOn_ = audio_->startCapture(selectedMic);
    micButton_->setText(micOn_ ? "🎤 Микрофон: ВКЛ" : "🎤 Микрофон");
    if (!micOn_)
        QMessageBox::warning(this, "Микрофон", "Трансляция экрана запущена без звука: микрофон открыть не удалось.");

    screenTimer_->start();
    ws_->send(QJsonObject{{"type", "lecture_state"}, {"mode", "screen"}, {"active", true}});
}

void TeacherWindow::startCameraLecture() {
    stopLecture();

    QCameraDevice device;
    const QByteArray wantedCamera = cameraCombo_->currentData().toByteArray();
    for (const auto& d : QMediaDevices::videoInputs()) {
        if (d.id() == wantedCamera) {
            device = d;
            break;
        }
    }
    if (device.isNull())
        device = QMediaDevices::defaultVideoInput();

    if (device.isNull()) {
        QMessageBox::warning(this, "Камера", "Камера не найдена.");
        return;
    }

    QAudioDevice selectedMic;
    const QByteArray wantedMic = micCombo_->currentData().toByteArray();
    for (const auto& d : QMediaDevices::audioInputs()) {
        if (d.id() == wantedMic) {
            selectedMic = d;
            break;
        }
    }
    if (selectedMic.isNull())
        selectedMic = QMediaDevices::defaultAudioInput();

    micOn_ = audio_->startCapture(selectedMic);
    micButton_->setText(micOn_ ? "🎤 Микрофон: ВКЛ" : "🎤 Микрофон");
    if (!micOn_)
        QMessageBox::warning(this, "Микрофон", "Трансляция камеры запущена без звука: микрофон открыть не удалось.");

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
    {
        const QByteArray wantedMic = micCombo_->currentData().toByteArray();
        for (const auto& d : QMediaDevices::audioInputs()) {
            if (d.id() == wantedMic) {
                recordingAudio_->setDevice(d);
                break;
            }
        }
    }
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
        QCameraDevice device;
        const QByteArray wantedCamera = cameraCombo_->currentData().toByteArray();
        for (const auto& d : QMediaDevices::videoInputs()) {
            if (d.id() == wantedCamera) {
                device = d;
                break;
            }
        }
        if (device.isNull())
            device = QMediaDevices::defaultVideoInput();
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

    if (privateAudioTarget_.isEmpty()) {
        audio_->stopCapture();
        micOn_ = false;
        if (micButton_)
            micButton_->setText("🎤 Микрофон");
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


QUrl TeacherWindow::httpBaseUrl() const {
    QUrl url(server_->text());
    if (url.scheme() == "wss") url.setScheme("https");
    else if (url.scheme() == "ws") url.setScheme("http");
    url.setPath("");
    url.setQuery(QString());
    return url;
}

void TeacherWindow::startPoll() {
    bool ok = false;
    const QString question = QInputDialog::getText(
        this, "Быстрый опрос", "Вопрос:", QLineEdit::Normal, {}, &ok).trimmed();
    if (!ok || question.isEmpty()) return;

    const QString optionText = QInputDialog::getText(
        this, "Быстрый опрос",
        "Варианты через точку с запятой (например: Понятно;Не понятно):",
        QLineEdit::Normal, "Понятно;Не понятно", &ok).trimmed();
    if (!ok) return;

    const QStringList options = optionText.split(';', Qt::SkipEmptyParts);
    if (options.size() < 2) {
        QMessageBox::information(this, "Опрос", "Нужно минимум два варианта.");
        return;
    }

    activePollId_ = QUuid::createUuid().toString(QUuid::WithoutBraces);
    activePollQuestion_ = question;
    pollCounts_.clear();
    pollResponses_ = 0;

    QJsonArray arr;
    for (const QString& option : options)
        arr.append(option.trimmed());
    ws_->send(QJsonObject{
        {"type", "poll_start"},
        {"poll_id", activePollId_},
        {"question", question},
        {"options", arr}
    });
    pollStatus_->setProperty("options", options);
    updatePollStatus();
}

void TeacherWindow::updatePollStatus() {
    const QStringList options = pollStatus_->property("options").toStringList();
    QStringList parts;
    for (int i = 0; i < options.size(); ++i)
        parts << QString("%1 — %2").arg(options.at(i)).arg(pollCounts_.value(i));
    pollStatus_->setText(activePollQuestion_ + " | Ответов: " +
                         QString::number(pollResponses_) + " | " + parts.join(" · "));
}

void TeacherWindow::sendChat(bool privateMessage) {
    const QString text = chatInput_->text().trimmed();
    if (text.isEmpty()) return;
    QString target;
    if (privateMessage) {
        target = selectedStudentId();
        if (target.isEmpty()) {
            QMessageBox::information(this, "Чат", "Сначала выберите ученика.");
            return;
        }
    }
    ws_->send(QJsonObject{{"type", "chat_message"}, {"text", text}, {"target", target}});
    chatInput_->clear();
}

void TeacherWindow::uploadQuickFile() {
    const QString path = QFileDialog::getOpenFileName(this, "Отправить файл ученикам");
    if (path.isEmpty()) return;

    auto* multi = new QHttpMultiPart(QHttpMultiPart::FormDataType);
    auto* file = new QFile(path, multi);
    if (!file->open(QIODevice::ReadOnly)) {
        delete multi;
        QMessageBox::warning(this, "Файл", "Не удалось открыть файл.");
        return;
    }
    QHttpPart part;
    part.setHeader(QNetworkRequest::ContentDispositionHeader,
                   QString("form-data; name=\"file\"; filename=\"%1\"")
                       .arg(QFileInfo(path).fileName()));
    part.setBodyDevice(file);
    multi->append(part);

    QUrl url = httpBaseUrl();
    url.setPath(QString("/api/v1/classrooms/%1/quick-files").arg(lesson_->text()));
    QNetworkRequest req(url);
    req.setRawHeader("Authorization", "Bearer " + token_->text().toUtf8());
    auto* reply = network_->post(req, multi);
    multi->setParent(reply);
    connect(reply, &QNetworkReply::finished, this, [this, reply] {
        if (reply->error() == QNetworkReply::NoError)
            status_->setText("Файл отправлен ученикам");
        else
            QMessageBox::warning(this, "Файл", "Ошибка загрузки: " + reply->errorString());
        reply->deleteLater();
    });
}

void TeacherWindow::showAttendance() {
    QUrl url = httpBaseUrl();
    url.setPath(QString("/api/v1/classrooms/%1/attendance").arg(lesson_->text()));
    QNetworkRequest req(url);
    req.setRawHeader("Authorization", "Bearer " + token_->text().toUtf8());
    auto* reply = network_->get(req);
    connect(reply, &QNetworkReply::finished, this, [this, reply] {
        const QByteArray data = reply->readAll();
        if (reply->error() != QNetworkReply::NoError) {
            QMessageBox::warning(this, "Посещаемость", reply->errorString());
            reply->deleteLater();
            return;
        }
        const QJsonArray rows = QJsonDocument::fromJson(data).array();
        QString text;
        for (const auto& v : rows) {
            const auto r = v.toObject();
            const qint64 ms = qint64(r.value("online_ms").toDouble());
            const qint64 first = qint64(r.value("first_seen").toDouble());
            const qint64 last = qint64(r.value("last_seen").toDouble());
            text += QString("%1 — %2 мин.\n  вход: %3\n  последняя активность: %4\n  переподключений: %5\n\n")
                .arg(r.value("student_name").toString())
                .arg(ms / 60000)
                .arg(QDateTime::fromMSecsSinceEpoch(first).toString("dd.MM.yyyy HH:mm:ss"))
                .arg(QDateTime::fromMSecsSinceEpoch(last).toString("dd.MM.yyyy HH:mm:ss"))
                .arg(r.value("reconnects").toInt());
        }
        if (text.isEmpty()) text = "Подключений учеников пока не было.";
        QMessageBox::information(this, "Посещаемость занятия", text);
        reply->deleteLater();
    });
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
