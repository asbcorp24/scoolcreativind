#include "StudentWindow.h"
#include "StudentLessonsWidget.h"
#include "../common/WsClient.h"
#include "../common/RtcRelayClient.h"
#include "../common/AudioPipe.h"
#include "../common/MediaCodec.h"
#include "../common/InviteLink.h"
#include "../common/AnnotationOverlay.h"
#include "WinRemoteInput.h"

#include <QBuffer>
#include <QGuiApplication>
#include <QHBoxLayout>
#include <QApplication>
#include <QDialog>
#include <QFileInfo>
#include <QFileDialog>
#include <QFile>
#include <QListWidget>
#include <QStackedLayout>
#include <QPlainTextEdit>
#include <QImage>
#include <QJsonArray>
#include <QJsonDocument>
#include <QJsonObject>
#include <QLabel>
#include <QLineEdit>
#include <QMessageBox>
#include <QNetworkAccessManager>
#include <QNetworkReply>
#include <QNetworkRequest>
#include <QUdpSocket>
#include <QNetworkDatagram>
#include <QHostAddress>
#include <QPixmap>
#include <QPushButton>
#include <QRegularExpressionValidator>
#include <QScreen>
#include <QSettings>
#include <QTabWidget>
#include <QTimer>
#include <QUuid>
#include <QVBoxLayout>
#include <QWidget>
#include <QSplitter>
#include <QDir>
#include <QStandardPaths>
#include <QDateTime>

