#include "StudentLessonsWidget.h"
#include "../common/LearningApi.h"

#include <QDateTime>
#include <QFileDialog>
#include <QHBoxLayout>
#include <QJsonObject>
#include <QLabel>
#include <QListWidget>
#include <QMessageBox>
#include <QPushButton>
#include <QSplitter>
#include <QTextEdit>
#include <QVBoxLayout>

StudentLessonsWidget::StudentLessonsWidget(QWidget* parent)
    : QWidget(parent), api_(new LearningApi(this)) {
    auto* outer = new QVBoxLayout(this);
    auto* top = new QHBoxLayout;
    auto* refreshBtn = new QPushButton("Обновить уроки");
    top->addWidget(refreshBtn);
    top->addStretch();
    outer->addLayout(top);

    auto* split = new QSplitter;
    lessons_ = new QListWidget;
    split->addWidget(lessons_);

    auto* detail = new QWidget;
    auto* dv = new QVBoxLayout(detail);
    title_ = new QLabel("<b>Выберите урок</b>");
    title_->setWordWrap(true);
    description_ = new QTextEdit;
    description_->setReadOnly(true);
    homework_ = new QTextEdit;
    homework_->setReadOnly(true);
    due_ = new QLabel;
    dv->addWidget(title_);
    dv->addWidget(new QLabel("Описание урока"));
    dv->addWidget(description_);
    dv->addWidget(new QLabel("<b>Домашнее задание</b>"));
    dv->addWidget(homework_);
    dv->addWidget(due_);

    dv->addWidget(new QLabel("<b>Материалы</b>"));
    materials_ = new QListWidget;
    dv->addWidget(materials_);
    auto* downloadBtn = new QPushButton("Скачать выбранный материал");
    dv->addWidget(downloadBtn);

    dv->addWidget(new QLabel("<b>Сдать домашнюю работу</b>"));
    note_ = new QTextEdit;
    note_->setPlaceholderText("Комментарий преподавателю");
    note_->setMaximumHeight(80);
    dv->addWidget(note_);
    selectedFiles_ = new QListWidget;
    selectedFiles_->setMaximumHeight(100);
    dv->addWidget(selectedFiles_);
    auto* fileButtons = new QHBoxLayout;
    auto* addBtn = new QPushButton("Добавить файлы");
    auto* clearBtn = new QPushButton("Очистить");
    auto* submitBtn = new QPushButton("Отправить");
    fileButtons->addWidget(addBtn);
    fileButtons->addWidget(clearBtn);
    fileButtons->addWidget(submitBtn);
    dv->addLayout(fileButtons);

    dv->addWidget(new QLabel("<b>Мои отправки по этому уроку</b>"));
    mySubmissions_ = new QListWidget;
    dv->addWidget(mySubmissions_);

    status_ = new QLabel;
    dv->addWidget(status_);

    split->addWidget(detail);
    split->setStretchFactor(0, 1);
    split->setStretchFactor(1, 3);
    outer->addWidget(split);

    connect(refreshBtn, &QPushButton::clicked, this, &StudentLessonsWidget::refresh);
    connect(lessons_, &QListWidget::currentRowChanged, this, [this]{ loadSelectedLesson(); });
    connect(addBtn, &QPushButton::clicked, this, &StudentLessonsWidget::addFiles);
    connect(clearBtn, &QPushButton::clicked, this, [this] {
        files_.clear();
        selectedFiles_->clear();
    });
    connect(submitBtn, &QPushButton::clicked, this, &StudentLessonsWidget::submit);
    connect(downloadBtn, &QPushButton::clicked, this, &StudentLessonsWidget::downloadSelectedMaterial);

    connect(api_, &LearningApi::lessonsLoaded, this, [this](const QJsonArray& lessons) {
        lessonData_ = lessons;
        lessons_->clear();
        for (const auto& value : lessons) {
            const auto obj = value.toObject();
            auto* item = new QListWidgetItem(QString("%1. %2")
                .arg(obj.value("position").toInt())
                .arg(obj.value("title").toString()));
            item->setData(Qt::UserRole, qint64(obj.value("id").toDouble()));
            lessons_->addItem(item);
        }
        if (lessons_->count() > 0) lessons_->setCurrentRow(lessons_->count() - 1);
        status_->setText(QString("Доступно уроков: %1").arg(lessons.size()));
    });

    connect(api_, &LearningApi::mySubmissionsLoaded, this,
            [this](qint64 lessonId, const QJsonArray& rows) {
        if (lessonId != currentLessonId_) return;
        mySubmissions_->clear();
        for (const auto& value : rows) {
            const auto obj = value.toObject();
            const qint64 ts = qint64(obj.value("submitted_at").toDouble());
            QString text = QDateTime::fromMSecsSinceEpoch(ts).toString("dd.MM.yyyy HH:mm");
            const QString note = obj.value("note").toString();
            if (!note.isEmpty()) text += " — " + note;
            QStringList names;
            for (const auto& fv : obj.value("files").toArray())
                names << fv.toObject().value("original_name").toString();
            if (!names.isEmpty()) text += " [" + names.join(", ") + "]";
            mySubmissions_->addItem(text);
        }
    });

    connect(api_, &LearningApi::operationSucceeded, this, [this](const QString& s) {
        status_->setText(s);
        files_.clear();
        selectedFiles_->clear();
        note_->clear();
    });
    connect(api_, &LearningApi::operationFailed, this, [this](const QString& s) {
        status_->setText("Ошибка: " + s);
    });
}

