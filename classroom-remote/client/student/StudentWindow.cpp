#include "StudentWindow.h"
#include "../common/WsClient.h"
#include "../common/RtcRelayClient.h"
#include "../common/AudioPipe.h"
#include "../common/MediaCodec.h"
#include "../common/InviteLink.h"
#include "WinRemoteInput.h"

#include <QBuffer>
#include <QGuiApplication>
#include <QHBoxLayout>
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
#include <QPixmap>
#include <QPushButton>
#include <QScreen>
#include <QTimer>
#include <QUuid>
#include <QVBoxLayout>
#include <QWidget>

StudentWindow::StudentWindow(QWidget* parent)
    : QMainWindow(parent),
      ws_(new WsClient(this)),
      rtc_(new RtcRelayClient(ws_, this)),
      audio_(new AudioPipe(this)),
      codec_(new MediaCodec),
      snapshotTimer_(new QTimer(this)),
      remoteScreenTimer_(new QTimer(this)),
      network_(new QNetworkAccessManager(this)),
      studentId_("student-" + QUuid::createUuid().toString(QUuid::WithoutBraces)) {

    auto* root = new QWidget;
    auto* outer = new QVBoxLayout(root);
    invite_ = new QLineEdit;
    invite_->setPlaceholderText("Вставьте ссылку ученика");
    name_ = new QLineEdit(qEnvironmentVariable("USERNAME", "Ученик"));
    auto* inviteBtn = new QPushButton("Войти по ссылке");
    auto* advancedBtn = new QPushButton("Ручная настройка");

    auto* inviteRow = new QHBoxLayout;
    inviteRow->addWidget(new QLabel("Ссылка:"));
    inviteRow->addWidget(invite_, 3);
    inviteRow->addWidget(new QLabel("Имя:"));
    inviteRow->addWidget(name_);
    inviteRow->addWidget(inviteBtn);
    inviteRow->addWidget(advancedBtn);
    outer->addLayout(inviteRow);

    auto* advanced = new QWidget;
    auto* form = new QHBoxLayout(advanced);
    form->setContentsMargins(0, 0, 0, 0);
    wsUrl_ = new QLineEdit("ws://127.0.0.1:8080/ws");
    httpUrl_ = new QLineEdit("http://127.0.0.1:8080");
    lesson_ = new QLineEdit("101");
    token_ = new QLineEdit;
    token_->setPlaceholderText("Ключ доступа");
    token_->setEchoMode(QLineEdit::Password);
    auto* connectBtn = new QPushButton("Подключиться вручную");

    form->addWidget(new QLabel("WS:")); form->addWidget(wsUrl_, 2);
    form->addWidget(new QLabel("HTTP:")); form->addWidget(httpUrl_, 2);
    form->addWidget(new QLabel("Занятие:")); form->addWidget(lesson_);
    form->addWidget(new QLabel("Ключ:")); form->addWidget(token_);
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

    status_ = new QLabel("Не подключено");
    outer->addWidget(status_);

    remoteBanner_ = new QLabel("Преподаватель подключён к вашему экрану");
    remoteBanner_->setAlignment(Qt::AlignCenter);
    remoteBanner_->setStyleSheet("background:#b45309;color:white;padding:8px;font-weight:700;");
    remoteBanner_->hide();
    outer->addWidget(remoteBanner_);

    lecture_ = new QLabel("Ожидание трансляции преподавателя");
    lecture_->setAlignment(Qt::AlignCenter);
    lecture_->setMinimumSize(640, 360);
    lecture_->setStyleSheet("background:#020617;color:white;font-size:24px;border-radius:10px;");
    outer->addWidget(lecture_, 1);

    hand_ = new QPushButton("✋ Поднять руку");
    hand_->setMinimumHeight(48);
    outer->addWidget(hand_);

    setCentralWidget(root);
    setWindowTitle("Classroom Student");

    if (!codec_->initialize())
        status_->setText("Ошибка инициализации OpenH264/Opus");

    snapshotTimer_->setInterval(1000);
    remoteScreenTimer_->setInterval(100);

    connect(connectBtn, &QPushButton::clicked, this, &StudentWindow::connectServer);

    connect(hand_, &QPushButton::clicked, this, [this] {
        handRaised_ = !handRaised_;
        ws_->send(QJsonObject{{"type", handRaised_ ? "hand_raise" : "hand_down"}});
        hand_->setText(handRaised_ ? "Опустить руку" : "✋ Поднять руку");
    });

    connect(ws_, &WsClient::connected, this, [this] {
        status_->setText("● Сигналинг подключён, получаю ICE/TURN конфигурацию...");
        snapshotTimer_->start();
    });

    connect(ws_, &WsClient::disconnected, this, [this] {
        status_->setText("○ Отключено");
        snapshotTimer_->stop();
        remoteScreenTimer_->stop();
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
        status_->setText("Ссылка принята, подключаюсь...");
        reply->deleteLater();
        connectServer();
    });
}

void StudentWindow::connectServer() {
    ws_->connectTo(QUrl(wsUrl_->text()), lesson_->text(), studentId_, "student", name_->text(), token_->text());
}

void StudentWindow::onMessage(const QJsonObject& obj) {
    const auto type = obj.value("type").toString();

    if (type == "server_config") {
        QStringList iceServers;
        for (const auto& v : obj.value("ice_servers").toArray())
            iceServers << v.toString();
        status_->setText("● ICE/TURN получен, устанавливаю WebRTC...");
        rtc_->start("student", iceServers);
        return;
    }

    if (type == "lecture_state") {
        if (!obj.value("active").toBool())
            lecture_->setText("Лекция приостановлена");
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