StudentWindow::StudentWindow(QWidget* parent)
    : QMainWindow(parent),
      ws_(new WsClient(this)),
      rtc_(new RtcRelayClient(ws_, this)),
      audio_(new AudioPipe(this)),
      codec_(new MediaCodec),
      snapshotTimer_(new QTimer(this)),
      remoteScreenTimer_(new QTimer(this)),
      statusTimer_(new QTimer(this)),
      network_(new QNetworkAccessManager(this)),
      lanDiscovery_(new QUdpSocket(this)) {

    QSettings settings("SHKI", "ClassroomRemote");
    studentId_ = settings.value("student/id").toString();
    if (studentId_.isEmpty()) {
        studentId_ = "student-" + QUuid::createUuid().toString(QUuid::WithoutBraces);
        settings.setValue("student/id", studentId_);
    }

    auto* root = new QWidget;
    auto* outer = new QVBoxLayout(root);
    config_ = ClientConfig::load(qEnvironmentVariable("USERNAME", "Ученик"));

    invite_ = new QLineEdit(this);
    invite_->hide();
    wsUrl_ = new QLineEdit(this);
    wsUrl_->hide();
    httpUrl_ = new QLineEdit(this);
    httpUrl_->hide();
    lesson_ = new QLineEdit(config_.lessonId, this);
    lesson_->hide();
    token_ = new QLineEdit(this);
    token_->hide();
    name_ = new QLineEdit(config_.displayName, this);
    name_->hide();

    loginCard_ = new QWidget;
    loginCard_->setObjectName("sciCard");
    auto* loginLayout = new QHBoxLayout(loginCard_);
    loginLayout->setContentsMargins(18, 14, 18, 14);

    auto* loginTitle = new QLabel("<b>CLASSROOM / STUDENT</b>");
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
    outer->addWidget(loginCard_);

    connect(loginBtn, &QPushButton::clicked, this, &StudentWindow::loginWithPIN);
    connect(pin_, &QLineEdit::returnPressed, this, &StudentWindow::loginWithPIN);
    connect(settingsBtn, &QPushButton::clicked, this, [this] {
        localDiscoveryResolved_ = false;
        editConnectionSettings();
    });

    status_ = new QLabel("Не подключено");
    outer->addWidget(status_);

    remoteBanner_ = new QLabel("Преподаватель подключён к вашему экрану");
    remoteBanner_->setAlignment(Qt::AlignCenter);
    remoteBanner_->setStyleSheet("background:#b45309;color:white;padding:8px;font-weight:700;");
    remoteBanner_->hide();
    outer->addWidget(remoteBanner_);

    mainTabs_ = new QTabWidget;
    auto* livePage = new QWidget;
    auto* liveLayout = new QVBoxLayout(livePage);

    auto* liveStackHost = new QWidget;
    auto* liveStack = new QStackedLayout(liveStackHost);
    liveStack->setStackingMode(QStackedLayout::StackAll);
    lecture_ = new QLabel("Ожидание трансляции преподавателя");
    lecture_->setAlignment(Qt::AlignCenter);
    lecture_->setMinimumSize(640, 360);
    lecture_->setStyleSheet("background:#0c0f16;color:#f5f7ff;font-size:24px;border:1px solid rgba(255,255,255,28);border-radius:18px;");
    annotationOverlay_ = new AnnotationOverlay;
    annotationOverlay_->setEditable(false);
    liveStack->addWidget(lecture_);
    liveStack->addWidget(annotationOverlay_);
    liveStack->setCurrentWidget(annotationOverlay_);
    annotationOverlay_->raise();
    liveLayout->addWidget(liveStackHost, 1);

    hand_ = new QPushButton("✋ Поднять руку");
    hand_->setMinimumHeight(48);
    hand_->setProperty("accent", "primary");
    liveLayout->addWidget(hand_);

    mainTabs_->addTab(livePage, "Урок сейчас");
    lessonsWidget_ = new StudentLessonsWidget;
    mainTabs_->addTab(lessonsWidget_, "Уроки и домашние работы");

    auto* screenshotPage = new QWidget;
    auto* screenshotLayout = new QVBoxLayout(screenshotPage);
    auto* screenshotSplitter = new QSplitter(Qt::Horizontal);

    screenshotTasks_ = new QListWidget;
    screenshotTasks_->setMinimumWidth(260);
    screenshotTasks_->setMaximumWidth(360);
    screenshotSplitter->addWidget(screenshotTasks_);

    auto* previewPane = new QWidget;
    auto* previewLayout = new QVBoxLayout(previewPane);
    screenshotTaskTitle_ = new QLabel("<b>Выберите задание</b>");
    screenshotTaskTitle_->setWordWrap(true);
    screenshotTaskTitle_->setStyleSheet("font-size:20px;");
    screenshotTaskMeta_ = new QLabel;
    screenshotTaskMeta_->setProperty("muted", true);
    screenshotTaskImage_ = new QLabel("Здесь будет изображение задания");
    screenshotTaskImage_->setAlignment(Qt::AlignCenter);
    screenshotTaskImage_->setMinimumSize(520, 320);
    screenshotTaskImage_->setStyleSheet(
        "background:#0b1220;color:#cbd5e1;border:1px solid #334155;border-radius:12px;");
    screenshotTaskText_ = new QLabel;
    screenshotTaskText_->setWordWrap(true);
    screenshotTaskText_->setStyleSheet("font-size:17px;padding:8px 0;");
    previewLayout->addWidget(screenshotTaskTitle_);
    previewLayout->addWidget(screenshotTaskMeta_);
    previewLayout->addWidget(screenshotTaskImage_, 1);
    previewLayout->addWidget(screenshotTaskText_);
    screenshotSplitter->addWidget(previewPane);
    screenshotSplitter->setStretchFactor(1, 1);

    screenshotLayout->addWidget(new QLabel("<b>Сохранённые скрин-задания преподавателя</b>"));
    screenshotLayout->addWidget(screenshotSplitter, 1);
    mainTabs_->addTab(screenshotPage, "Скрин-задания");

    connect(screenshotTasks_, &QListWidget::currentRowChanged,
            this, [this] { refreshScreenshotTaskPreview(); });
    loadSavedScreenshotTasks();

    auto* interactivePage = new QWidget;
    auto* interactiveLayout = new QVBoxLayout(interactivePage);
    pollQuestion_ = new QLabel("Нет активного опроса");
    pollQuestion_->setWordWrap(true);
    pollOptions_ = new QListWidget;
    pollOptions_->setMaximumHeight(160);
    pollAnswerButton_ = new QPushButton("Ответить");
    pollAnswerButton_->setEnabled(false);
    interactiveLayout->addWidget(new QLabel("<b>Быстрый опрос</b>"));
    interactiveLayout->addWidget(pollQuestion_);
    interactiveLayout->addWidget(pollOptions_);
    interactiveLayout->addWidget(pollAnswerButton_);

    chatState_ = new QLabel("Общий чат включён");
    chatLog_ = new QPlainTextEdit;
    chatLog_->setReadOnly(true);
    chatLog_->setPlaceholderText("Чат урока");
    auto* chatRow = new QHBoxLayout;
    chatInput_ = new QLineEdit;
    chatInput_->setPlaceholderText("Сообщение в общий чат...");
    auto* sendChatBtn = new QPushButton("Всем");
    auto* sendTeacherBtn = new QPushButton("Преподавателю");
    chatRow->addWidget(chatInput_, 1);
    chatRow->addWidget(sendChatBtn);
    chatRow->addWidget(sendTeacherBtn);
    interactiveLayout->addWidget(new QLabel("<b>Чат</b>"));
    interactiveLayout->addWidget(chatState_);
    interactiveLayout->addWidget(chatLog_, 1);
    interactiveLayout->addLayout(chatRow);

    interactiveLayout->addWidget(new QLabel("<b>Файлы преподавателя «на урок сейчас»</b>"));
    quickFiles_ = new QListWidget;
    auto* quickRow = new QHBoxLayout;
    auto* refreshQuickBtn = new QPushButton("Обновить");
    auto* downloadQuickBtn = new QPushButton("Скачать выбранный");
    quickRow->addWidget(refreshQuickBtn);
    quickRow->addWidget(downloadQuickBtn);
    quickRow->addStretch();
    interactiveLayout->addWidget(quickFiles_);
    interactiveLayout->addLayout(quickRow);

    mainTabs_->addTab(interactivePage, "Опрос / чат / файлы");
    outer->addWidget(mainTabs_, 1);

    setCentralWidget(root);
    setWindowTitle("Classroom Student");

    if (!codec_->initialize())
        status_->setText("Ошибка инициализации OpenH264/Opus");

    snapshotTimer_->setInterval(1000);
    remoteScreenTimer_->setInterval(100);
    statusTimer_->setInterval(5000);


    connect(hand_, &QPushButton::clicked, this, [this] {
        handRaised_ = !handRaised_;
        ws_->send(QJsonObject{{"type", handRaised_ ? "hand_raise" : "hand_down"}});
        hand_->setText(handRaised_ ? "Опустить руку" : "✋ Поднять руку");
    });

    connect(pollAnswerButton_, &QPushButton::clicked, this, [this] {
        if (activePollId_.isEmpty() || pollOptions_->currentRow() < 0)
            return;
        ws_->send(QJsonObject{
            {"type", "poll_answer"},
            {"poll_id", activePollId_},
            {"answer", pollOptions_->currentRow()}
        });
        pollAnswerButton_->setEnabled(false);
        pollQuestion_->setText(pollQuestion_->text() + " — ответ отправлен");
        if (pollDialog_)
            pollDialog_->accept();
    });

    connect(chatInput_, &QLineEdit::returnPressed, this, &StudentWindow::sendChat);
    connect(sendChatBtn, &QPushButton::clicked, this, &StudentWindow::sendChat);
    connect(sendTeacherBtn, &QPushButton::clicked, this, [this] {
        const QString text = chatInput_->text().trimmed();
        if (text.isEmpty()) return;
        ws_->send(QJsonObject{{"type", "chat_message"}, {"text", text}, {"target", "teacher"}});
        chatInput_->clear();
    });
    connect(refreshQuickBtn, &QPushButton::clicked, this, &StudentWindow::loadQuickFiles);
    connect(downloadQuickBtn, &QPushButton::clicked, this, &StudentWindow::downloadQuickFile);
    connect(statusTimer_, &QTimer::timeout, this, &StudentWindow::sendStudentStatus);
    connect(mainTabs_, &QTabWidget::currentChanged, this, [this] { sendStudentStatus(); });
    connect(qApp, &QGuiApplication::applicationStateChanged, this, [this] { sendStudentStatus(); });

    connect(ws_, &WsClient::connected, this, [this] {
        status_->setText("● Сигналинг подключён, получаю ICE/TURN конфигурацию...");
        if (loginCard_)
            loginCard_->hide();
        if (config_.localNetwork) {
            showLanConnectDialog("Подключено к преподавателю");
            QTimer::singleShot(500, this, &StudentWindow::closeLanConnectDialog);
        }
        snapshotTimer_->start();
        statusTimer_->start();
        QTimer::singleShot(250, this, &StudentWindow::sendStudentStatus);
    });

    connect(ws_, &WsClient::disconnected, this, [this] {
        status_->setText("○ Отключено");
        if (loginCard_)
            loginCard_->show();
        snapshotTimer_->stop();
        remoteScreenTimer_->stop();
        statusTimer_->stop();
        audio_->stopCapture();
        rtc_->stop();
        setRemoteActive(false);
    });

    connect(ws_, &WsClient::errorText, this, [this](const QString& e) {
        status_->setText("Ошибка: " + e);
    });

    connect(ws_, &WsClient::message, this, &StudentWindow::onMessage);
    connect(snapshotTimer_, &QTimer::timeout, this, &StudentWindow::uploadSnapshot);
    connect(remoteScreenTimer_, &QTimer::timeout, this, &StudentWindow::sendRealtimeScreen);

    connect(rtc_, &RtcRelayClient::opened, this, [this] {
        status_->setText("● Подключено к занятию / WebRTC готов");
        audio_->startPlayback();
        sendStudentStatus();
    });

    connect(rtc_, &RtcRelayClient::stateText, this, [this](const QString& s) {
        status_->setText(s);
    });

    connect(rtc_, &RtcRelayClient::videoFrameReceived, this, &StudentWindow::onRtcVideo);
    connect(rtc_, &RtcRelayClient::audioFrameReceived, this, &StudentWindow::onRtcAudio);

    connect(rtc_, &RtcRelayClient::textReceived, this, [this](const QJsonObject& obj) {
        if (!remoteActive_ || !controlAllowed_)
            return;
        if (obj.value("type").toString() == "remote_input")
            WinRemoteInput::apply(obj);
    });

    connect(audio_, &AudioPipe::captured, this, [this](const QByteArray& pcm) {
        if (!audioTalkActive_)
            return;
        const auto packets = codec_->encodeAudio(
            pcm, RtcRelayClient::RemoteAudioTrack);
        for (const auto& packet : packets)
            rtc_->sendAudio(RtcRelayClient::RemoteAudioTrack, packet, 20);
    });
}

