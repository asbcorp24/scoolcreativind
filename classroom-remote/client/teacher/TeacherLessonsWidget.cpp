#include "TeacherLessonsWidget.h"
#include "../common/LearningApi.h"

#include <QCheckBox>
#include <QDateTime>
#include <QDateTimeEdit>
#include <QFileDialog>
#include <QFileInfo>
#include <QFormLayout>
#include <QHBoxLayout>
#include <QJsonObject>
#include <QLabel>
#include <QLineEdit>
#include <QListWidget>
#include <QMessageBox>
#include <QPushButton>
#include <QSpinBox>
#include <QSplitter>
#include <QTextEdit>
#include <QVBoxLayout>

TeacherLessonsWidget::TeacherLessonsWidget(QWidget* parent)
    : QWidget(parent), api_(new LearningApi(this)) {
    auto* outer = new QVBoxLayout(this);
    auto* split = new QSplitter;

    auto* left = new QWidget;
    auto* lv = new QVBoxLayout(left);
    auto* leftButtons = new QHBoxLayout;
    auto* refreshBtn = new QPushButton("Обновить");
    auto* newBtn = new QPushButton("Новый урок");
    leftButtons->addWidget(refreshBtn);
    leftButtons->addWidget(newBtn);
    lv->addLayout(leftButtons);
    lessons_ = new QListWidget;
    lv->addWidget(lessons_, 1);

    auto* right = new QWidget;
    auto* rv = new QVBoxLayout(right);
    auto* form = new QFormLayout;
    title_ = new QLineEdit;
    description_ = new QTextEdit;
    homework_ = new QTextEdit;
    position_ = new QSpinBox;
    position_->setRange(0, 100000);
    published_ = new QCheckBox("Доступен ученикам");
    dueEnabled_ = new QCheckBox("Есть срок сдачи");
    due_ = new QDateTimeEdit(QDateTime::currentDateTime().addDays(7));
    due_->setCalendarPopup(true);
    form->addRow("Тема", title_);
    form->addRow("Описание урока", description_);
    form->addRow("Домашнее задание", homework_);
    form->addRow("Порядок", position_);
    form->addRow("", published_);
    auto* dueRow = new QWidget;
    auto* dueLayout = new QHBoxLayout(dueRow);
    dueLayout->setContentsMargins(0,0,0,0);
    dueLayout->addWidget(dueEnabled_);
    dueLayout->addWidget(due_);
    form->addRow("Срок", dueRow);
    rv->addLayout(form);

    auto* saveBtn = new QPushButton("Сохранить урок");
    rv->addWidget(saveBtn);

    rv->addWidget(new QLabel("<b>Материалы урока</b>"));
    uploadLimit_ = new QLabel("Лимит файла: 50 МБ");
    uploadLimit_->setStyleSheet("color:#94a3b8;");
    rv->addWidget(uploadLimit_);
    materials_ = new QListWidget;
    rv->addWidget(materials_);
    auto* materialButtons = new QHBoxLayout;
    auto* uploadBtn = new QPushButton("Добавить документ");
    auto* downloadMaterialBtn = new QPushButton("Скачать выбранный");
    materialButtons->addWidget(uploadBtn);
    materialButtons->addWidget(downloadMaterialBtn);
    rv->addLayout(materialButtons);

    rv->addWidget(new QLabel("<b>Сданные домашние работы</b>"));
    submissions_ = new QListWidget;
    rv->addWidget(submissions_, 1);
    auto* subButtons = new QHBoxLayout;
    auto* refreshSubsBtn = new QPushButton("Обновить сдачи");
    auto* downloadSubBtn = new QPushButton("Скачать выбранный файл");
    subButtons->addWidget(refreshSubsBtn);
    subButtons->addWidget(downloadSubBtn);
    rv->addLayout(subButtons);

    status_ = new QLabel;
    rv->addWidget(status_);

    split->addWidget(left);
    split->addWidget(right);
    split->setStretchFactor(0, 1);
    split->setStretchFactor(1, 3);
    outer->addWidget(split);

    connect(refreshBtn, &QPushButton::clicked, this, &TeacherLessonsWidget::refresh);
    connect(newBtn, &QPushButton::clicked, this, &TeacherLessonsWidget::newLesson);
    connect(saveBtn, &QPushButton::clicked, this, &TeacherLessonsWidget::saveLesson);
    connect(uploadBtn, &QPushButton::clicked, this, &TeacherLessonsWidget::uploadMaterial);
    connect(downloadMaterialBtn, &QPushButton::clicked, this, &TeacherLessonsWidget::downloadSelectedMaterial);
    connect(refreshSubsBtn, &QPushButton::clicked, this, &TeacherLessonsWidget::refreshSubmissions);
    connect(downloadSubBtn, &QPushButton::clicked, this, &TeacherLessonsWidget::downloadSelectedSubmissionFile);
    connect(lessons_, &QListWidget::currentRowChanged, this, [this] { loadSelectedLesson(); });

    connect(api_, &LearningApi::lessonsLoaded, this, [this](const QJsonArray& lessons) {
        lessonData_ = lessons;
        const qint64 keep = currentLessonId_;
        lessons_->clear();
        int selectRow = -1;
        for (int i = 0; i < lessons.size(); ++i) {
            const auto obj = lessons.at(i).toObject();
            const qint64 id = qint64(obj.value("id").toDouble());
            QString text = QString("%1. %2")
                .arg(obj.value("position").toInt())
                .arg(obj.value("title").toString());
            if (!obj.value("published").toBool())
                text += " [черновик]";
            auto* item = new QListWidgetItem(text);
            item->setData(Qt::UserRole, id);
            lessons_->addItem(item);
            if (id == keep) selectRow = i;
        }
        if (keep == -1 && lessons_->count() > 0)
            lessons_->setCurrentRow(lessons_->count() - 1);
        else if (selectRow >= 0)
            lessons_->setCurrentRow(selectRow);
        else if (lessons_->count() > 0)
            lessons_->setCurrentRow(0);
        status_->setText(QString("Уроков: %1").arg(lessons.size()));
    });

    connect(api_, &LearningApi::submissionsLoaded, this,
            [this](qint64 lessonId, const QJsonArray& rows) {
        if (lessonId != currentLessonId_) return;
        submissions_->clear();
        for (const auto& value : rows) {
            const auto sub = value.toObject();
            const qint64 submissionId = qint64(sub.value("id").toDouble());
            const QString student = sub.value("student_name").toString();
            const QString note = sub.value("note").toString();
            const qint64 ts = qint64(sub.value("submitted_at").toDouble());
            auto* header = new QListWidgetItem(QString("✓ %1 — %2%3")
                .arg(student,
                     QDateTime::fromMSecsSinceEpoch(ts).toString("dd.MM.yyyy HH:mm"),
                     note.isEmpty() ? "" : " — " + note));
            header->setFlags(header->flags() & ~Qt::ItemIsSelectable);
            submissions_->addItem(header);
            for (const auto& fv : sub.value("files").toArray()) {
                const auto file = fv.toObject();
                auto* item = new QListWidgetItem("    📎 " + file.value("original_name").toString());
                item->setData(Qt::UserRole, submissionId);
                item->setData(Qt::UserRole + 1, qint64(file.value("id").toDouble()));
                item->setData(Qt::UserRole + 2, file.value("original_name").toString());
                submissions_->addItem(item);
            }
        }
    });

    connect(api_, &LearningApi::operationSucceeded, this, [this](const QString& s) {
        status_->setText(s);
        if (currentLessonId_ > 0) refreshSubmissions();
    });
    connect(api_, &LearningApi::operationFailed, this, [this](const QString& s) {
        status_->setText("Ошибка: " + s);
    });
}