void StudentLessonsWidget::setSession(const QUrl& httpBase, const QString& classroomId,
                                      const QString& token, const QString& studentId,
                                      const QString& studentName) {
    studentId_ = studentId;
    studentName_ = studentName;
    api_->setSession(httpBase, classroomId, token);
    refresh();
}

void StudentLessonsWidget::refresh() {
    if (api_->ready()) api_->listLessons();
}

void StudentLessonsWidget::loadSelectedLesson() {
    const int row = lessons_->currentRow();
    if (row < 0 || row >= lessonData_.size()) return;
    const auto obj = lessonData_.at(row).toObject();
    currentLessonId_ = qint64(obj.value("id").toDouble());
    title_->setText("<h3>" + obj.value("title").toString().toHtmlEscaped() + "</h3>");
    description_->setPlainText(obj.value("description").toString());
    homework_->setPlainText(obj.value("homework_text").toString());

    const auto dueValue = obj.value("due_at");
    if (dueValue.isDouble()) {
        due_->setText("Срок сдачи: " +
            QDateTime::fromMSecsSinceEpoch(qint64(dueValue.toDouble())).toString("dd.MM.yyyy HH:mm"));
    } else {
        due_->setText("Срок сдачи не ограничен");
    }

    materials_->clear();
    for (const auto& fv : obj.value("files").toArray()) {
        const auto file = fv.toObject();
        auto* item = new QListWidgetItem(QString("📄 %1 (%2 KB)")
            .arg(file.value("original_name").toString())
            .arg(qint64(file.value("size_bytes").toDouble()) / 1024));
        item->setData(Qt::UserRole, qint64(file.value("id").toDouble()));
        item->setData(Qt::UserRole + 1, file.value("original_name").toString());
        materials_->addItem(item);
    }

    api_->listMySubmissions(currentLessonId_, studentId_);
}

void StudentLessonsWidget::addFiles() {
    const QStringList paths = QFileDialog::getOpenFileNames(this, "Выберите файлы домашней работы");
    for (const QString& path : paths) {
        if (path.isEmpty() || files_.contains(path)) continue;
        files_ << path;
        selectedFiles_->addItem(path);
    }
}

void StudentLessonsWidget::submit() {
    if (currentLessonId_ <= 0) {
        QMessageBox::information(this, "Домашняя работа", "Сначала выберите урок.");
        return;
    }
    if (files_.isEmpty()) {
        QMessageBox::information(this, "Домашняя работа", "Добавьте хотя бы один файл.");
        return;
    }
    api_->submitHomework(currentLessonId_, studentId_, studentName_, note_->toPlainText(), files_);
}

void StudentLessonsWidget::downloadSelectedMaterial() {
    auto* item = materials_->currentItem();
    if (!item || currentLessonId_ <= 0) return;
    const QString name = item->data(Qt::UserRole + 1).toString();
    const QString savePath = QFileDialog::getSaveFileName(this, "Сохранить материал", name);
    if (!savePath.isEmpty())
        api_->downloadMaterial(currentLessonId_, item->data(Qt::UserRole).toLongLong(), savePath);
}