StudentWindow::~StudentWindow() {
    audio_->stopCapture();
    audio_->stopPlayback();
    rtc_->stop();
    delete codec_;
}

void StudentWindow::openInviteUrl(const QUrl& rawUrl) {
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
        if (obj.value("role").toString() != "student") {
            QMessageBox::warning(this, "Ссылка",
                "Это ссылка преподавателя. Откройте её через ClassroomTeacher.");
            reply->deleteLater();
            return;
        }

        wsUrl_->setText(obj.value("ws_url").toString());
        httpUrl_->setText(obj.value("http_url").toString());
        lesson_->setText(obj.value("lesson_id").toString());
        token_->setText(obj.value("access_token").toString());
        lessonsWidget_->setMaxUploadMB(obj.value("max_upload_mb").toInt(50));
        status_->setText("Ссылка принята, подключаюсь...");
        reply->deleteLater();
        connectServer();
    });
}

void StudentWindow::editConnectionSettings() {
    QString error;
    if (!config_.edit(this, "Настройки подключения", &error)) {
        if (!error.isEmpty())
            QMessageBox::warning(this, "Настройки", error);
        return;
    }
    lesson_->setText(config_.lessonId);
    name_->setText(config_.displayName);
    status_->setText("Настройки сохранены: " + ClientConfig::filePath());
}

void StudentWindow::loginWithPIN() {
    if (config_.localNetwork && !localDiscoveryResolved_) {
        discoverLocalTeacher();
        return;
    }

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
        {"role", "student"},
        {"pin", pin},
        {"lesson_id", config_.lessonId}
    }).toJson(QJsonDocument::Compact);

    status_->setText("Проверяю пароль...");
    auto* reply = network_->post(req, body);
    connect(reply, &QNetworkReply::finished, this, [this, reply] {
        const QByteArray data = reply->readAll();
        if (reply->error() != QNetworkReply::NoError) {
            const int httpStatus = reply->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt();

            if (config_.localNetwork) {
                if (httpStatus == 401) {
                    showLanConnectDialog("Неверный PIN ученика. Введите PIN, который показан у преподавателя.");
                    status_->setText("Неверный PIN ученика");
                } else if (httpStatus == 429) {
                    showLanConnectDialog("Слишком много неверных попыток. Повторите через минуту.");
                    status_->setText("Слишком много попыток входа");
                } else {
                    localDiscoveryResolved_ = false;
                    showLanConnectDialog("Связь с локальным сервером потеряна. Выполняю новый поиск преподавателя...");
                    status_->setText("Локальный сервер недоступен");
                    QTimer::singleShot(500, this, &StudentWindow::discoverLocalTeacher);
                }
            } else {
                if (httpStatus == 429)
                    QMessageBox::warning(this, "Вход", "Слишком много попыток. Повторите через минуту.");
                else if (httpStatus == 401)
                    QMessageBox::warning(this, "Вход", "Неверный пароль.");
                else
                    QMessageBox::warning(this, "Вход", "Сервер недоступен.");
                status_->setText("Не удалось войти");
            }

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
        wsUrl_->setText(obj.value("ws_url").toString());
        httpUrl_->setText(obj.value("http_url").toString());
        name_->setText(config_.displayName);
        pin_->clear();
        status_->setText("Пароль принят, подключаюсь...");
        if (config_.localNetwork)
            showLanConnectDialog("PIN принят. Подключаюсь к занятию...");
        reply->deleteLater();
        connectServer();
    });
}

void StudentWindow::connectServer() {
    lessonsWidget_->setSession(QUrl(httpUrl_->text()), lesson_->text(), token_->text(),
                               studentId_, name_->text());
    ws_->connectTo(QUrl(wsUrl_->text()), lesson_->text(), studentId_,
                   "student", name_->text(), token_->text());
}

