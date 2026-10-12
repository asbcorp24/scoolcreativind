#include "StudentWindow.h"
#include "../common/WsClient.h"
#include "../common/RtcRelayClient.h"
#include "../common/AudioPipe.h"
#include "WinRemoteInput.h"

#include <QBuffer>
#include <QGuiApplication>
#include <QHBoxLayout>
#include <QImage>
#include <QJsonObject>
#include <QLabel>
#include <QLineEdit>
#include <QNetworkAccessManager>
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
      snapshotTimer_(new QTimer(this)),
      remoteScreenTimer_(new QTimer(this)),
      network_(new QNetworkAccessManager(this)),
      studentId_("student-" + QUuid::createUuid().toString(QUuid::WithoutBraces)) {

    auto* root = new QWidget;
    auto* outer = new QVBoxLayout(root);
    auto* form = new QHBoxLayout;

    wsUrl_ = new QLineEdit("wss://classroom.example.ru/ws");
    httpUrl_ = new QLineEdit("https://classroom.example.ru");
    lesson_ = new QLineEdit("101");
    name_ = new QLineEdit(qEnvironmentVariable("USERNAME", "Ученик"));
    auto* connectBtn = new QPushButton("Подключиться");

    form->addWidget(new QLabel("WS:")); form->addWidget(wsUrl_, 2);
    form->addWidget(new QLabel("HTTP:")); form->addWidget(httpUrl_, 2);
    form->addWidget(new QLabel("Занятие:")); form->addWidget(lesson_);
    form->addWidget(new QLabel("Имя:")); form->addWidget(name_);
    form->addWidget(connectBtn);
    outer->addLayout(form);

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

    snapshotTimer_->setInterval(1000);
    remoteScreenTimer_->setInterval(100);

    connect(connectBtn, &QPushButton::clicked, this, &StudentWindow::connectServer);

    connect(hand_, &QPushButton::clicked, this, [this] {
        handRaised_ = !handRaised_;
        ws_->send(QJsonObject{{"type", handRaised_ ? "hand_raise" : "hand_down"}});
        hand_->setText(handRaised_ ? "Опустить руку" : "✋ Поднять руку");
    });

    connect(ws_, &WsClient::connected, this, [this] {
        status_->setText("● Сигналинг подключён, устанавливаю WebRTC...");
        snapshotTimer_->start();
        rtc_->start();
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

    connect(rtc_, &RtcRelayClient::frameReceived, this, &StudentWindow::onRtcFrame);

    connect(rtc_, &RtcRelayClient::textReceived, this, [this](const QJsonObject& obj) {
        if (!remoteActive_ || !controlAllowed_)
            return;
        if (obj.value("type").toString() == "remote_input")
            WinRemoteInput::apply(obj);
    });

    connect(audio_, &AudioPipe::captured, this, [this](const QByteArray& pcm) {
        if (audioTalkActive_)
            rtc_->sendFrame(RtcRelayClient::StudentAudio, pcm);
    });
}

StudentWindow::~StudentWindow() {
    audio_->stopCapture();
    audio_->stopPlayback();
    rtc_->stop();
}

void StudentWindow::connectServer() {
    ws_->connectTo(QUrl(wsUrl_->text()), lesson_->text(), studentId_, "student", name_->text());
}

void StudentWindow::onMessage(const QJsonObject& obj) {
    const auto type = obj.value("type").toString();

    if (type == "lecture_state") {
        if (!obj.value("active").toBool())
            lecture_->setText("Лекция приостановлена");
        return;
    }

    if (type == "remote_request") {
        const QString mode = obj.value("mode").toString("view");
        controlAllowed_ = (mode == "control");
        setRemoteActive(true);
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

void StudentWindow::onRtcFrame(quint8 kind, const QString&, const QByteArray& payload) {
    if (kind == RtcRelayClient::LectureVideo) {
        QPixmap pix;
        if (pix.loadFromData(payload)) {
            lecture_->setPixmap(pix.scaled(
                lecture_->size(), Qt::KeepAspectRatio, Qt::SmoothTransformation));
        }
    } else if (kind == RtcRelayClient::LectureAudio ||
               kind == RtcRelayClient::TeacherPrivateAudio) {
        audio_->play(payload);
    }
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

    QByteArray body;
    QBuffer buffer(&body);
    buffer.open(QIODevice::WriteOnly);
    if (!image.save(&buffer, "JPG", 65))
        return;

    rtc_->sendFrame(RtcRelayClient::StudentVideo, body);
}

void StudentWindow::setRemoteActive(bool active) {
    remoteActive_ = active;
    if (!active)
        controlAllowed_ = false;
    remoteBanner_->setVisible(active);
}
