#include "StudentWindow.h"
#include "../common/WsClient.h"
#include "WinRemoteInput.h"

#include <QBuffer>
#include <QGuiApplication>
#include <QHBoxLayout>
#include <QJsonObject>
#include <QLabel>
#include <QLineEdit>
#include <QNetworkAccessManager>
#include <QNetworkRequest>
#include <QPushButton>
#include <QScreen>
#include <QTimer>
#include <QUuid>
#include <QVBoxLayout>
#include <QWidget>

StudentWindow::StudentWindow(QWidget* parent)
    : QMainWindow(parent),
      ws_(new WsClient(this)),
      snapshotTimer_(new QTimer(this)),
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

    lecture_ = new QLabel("Здесь отображается лекция преподавателя\n(WebRTC экран или камера)");
    lecture_->setAlignment(Qt::AlignCenter);
    lecture_->setStyleSheet("background:#020617;color:white;font-size:24px;border-radius:10px;");
    outer->addWidget(lecture_, 1);

    hand_ = new QPushButton("✋ Поднять руку");
    hand_->setMinimumHeight(48);
    outer->addWidget(hand_);

    setCentralWidget(root);
    setWindowTitle("Classroom Student");

    connect(connectBtn, &QPushButton::clicked, this, &StudentWindow::connectServer);
    connect(hand_, &QPushButton::clicked, this, [this] {
        handRaised_ = !handRaised_;
        ws_->send(QJsonObject{{"type", handRaised_ ? "hand_raise" : "hand_down"}});
        hand_->setText(handRaised_ ? "Опустить руку" : "✋ Поднять руку");
    });
    connect(ws_, &WsClient::connected, this, [this] {
        status_->setText("● Подключено к занятию");
        snapshotTimer_->start(1000);
    });
    connect(ws_, &WsClient::disconnected, this, [this] {
        status_->setText("○ Отключено");
        snapshotTimer_->stop();
    });
    connect(ws_, &WsClient::errorText, this, [this](const QString& e){ status_->setText("Ошибка: " + e); });
    connect(ws_, &WsClient::message, this, &StudentWindow::onMessage);
    connect(snapshotTimer_, &QTimer::timeout, this, &StudentWindow::uploadSnapshot);
}

void StudentWindow::connectServer() {
    ws_->connectTo(QUrl(wsUrl_->text()), lesson_->text(), studentId_, "student", name_->text());
}

void StudentWindow::onMessage(const QJsonObject& obj) {
    const auto type = obj.value("type").toString();
    if (type == "lecture_state") {
        lecture_->setText(obj.value("active").toBool()
            ? "Лекция идёт: " + obj.value("mode").toString()
            : "Лекция приостановлена");
    } else if (type == "remote_request") {
        status_->setText("Преподаватель запросил просмотр/управление");
        // Здесь запускается WebRTC screen track и DataChannel.
    } else if (type == "remote_stop") {
        status_->setText("Удалённая сессия завершена");
    } else if (type == "audio_request") {
        status_->setText("Преподаватель вызывает вас в аудиодиалог");
        // Здесь создаётся отдельный WebRTC audio track.
    } else if (type == "remote_input") {
        WinRemoteInput::apply(obj);
    } else if (type == "signal") {
        // SDP/ICE передаётся в libdatachannel/WebRTC слой.
    }
}

void StudentWindow::uploadSnapshot() {
    if (!ws_->isConnected()) return;
    QScreen* screen = QGuiApplication::primaryScreen();
    if (!screen) return;

    QPixmap pix = screen->grabWindow(0).scaled(960, 540, Qt::KeepAspectRatio, Qt::SmoothTransformation);
    QByteArray body;
    QBuffer buf(&body);
    buf.open(QIODevice::WriteOnly);
    if (!pix.save(&buf, "WEBP", 55)) {
        body.clear();
        buf.close();
        buf.open(QIODevice::WriteOnly);
        pix.save(&buf, "JPG", 60);
    }

    const QString url = QString("%1/api/v1/lessons/%2/students/%3/snapshot")
        .arg(httpUrl_->text(), lesson_->text(), studentId_);
    QNetworkRequest req(QUrl(url));
    req.setHeader(QNetworkRequest::ContentTypeHeader, "image/webp");
    network_->post(req, body);
}