void StudentWindow::onMessage(const QJsonObject& obj) {
    const auto type = obj.value("type").toString();

    if (type == "server_config") {
        lessonsWidget_->setMaxUploadMB(obj.value("max_upload_mb").toInt(50));
        QStringList iceServers;
        for (const auto& v : obj.value("ice_servers").toArray())
            iceServers << v.toString();
        status_->setText("● ICE/TURN получен, устанавливаю WebRTC...");
        rtc_->start("student", iceServers);
        loadQuickFiles();
        loadServerScreenshotTasks();
        return;
    }

    if (type == "lecture_state") {
        if (!obj.value("active").toBool()) {
            lecture_->setText("Лекция завершена. Материалы урока обновляются...");
            lessonsWidget_->refresh();
        }
        return;
    }

    if (type == "annotation" || type == "annotation_clear") {
        annotationOverlay_->applyRemote(obj);
        annotationOverlay_->raise();
        return;
    }

    if (type == "attention") {
        const bool active = obj.value("active").toBool();
        const QString text = obj.value("text").toString().trimmed();
        if (!active) {
            if (attentionDialog_) {
                attentionDialog_->close();
                attentionDialog_->deleteLater();
                attentionDialog_ = nullptr;
            }
            return;
        }

        if (attentionDialog_) {
            attentionDialog_->close();
            attentionDialog_->deleteLater();
        }
        attentionDialog_ = new QDialog(this);
        attentionDialog_->setWindowFlags(Qt::Dialog | Qt::FramelessWindowHint | Qt::WindowStaysOnTopHint);
        attentionDialog_->setModal(false);
        attentionDialog_->setStyleSheet(
            "QDialog{background:#07111f;} QLabel{color:white;} QPushButton{font-size:20px;padding:14px 28px;}");
        auto* layout = new QVBoxLayout(attentionDialog_);
        layout->setContentsMargins(60,60,60,60);
        auto* title = new QLabel("⚠ ВНИМАНИЕ");
        title->setAlignment(Qt::AlignCenter);
        title->setStyleSheet("font-size:42px;font-weight:800;color:#fbbf24;");
        auto* message = new QLabel(text.isEmpty() ? "Смотрим на экран преподавателя" : text);
        message->setAlignment(Qt::AlignCenter);
        message->setWordWrap(true);
        message->setStyleSheet("font-size:34px;font-weight:650;");
        auto* okBtn = new QPushButton("Понятно");
        okBtn->setProperty("accent","primary");
        layout->addStretch();
        layout->addWidget(title);
        layout->addSpacing(30);
        layout->addWidget(message);
        layout->addSpacing(30);
        layout->addWidget(okBtn, 0, Qt::AlignCenter);
        layout->addStretch();
        connect(okBtn, &QPushButton::clicked, attentionDialog_, &QDialog::accept);
        connect(attentionDialog_, &QDialog::finished, this, [this] {
            if (attentionDialog_) {
                attentionDialog_->deleteLater();
                attentionDialog_ = nullptr;
            }
        });
        attentionDialog_->showFullScreen();
        return;
    }

    if (type == "poll_start") {
        activePollId_ = obj.value("poll_id").toString();
        const QString question = obj.value("question").toString();
        const QJsonArray options = obj.value("options").toArray();
        const bool fullscreen = obj.value("fullscreen").toBool(false);

        pollQuestion_->setText(question);
        pollOptions_->clear();
        for (const auto& v : options)
            pollOptions_->addItem(v.toString());
        pollAnswerButton_->setEnabled(pollOptions_->count() > 0);
        mainTabs_->setCurrentIndex(3);

        if (fullscreen) {
            if (pollDialog_) {
                pollDialog_->close();
                pollDialog_->deleteLater();
                pollDialog_ = nullptr;
            }

            pollDialog_ = new QDialog(this);
            pollDialog_->setModal(false);
            pollDialog_->setWindowFlags(
                Qt::Dialog | Qt::FramelessWindowHint | Qt::WindowStaysOnTopHint);
            pollDialog_->setStyleSheet(
                "QDialog{background:#06070b;}"
                "QLabel{color:#f5f7ff;}"
                "QListWidget{font-size:24px;padding:12px;background:#0d1017;"
                "border:1px solid rgba(255,255,255,28);border-radius:18px;}"
                "QListWidget::item{padding:18px 22px;margin:5px;border-radius:14px;}"
                "QListWidget::item:selected{background:#8a5cff;color:white;}"
                "QPushButton{font-size:22px;font-weight:800;min-height:58px;padding:10px 28px;}");

            auto* layout = new QVBoxLayout(pollDialog_);
            layout->setContentsMargins(80, 60, 80, 60);
            layout->setSpacing(22);

            auto* eyebrow = new QLabel("CLASSROOM / ВОПРОС ПРЕПОДАВАТЕЛЯ");
            eyebrow->setAlignment(Qt::AlignCenter);
            eyebrow->setStyleSheet(
                "font-size:14px;font-weight:800;letter-spacing:2px;color:#00e5ff;");

            auto* questionLabel = new QLabel(question);
            questionLabel->setAlignment(Qt::AlignCenter);
            questionLabel->setWordWrap(true);
            questionLabel->setStyleSheet("font-size:38px;font-weight:850;padding:20px;");

            auto* popupOptions = new QListWidget;
            popupOptions->setSelectionMode(QAbstractItemView::SingleSelection);
            popupOptions->setMinimumHeight(220);
            popupOptions->setMaximumWidth(900);
            for (const auto& v : options)
                popupOptions->addItem(v.toString());

            auto* answerButton = new QPushButton("ОТВЕТИТЬ");
            answerButton->setProperty("accent", "primary");
            answerButton->setEnabled(false);
            answerButton->setMinimumWidth(260);

            layout->addStretch();
            layout->addWidget(eyebrow);
            layout->addWidget(questionLabel);
            layout->addWidget(popupOptions, 0, Qt::AlignHCenter);
            layout->addWidget(answerButton, 0, Qt::AlignHCenter);
            layout->addStretch();

            connect(popupOptions, &QListWidget::currentRowChanged,
                    answerButton, [answerButton](int row) {
                answerButton->setEnabled(row >= 0);
            });

            connect(popupOptions, &QListWidget::itemDoubleClicked,
                    answerButton, [answerButton](QListWidgetItem*) {
                answerButton->click();
            });

            connect(answerButton, &QPushButton::clicked, this,
                    [this, popupOptions, question] {
                const int row = popupOptions->currentRow();
                if (row < 0 || activePollId_.isEmpty())
                    return;

                ws_->send(QJsonObject{
                    {"type", "poll_answer"},
                    {"poll_id", activePollId_},
                    {"answer", row}
                });

                pollOptions_->setCurrentRow(row);
                pollAnswerButton_->setEnabled(false);
                pollQuestion_->setText(question + " — ответ отправлен");

                if (pollDialog_)
                    pollDialog_->accept();
            });

            connect(pollDialog_, &QDialog::finished, this, [this] {
                if (pollDialog_) {
                    pollDialog_->deleteLater();
                    pollDialog_ = nullptr;
                }
            });

            pollDialog_->showFullScreen();
            pollDialog_->raise();
            pollDialog_->activateWindow();
            QApplication::beep();
        }
        return;
    }

    if (type == "chat_control") {
        publicChatEnabled_ = obj.value("enabled").toBool();
        chatState_->setText(publicChatEnabled_
            ? "Общий чат включён"
            : "Общий чат отключён. Личные сообщения преподавателю доступны.");
        chatInput_->setPlaceholderText(publicChatEnabled_
            ? "Сообщение в общий чат..."
            : "Общий чат выключен — используйте «Преподавателю»");
        return;
    }

    if (type == "chat_message") {
        const QString from = obj.value("from_name").toString();
        const QString text = obj.value("text").toString();
        const QString target = obj.value("target").toString();
        chatLog_->appendPlainText(QString("%1%2: %3")
            .arg(target.isEmpty() ? "" : "[личное] ", from, text));
        return;
    }

    if (type == "screenshot_task") {
        showScreenshotTask(obj);
        return;
    }

    if (type == "quick_file") {
        loadQuickFiles();
        mainTabs_->setCurrentIndex(3);
        status_->setText("Преподаватель отправил файл: " + obj.value("name").toString());
        return;
    }

    if (type == "remote_request") {
        const QString mode = obj.value("mode").toString("view");
        controlAllowed_ = (mode == "control");
        setRemoteActive(true);
        codec_->requestKeyframe();
        remoteScreenTimer_->start();
        remoteBanner_->setText(controlAllowed_
            ? "Преподаватель подключён к управлению вашим компьютером"
            : "Преподаватель просматривает ваш экран");
        status_->setText(controlAllowed_
            ? "Удалённое управление активно"
            : "Преподаватель просматривает ваш экран");
        return;
    }

    if (type == "remote_stop") {
        remoteScreenTimer_->stop();
        audioTalkActive_ = false;
        audio_->stopCapture();
        controlAllowed_ = false;
        setRemoteActive(false);
        status_->setText("Удалённая сессия завершена");
        return;
    }

    if (type == "audio_request") {
        audioTalkActive_ = audio_->startCapture();
        audio_->startPlayback();
        status_->setText(audioTalkActive_
            ? "Приватный аудиодиалог с преподавателем активен"
            : "Не удалось открыть микрофон");
        return;
    }

    if (type == "audio_stop") {
        audioTalkActive_ = false;
        audio_->stopCapture();
        status_->setText("Аудиодиалог завершён");
        return;
    }
}

