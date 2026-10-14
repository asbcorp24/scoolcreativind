#pragma once

#include <QWidget>
#include <QJsonArray>
#include <QUrl>
#include <QStringList>

class LearningApi;
class QListWidget;
class QLabel;
class QTextEdit;
class QPushButton;

class StudentLessonsWidget final : public QWidget {
    Q_OBJECT
public:
    explicit StudentLessonsWidget(QWidget* parent = nullptr);
    void setSession(const QUrl& httpBase, const QString& classroomId, const QString& token,
                    const QString& studentId, const QString& studentName);
    void setMaxUploadMB(int mb);
    void refresh();

private:
    void loadSelectedLesson();
    void addFiles();
    void submit();
    void downloadSelectedMaterial();

    LearningApi* api_{};
    QListWidget* lessons_{};
    QLabel* title_{};
    QTextEdit* description_{};
    QTextEdit* homework_{};
    QLabel* due_{};
    QListWidget* materials_{};
    QTextEdit* note_{};
    QListWidget* selectedFiles_{};
    QListWidget* mySubmissions_{};
    QLabel* status_{};
    QLabel* uploadLimit_{};

    QJsonArray lessonData_;
    qint64 currentLessonId_{0};
    QString studentId_;
    QString studentName_;
    QStringList files_;
    int maxUploadMB_{50};
};
