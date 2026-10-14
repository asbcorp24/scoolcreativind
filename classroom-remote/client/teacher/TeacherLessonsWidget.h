#pragma once

#include <QWidget>
#include <QJsonArray>
#include <QUrl>

class LearningApi;
class QListWidget;
class QLineEdit;
class QTextEdit;
class QSpinBox;
class QCheckBox;
class QDateTimeEdit;
class QLabel;

class TeacherLessonsWidget final : public QWidget {
    Q_OBJECT
public:
    explicit TeacherLessonsWidget(QWidget* parent = nullptr);
    void setSession(const QUrl& httpBase, const QString& classroomId, const QString& token);
    void refresh();

private:
    void loadSelectedLesson();
    void newLesson();
    void saveLesson();
    void uploadMaterial();
    void downloadSelectedMaterial();
    void refreshSubmissions();
    void downloadSelectedSubmissionFile();

    LearningApi* api_{};
    QListWidget* lessons_{};
    QLineEdit* title_{};
    QTextEdit* description_{};
    QTextEdit* homework_{};
    QSpinBox* position_{};
    QCheckBox* published_{};
    QCheckBox* dueEnabled_{};
    QDateTimeEdit* due_{};
    QListWidget* materials_{};
    QListWidget* submissions_{};
    QLabel* status_{};

    QJsonArray lessonData_;
    qint64 currentLessonId_{0};
};