void StudentWindow::onRtcVideo(const QString& trackId, const QByteArray& h264) {
    if (trackId != RtcRelayClient::LectureVideoTrack)
        return;

    const QImage image = codec_->decodeVideo(h264);
    if (!image.isNull()) {
        lecture_->setPixmap(QPixmap::fromImage(image).scaled(
            lecture_->size(), Qt::KeepAspectRatio, Qt::SmoothTransformation));
    }
}

void StudentWindow::onRtcAudio(const QString& trackId, const QByteArray& opus) {
    if (trackId != RtcRelayClient::LectureAudioTrack &&
        trackId != RtcRelayClient::PrivateAudioTrack)
        return;

    const QByteArray pcm = codec_->decodeAudio(opus, trackId);
    if (!pcm.isEmpty())
        audio_->play(pcm);
}

void StudentWindow::sendStudentStatus() {
    if (!ws_->isConnected())
        return;

    QString state = "online";
    if (isMinimized())
        state = "minimized";
    else if (QGuiApplication::applicationState() != Qt::ApplicationActive)
        state = "background";

    const bool homeworkBusy = mainTabs_ && mainTabs_->currentIndex() == 1;
    const bool watching = mainTabs_ && mainTabs_->currentIndex() == 0 &&
                          state == "online";

    ws_->send(QJsonObject{
        {"type", "student_status"},
        {"status", state},
        {"watching", watching},
        {"homework_busy", homeworkBusy},
        {"rtc_ready", rtc_->isOpen()}
    });
}

void StudentWindow::sendChat() {
    const QString text = chatInput_->text().trimmed();
    if (text.isEmpty() || !publicChatEnabled_)
        return;
    ws_->send(QJsonObject{{"type", "chat_message"}, {"text", text}});
    chatInput_->clear();
}

void StudentWindow::showLanConnectDialog(const QString& text) {
    if (!lanConnectDialog_) {
        lanConnectDialog_ = new QDialog(this);
        lanConnectDialog_->setModal(false);
        lanConnectDialog_->setWindowTitle("Подключение к преподавателю");
        lanConnectDialog_->setFixedSize(420, 150);
        auto* layout = new QVBoxLayout(lanConnectDialog_);
        auto* title = new QLabel("<b>Локальная сеть</b>");
        title->setAlignment(Qt::AlignCenter);
        title->setStyleSheet("font-size:20px;");
        lanConnectStatus_ = new QLabel;
        lanConnectStatus_->setAlignment(Qt::AlignCenter);
        lanConnectStatus_->setWordWrap(true);
        lanConnectStatus_->setStyleSheet("font-size:15px;padding:10px;");
        layout->addWidget(title);
        layout->addWidget(lanConnectStatus_, 1);
    }
    if (lanConnectStatus_)
        lanConnectStatus_->setText(text);
    lanConnectDialog_->show();
    lanConnectDialog_->raise();
    lanConnectDialog_->activateWindow();
}