void TeacherLessonsWidget::setMaxUploadMB(int mb) {
    maxUploadMB_ = mb > 0 ? mb : 50;
    if (uploadLimit_)
        uploadLimit_->setText(QString("Лимит файла: %1 МБ").arg(maxUploadMB_));
}

void TeacherLessonsWidget::setSession(const QUrl& httpBase, const QString& classroomId, const QString& token) {
    api_->setSession(httpBase, classroomId, token);
    refresh();
}

void TeacherLessonsWidget::refresh() {
    if (api_->ready()) api_->listLessons();
}

void TeacherLessonsWidget::newLesson() {
    currentLessonId_ = 0;
    lessons_->clearSelection();
    title_->clear();
    description_->clear();
    homework_->clear();
    position_->setValue(lessonData_.size() + 1);
    published_->setChecked(false);
    dueEnabled_->setChecked(false);
    due_->setDateTime(QDateTime::currentDateTime().addDays(7));
    materials_->clear();
    submissions_->clear();
    status_->setText("Новый урок");
}

void TeacherLessonsWidget::loadSelectedLesson() {
    const int row = lessons_->currentRow();
    if (row < 0 || row >= lessonData_.size()) return;
    const auto obj = lessonData_.at(row).toObject();
    currentLessonId_ = qint64(obj.value("id").toDouble());
    title_->setText(obj.value("title").toString());
    description_->setPlainText(obj.value("description").toString());
    homework_->setPlainText(obj.value("homework_text").toString());
    position_->setValue(obj.value("position").toInt());
    published_->setChecked(obj.value("published").toBool());

    const auto dueValue = obj.value("due_at");
    if (dueValue.isDouble()) {
        dueEnabled_->setChecked(true);
        due_->setDateTime(QDateTime::fromMSecsSinceEpoch(qint64(dueValue.toDouble())));
    } else {
        dueEnabled_->setChecked(false);
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
    refreshSubmissions();
}

void TeacherLessonsWidget::saveLesson() {
    QJsonObject obj{
        {"title", title_->text().trimmed()},
        {"description", description_->toPlainText()},
        {"homework_text", homework_->toPlainText()},
        {"position", position_->value()},
        {"published", published_->isChecked()}
    };
    if (dueEnabled_->isChecked())
        obj["due_at"] = double(due_->dateTime().toMSecsSinceEpoch());
    else
        obj["due_at"] = QJsonValue::Null;

    if (obj.value("title").toString().isEmpty()) {
        QMessageBox::information(this, "Урок", "Введите тему урока.");
        return;
    }
    if (currentLessonId_ == 0) {
        currentLessonId_ = -1;
        api_->createLesson(obj);
    } else {
        api_->updateLesson(currentLessonId_, obj);
    }
}

void TeacherLessonsWidget::uploadMaterial() {
    if (currentLessonId_ == 0) {
        QMessageBox::information(this, "Материал", "Сначала сохраните урок.");
        return;
    }
    const QString path = QFileDialog::getOpenFileName(this, "Добавить материал к уроку");
    if (path.isEmpty()) return;
    const qint64 maxBytes = qint64(maxUploadMB_) * 1024 * 1024;
    if (QFileInfo(path).size() > maxBytes) {
        QMessageBox::warning(this, "Материал",
            QString("Файл превышает лимит %1 МБ.").arg(maxUploadMB_));
        return;
    }
    api_->uploadMaterial(currentLessonId_, path);
}

void TeacherLessonsWidget::downloadSelectedMaterial() {
    auto* item = materials_->currentItem();
    if (!item || currentLessonId_ == 0) return;
    const QString name = item->data(Qt::UserRole + 1).toString();
    const QString path = QFileDialog::getSaveFileName(this, "Сохранить материал", name);
    if (!path.isEmpty())
        api_->downloadMaterial(currentLessonId_, item->data(Qt::UserRole).toLongLong(), path);
}

void TeacherLessonsWidget::refreshSubmissions() {
    if (currentLessonId_ > 0) api_->listSubmissions(currentLessonId_);
}

void TeacherLessonsWidget::downloadSelectedSubmissionFile() {
    auto* item = submissions_->currentItem();
    if (!item || currentLessonId_ == 0) return;
    const qint64 submissionId = item->data(Qt::UserRole).toLongLong();
    const qint64 fileId = item->data(Qt::UserRole + 1).toLongLong();
    if (submissionId <= 0 || fileId <= 0) return;
    const QString name = item->data(Qt::UserRole + 2).toString();
    const QString path = QFileDialog::getSaveFileName(this, "Сохранить домашнюю работу", name);
    if (!path.isEmpty())
        api_->downloadSubmissionFile(currentLessonId_, submissionId, fileId, path);
}
