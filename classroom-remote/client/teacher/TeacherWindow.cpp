#include "TeacherWindow.h"
#include "../common/WsClient.h"

#include <QFormLayout>
#include <QHBoxLayout>
#include <QJsonArray>
#include <QJsonObject>
#include <QLabel>
#include <QLineEdit>
#include <QListWidget>
#include <QListWidgetItem>
#include <QMessageBox>
#include <QPushButton>
#include <QSplitter>
#include <QVBoxLayout>
#include <QUuid>
#include <QWidget>

TeacherWindow::TeacherWindow(QWidget* parent) : QMainWindow(parent), ws_(new WsClient(this)) {
    auto* root = new QWidget;
    auto* outer = new QVBoxLayout(root);

    auto* form = new QHBoxLayout;
    server_ = new QLineEdit("wss://classroom.example.ru/ws");
    lesson_ = new QLineEdit("101");
    teacherName_ = new QLineEdit("Преподаватель");
    auto* connectBtn = new QPushButton("Подключиться");
    form->addWidget(new QLabel("Сервер:"));
    form->addWidget(server_, 2);
    form->addWidget(new QLabel("Занятие:"));
    form->addWidget(lesson_);
    form->addWidget(new QLabel("Имя:"));
    form->addWidget(teacherName_);
    form->addWidget(connectBtn);
    outer->addLayout(form);

    status_ = new QLabel("Не подключено");
    outer->addWidget(status_);

    auto* split = new QSplitter;
    lecturePreview_ = new QLabel("Здесь будет локальный preview\nэкрана или камеры преподавателя");
    lecturePreview_->setAlignment(Qt::AlignCenter);
    lecturePreview_->setMinimumWidth(650);
    lecturePreview_->setStyleSheet("background:#111827;color:#e5e7eb;font-size:22px;border-radius:8px;");

    auto* right = new QWidget;
    auto* rv = new QVBoxLayout(right);
    rv->addWidget(new QLabel("<b>Участники</b>"));
    students_ = new QListWidget;
    rv->addWidget(students_, 1);

    auto* viewBtn = new QPushButton("Посмотреть экран");
    auto* controlBtn = new QPushButton("Запросить управление");
    auto* stopBtn = new QPushButton("Отключиться от ученика");
    auto* audioBtn = new QPushButton("Аудиодиалог");
    rv->addWidget(viewBtn);
    rv->addWidget(controlBtn);
    rv->addWidget(audioBtn);
    rv->addWidget(stopBtn);

    split->addWidget(lecturePreview_);
    split->addWidget(right);
    split->setStretchFactor(0, 4);
    split->setStretchFactor(1, 1);
    outer->addWidget(split, 1);
    setCentralWidget(root);
    setWindowTitle("Classroom Teacher");

    connect(connectBtn, &QPushButton::clicked, this, &TeacherWindow::connectServer);
    connect(viewBtn, &QPushButton::clicked, this, [this]{ sendToSelected("remote_request"); });
    connect(controlBtn, &QPushButton::clicked, this, [this]{ sendToSelected("remote_request"); });
    connect(stopBtn, &QPushButton::clicked, this, [this]{ sendToSelected("remote_stop"); });
    connect(audioBtn, &QPushButton::clicked, this, [this]{ sendToSelected("audio_request"); });

    connect(ws_, &WsClient::connected, this, [this]{ status_->setText("● Подключено"); });
    connect(ws_, &WsClient::disconnected, this, [this]{ status_->setText("○ Отключено"); });
    connect(ws_, &WsClient::errorText, this, [this](const QString& e){ status_->setText("Ошибка: " + e); });
    connect(ws_, &WsClient::message, this, &TeacherWindow::onMessage);
}

void TeacherWindow::connectServer() {
    ws_->connectTo(QUrl(server_->text()), lesson_->text(),
                   "teacher-" + QUuid::createUuid().toString(QUuid::WithoutBraces),
                   "teacher", teacherName_->text());
}

void TeacherWindow::onMessage(const QJsonObject& obj) {
    const auto type = obj.value("type").toString();
    if (type == "presence") {
        students_->clear();
        names_.clear();
        for (const auto& v : obj.value("users").toArray()) {
            const auto u = v.toObject();
            if (u.value("role").toString() != "student") continue;
            const auto id = u.value("id").toString();
            const auto name = u.value("name").toString();
            names_[id] = name;
            auto* item = new QListWidgetItem("● " + name);
            item->setData(Qt::UserRole, id);
            students_->addItem(item);
        }
    } else if (type == "hand_raise") {
        const auto id = obj.value("student_id").toString();
        for (int i=0;i<students_->count();++i) {
            auto* item = students_->item(i);
            if (item->data(Qt::UserRole).toString() == id)
                item->setText("✋ " + names_.value(id, id));
        }
    } else if (type == "signal") {
        status_->setText("Получен WebRTC signaling пакет");
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
    ws_->send(QJsonObject{{"type", type}, {"target", id}});
}