void StudentWindow::closeLanConnectDialog() {
    if (!lanConnectDialog_)
        return;
    lanConnectDialog_->hide();
}

void StudentWindow::verifyLocalServerAndLogin() {
    ++lanHealthAttempts_;
    showLanConnectDialog(QString("Преподаватель найден. Проверяю локальный сервер... (%1/5)")
                         .arg(lanHealthAttempts_));

    QUrl health(config_.serverUrl);
    health.setPath("/health");
    health.setQuery(QString());
    health.setFragment(QString());

    auto* reply = network_->get(QNetworkRequest(health));
    auto* timeout = new QTimer(reply);
    timeout->setSingleShot(true);
    timeout->setInterval(1800);
    connect(timeout, &QTimer::timeout, reply, [reply] {
        if (reply->isRunning())
            reply->abort();
    });
    timeout->start();

    connect(reply, &QNetworkReply::finished, this, [this, reply] {
        const bool ok = reply->error() == QNetworkReply::NoError &&
                        reply->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt() == 200;
        reply->deleteLater();

        if (!ok) {
            if (lanHealthAttempts_ >= 5) {
                localDiscoveryResolved_ = false;
                showLanConnectDialog(
                    "Сервер преподавателя найден, но не отвечает после 5 попыток.\n"
                    "Проверьте, что локальный сервер действительно запущен и Windows Firewall "
                    "разрешает ClassroomTeacher/classroom-server в частной сети.\n"
                    "Закройте это окно и нажмите «Войти» ещё раз для новой попытки.");
                status_->setText("Локальный сервер найден, но не отвечает");
                return;
            }

            showLanConnectDialog(QString(
                "Сервер преподавателя найден, но ещё не готов. Повтор через секунду... (%1/5)")
                .arg(lanHealthAttempts_));
            QTimer::singleShot(1000, this, [this] {
                if (config_.localNetwork && localDiscoveryResolved_)
                    verifyLocalServerAndLogin();
            });
            return;
        }

        lanHealthAttempts_ = 0;
        showLanConnectDialog("Локальный сервер готов. Проверяю PIN...");
        loginWithPIN();
    });
}

void StudentWindow::discoverLocalTeacher() {
    if (!lanDiscovery_)
        return;

    localDiscoveryResolved_ = false;
    lanHealthAttempts_ = 0;
    showLanConnectDialog("Ищу преподавателя в локальной сети...");

    lanDiscovery_->abort();
    lanDiscovery_->bind(QHostAddress::AnyIPv4, 0,
                        QUdpSocket::ShareAddress | QUdpSocket::ReuseAddressHint);

    disconnect(lanDiscovery_, nullptr, this, nullptr);
    connect(lanDiscovery_, &QUdpSocket::readyRead, this, [this] {
        while (lanDiscovery_->hasPendingDatagrams()) {
            const QNetworkDatagram datagram = lanDiscovery_->receiveDatagram();
            const QByteArray data = datagram.data();
            if (!data.startsWith("SHKI_CLASSROOM|"))
                continue;

            const QList<QByteArray> parts = data.split('|');
            if (parts.size() < 4)
                continue;

            QString host = datagram.senderAddress().toString().trimmed();
            if (host.isEmpty() || host == "0.0.0.0")
                host = QString::fromUtf8(parts[1]).trimmed();
            const QString port = QString::fromUtf8(parts[2]).trimmed();
            const QString lesson = QString::fromUtf8(parts[3]).trimmed();
            if (host.isEmpty() || port.isEmpty())
                continue;

            config_.serverUrl = QString("http://%1:%2").arg(host, port);
            if (!lesson.isEmpty())
                config_.lessonId = lesson;
            lesson_->setText(config_.lessonId);
            localDiscoveryResolved_ = true;
            status_->setText("Найден преподаватель: " + config_.serverUrl);
            verifyLocalServerAndLogin();
            return;
        }
    });

    const QByteArray request("SHKI_DISCOVER");
    lanDiscovery_->writeDatagram(request, QHostAddress::Broadcast, 45454);
    status_->setText("Ищу преподавателя в локальной сети...");

    QTimer::singleShot(2200, this, [this] {
        if (localDiscoveryResolved_)
            return;

        showLanConnectDialog(
            "Преподаватель не найден автоматически.\n"
            "Проверьте, что Teacher запустил «Локальный сервер» и Windows Firewall разрешает частную сеть.");
        status_->setText("Преподаватель в локальной сети не найден");
        localDiscoveryResolved_ = false;
    });
}

void StudentWindow::showScreenshotTask(const QJsonObject& obj) {
    const qint64 taskId = qint64(obj.value("id").toDouble());
    const qint64 fileId = qint64(obj.value("file_id").toDouble());
    const qint64 createdAt = qint64(obj.value("created_at").toDouble());
    const QString taskText = obj.value("text").toString().trimmed();
    const QString titleText = obj.value("title").toString("Задание преподавателя");
    const QString originalName = obj.value("name").toString("task.png");
    if (fileId <= 0)
        return;
    if (taskId > 0 && hasSavedScreenshotTask(taskId))
        return;

    QNetworkRequest req(quickFileUrl(fileId));
    req.setRawHeader("Authorization", "Bearer " + token_->text().toUtf8());
    auto* reply = network_->get(req);
    connect(reply, &QNetworkReply::finished, this,
            [this, reply, taskId, createdAt, taskText, titleText, originalName] {
        const QByteArray data = reply->readAll();
        if (reply->error() != QNetworkReply::NoError) {
            status_->setText("Не удалось загрузить скрин-задание: " + reply->errorString());
            reply->deleteLater();
            return;
        }

        saveScreenshotTask(taskId, titleText, taskText, data, originalName, createdAt);
        loadSavedScreenshotTasks();
        if (screenshotTasks_ && screenshotTasks_->count() > 0)
            screenshotTasks_->setCurrentRow(0);

        if (mainTabs_) {
            for (int i = 0; i < mainTabs_->count(); ++i) {
                if (mainTabs_->tabText(i) == "Скрин-задания") {
                    mainTabs_->setCurrentIndex(i);
                    break;
                }
            }
        }
        status_->setText("Новое скрин-задание сохранено");
        reply->deleteLater();
    });
}

QString StudentWindow::screenshotTasksDir() const {
    QString base = QStandardPaths::writableLocation(QStandardPaths::AppDataLocation);
    if (base.isEmpty())
        base = QDir::homePath() + "/.shki-classroom";
    return QDir(base).filePath("screenshot-tasks");
}

QString StudentWindow::screenshotTasksIndexPath() const {
    return QDir(screenshotTasksDir()).filePath("index.json");
}

void StudentWindow::saveScreenshotTask(qint64 taskId, const QString& title, const QString& text,
                                       const QByteArray& imageData, const QString& originalName,
                                       qint64 createdAt) {
    QDir dir;
    dir.mkpath(screenshotTasksDir());

    QString ext = QFileInfo(originalName).suffix().toLower();
    if (ext.isEmpty() || ext.size() > 8)
        ext = "png";

    const qint64 ts = createdAt > 0 ? createdAt : QDateTime::currentMSecsSinceEpoch();
    const QString fileName = QString("%1.%2").arg(ts).arg(ext);
    const QString imagePath = QDir(screenshotTasksDir()).filePath(fileName);

    QFile imageFile(imagePath);
    if (!imageFile.open(QIODevice::WriteOnly) || imageFile.write(imageData) != imageData.size()) {
        status_->setText("Не удалось сохранить изображение задания");
        return;
    }
    imageFile.close();

    QJsonArray items;
    QFile indexFile(screenshotTasksIndexPath());
    if (indexFile.open(QIODevice::ReadOnly)) {
        const QJsonDocument doc = QJsonDocument::fromJson(indexFile.readAll());
        if (doc.isArray())
            items = doc.array();
        indexFile.close();
    }

    QJsonObject item{
        {"server_task_id", double(taskId)},
        {"title", title},
        {"text", text},
        {"file", fileName},
        {"original_name", originalName},
        {"received_at", double(ts)},
        {"lesson_id", lesson_ ? lesson_->text() : config_.lessonId}
    };
    items.prepend(item);

    if (indexFile.open(QIODevice::WriteOnly | QIODevice::Truncate)) {
        indexFile.write(QJsonDocument(items).toJson(QJsonDocument::Indented));
        indexFile.close();
    }
}

bool StudentWindow::hasSavedScreenshotTask(qint64 taskId) const {
    if (taskId <= 0)
        return false;

    QFile indexFile(screenshotTasksIndexPath());
    if (!indexFile.open(QIODevice::ReadOnly))
        return false;
    const QJsonDocument doc = QJsonDocument::fromJson(indexFile.readAll());
    indexFile.close();
    if (!doc.isArray())
        return false;

    for (const QJsonValue& value : doc.array()) {
        if (qint64(value.toObject().value("server_task_id").toDouble()) == taskId)
            return true;
    }
    return false;
}

void StudentWindow::loadServerScreenshotTasks() {
    if (httpUrl_->text().trimmed().isEmpty() || token_->text().trimmed().isEmpty())
        return;

    QUrl url(httpUrl_->text());
    url.setPath(QString("/api/v1/classrooms/%1/screenshot-tasks").arg(lesson_->text()));
    url.setQuery(QString());
    url.setFragment(QString());

    QNetworkRequest req(url);
    req.setRawHeader("Authorization", "Bearer " + token_->text().toUtf8());
    auto* reply = network_->get(req);
    connect(reply, &QNetworkReply::finished, this, [this, reply] {
        const QByteArray body = reply->readAll();
        if (reply->error() != QNetworkReply::NoError) {
            reply->deleteLater();
            return;
        }

        const QJsonDocument doc = QJsonDocument::fromJson(body);
        if (!doc.isArray()) {
            reply->deleteLater();
            return;
        }

        // Сервер отдаёт новые задания первыми. Загружаем от старых к новым,
        // чтобы локальный список сохранил естественный порядок поступления.
        const QJsonArray items = doc.array();
        for (int i = items.size() - 1; i >= 0; --i) {
            const QJsonObject item = items.at(i).toObject();
            const qint64 taskId = qint64(item.value("id").toDouble());
            if (taskId > 0 && hasSavedScreenshotTask(taskId))
                continue;
            showScreenshotTask(item);
        }
        reply->deleteLater();
    });
}

void StudentWindow::loadSavedScreenshotTasks() {
    if (!screenshotTasks_)
        return;

    screenshotTasks_->clear();
    QFile indexFile(screenshotTasksIndexPath());
    if (!indexFile.open(QIODevice::ReadOnly)) {
        refreshScreenshotTaskPreview();
        return;
    }

    const QJsonDocument doc = QJsonDocument::fromJson(indexFile.readAll());
    indexFile.close();
    if (!doc.isArray()) {
        refreshScreenshotTaskPreview();
        return;
    }

    const QJsonArray items = doc.array();
    for (const QJsonValue& value : items) {
        const QJsonObject item = value.toObject();
        const QString file = item.value("file").toString();
        if (file.isEmpty() || !QFileInfo::exists(QDir(screenshotTasksDir()).filePath(file)))
            continue;

        const qint64 ts = qint64(item.value("received_at").toDouble());
        const QString when = QDateTime::fromMSecsSinceEpoch(ts).toString("dd.MM.yyyy HH:mm");
        auto* row = new QListWidgetItem(
            QString("🖼 %1\n%2")
                .arg(item.value("title").toString("Задание"), when));
        row->setData(Qt::UserRole,
                     QString::fromUtf8(QJsonDocument(item).toJson(QJsonDocument::Compact)));
        screenshotTasks_->addItem(row);
    }

    if (screenshotTasks_->count() > 0)
        screenshotTasks_->setCurrentRow(0);
    else
        refreshScreenshotTaskPreview();
}

void StudentWindow::refreshScreenshotTaskPreview() {
    if (!screenshotTaskImage_ || !screenshotTaskTitle_ || !screenshotTaskText_ || !screenshotTaskMeta_)
        return;

    auto* row = screenshotTasks_ ? screenshotTasks_->currentItem() : nullptr;
    if (!row) {
        screenshotTaskTitle_->setText("<b>Нет сохранённых заданий</b>");
        screenshotTaskMeta_->clear();
        screenshotTaskText_->clear();
        screenshotTaskImage_->setPixmap(QPixmap());
        screenshotTaskImage_->setText("Скрин-задания будут сохраняться здесь");
        return;
    }

    const QJsonObject item = QJsonDocument::fromJson(
        row->data(Qt::UserRole).toString().toUtf8()).object();
    const QString imagePath = QDir(screenshotTasksDir()).filePath(item.value("file").toString());
    QPixmap pix(imagePath);

    screenshotTaskTitle_->setText("<b>" + item.value("title").toString("Задание").toHtmlEscaped() + "</b>");
    const qint64 ts = qint64(item.value("received_at").toDouble());
    const QString when = QDateTime::fromMSecsSinceEpoch(ts).toString("dd.MM.yyyy HH:mm");
    screenshotTaskMeta_->setText(
        QString("Получено: %1 · Урок: %2")
            .arg(when, item.value("lesson_id").toString()));
    screenshotTaskText_->setText(item.value("text").toString());

    screenshotTaskImage_->setText(QString());
    if (!pix.isNull())
        screenshotTaskImage_->setPixmap(pix.scaled(
            screenshotTaskImage_->size(), Qt::KeepAspectRatio, Qt::SmoothTransformation));
    else
        screenshotTaskImage_->setText("Не удалось открыть сохранённое изображение");
}


QUrl StudentWindow::quickFileUrl(qint64 fileId) const {
    QUrl url(httpUrl_->text());
    if (fileId > 0)
        url.setPath(QString("/api/v1/classrooms/%1/quick-files/%2").arg(lesson_->text()).arg(fileId));
    else
        url.setPath(QString("/api/v1/classrooms/%1/quick-files").arg(lesson_->text()));
    url.setQuery(QString());
    return url;
}

void StudentWindow::loadQuickFiles() {
    if (lesson_->text().isEmpty() || token_->text().isEmpty())
        return;
    QNetworkRequest req(quickFileUrl());
    req.setRawHeader("Authorization", "Bearer " + token_->text().toUtf8());
    auto* reply = network_->get(req);
    connect(reply, &QNetworkReply::finished, this, [this, reply] {
        const QByteArray data = reply->readAll();
        if (reply->error() != QNetworkReply::NoError) {
            reply->deleteLater();
            return;
        }
        const QJsonArray rows = QJsonDocument::fromJson(data).array();
        quickFiles_->clear();
        for (const auto& v : rows) {
            const auto f = v.toObject();
            auto* item = new QListWidgetItem(QString("📎 %1 (%2 KB)")
                .arg(f.value("original_name").toString())
                .arg(qint64(f.value("size_bytes").toDouble()) / 1024));
            item->setData(Qt::UserRole, qint64(f.value("id").toDouble()));
            item->setData(Qt::UserRole + 1, f.value("original_name").toString());
            quickFiles_->addItem(item);
        }
        reply->deleteLater();
    });
}

void StudentWindow::downloadQuickFile() {
    auto* item = quickFiles_->currentItem();
    if (!item) return;
    const QString name = item->data(Qt::UserRole + 1).toString();
    const QString path = QFileDialog::getSaveFileName(this, "Сохранить файл", name);
    if (path.isEmpty()) return;

    QNetworkRequest req(quickFileUrl(item->data(Qt::UserRole).toLongLong()));
    req.setRawHeader("Authorization", "Bearer " + token_->text().toUtf8());
    auto* reply = network_->get(req);
    connect(reply, &QNetworkReply::finished, this, [this, reply, path] {
        const QByteArray data = reply->readAll();
        if (reply->error() != QNetworkReply::NoError) {
            QMessageBox::warning(this, "Файл", reply->errorString());
            reply->deleteLater();
            return;
        }
        QFile file(path);
        if (!file.open(QIODevice::WriteOnly) || file.write(data) != data.size())
            QMessageBox::warning(this, "Файл", "Не удалось сохранить файл.");
        else
            status_->setText("Файл сохранён: " + path);
        reply->deleteLater();
    });
}

void StudentWindow::uploadSnapshot() {
    if (!ws_->isConnected())
        return;

    QScreen* screen = QGuiApplication::primaryScreen();
    if (!screen)
        return;

    const QPixmap pix = screen->grabWindow(0).scaled(
        960, 540, Qt::KeepAspectRatio, Qt::SmoothTransformation);

    QByteArray body;
    QBuffer buf(&body);
    buf.open(QIODevice::WriteOnly);
    pix.save(&buf, "JPG", 55);

    const QString url = QString("%1/api/v1/lessons/%2/students/%3/snapshot")
        .arg(httpUrl_->text(), lesson_->text(), studentId_);
    QNetworkRequest req{QUrl(url)};
    req.setHeader(QNetworkRequest::ContentTypeHeader, "image/jpeg");
    req.setRawHeader("Authorization", "Bearer " + token_->text().toUtf8());
    network_->post(req, body);
}

void StudentWindow::sendRealtimeScreen() {
    if (!remoteActive_ || !rtc_->isOpen())
        return;

    auto* screen = QGuiApplication::primaryScreen();
    if (!screen)
        return;

    const QImage image = screen->grabWindow(0).toImage().scaled(
        1280, 720, Qt::KeepAspectRatio, Qt::SmoothTransformation);

    const QByteArray h264 = codec_->encodeVideo(image);
    if (!h264.isEmpty())
        rtc_->sendVideo(RtcRelayClient::RemoteVideoTrack, h264, 100);
}

void StudentWindow::setRemoteActive(bool active) {
    remoteActive_ = active;
    if (!active)
        controlAllowed_ = false;
    remoteBanner_->setVisible(active);
}
