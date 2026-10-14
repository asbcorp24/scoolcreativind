#pragma once

#include <QObject>
#include <QJsonArray>
#include <QJsonObject>
#include <QNetworkRequest>
#include <QUrl>

class QNetworkAccessManager;
class QHttpMultiPart;

class LearningApi final : public QObject {
    Q_OBJECT
public:
    explicit LearningApi(QObject* parent = nullptr);

    void setSession(const QUrl& httpBase, const QString& classroomId, const QString& token);
    bool ready() const;

    void listLessons();
    void createLesson(const QJsonObject& lesson);
    void updateLesson(qint64 lessonId, const QJsonObject& lesson);
    void uploadMaterial(qint64 lessonId, const QString& path);
    void listSubmissions(qint64 lessonId);
    void listMySubmissions(qint64 lessonId, const QString& studentId);
    void submitHomework(qint64 lessonId, const QString& studentId, const QString& studentName,
                        const QString& note, const QStringList& files);
    void downloadMaterial(qint64 lessonId, qint64 fileId, const QString& savePath);
    void downloadSubmissionFile(qint64 lessonId, qint64 submissionId, qint64 fileId,
                                const QString& savePath);

signals:
    void lessonsLoaded(const QJsonArray& lessons);
    void submissionsLoaded(qint64 lessonId, const QJsonArray& submissions);
    void mySubmissionsLoaded(qint64 lessonId, const QJsonArray& submissions);
    void operationSucceeded(const QString& message);
    void operationFailed(const QString& message);

private:
    QNetworkRequest request(const QString& path) const;
    void handleJsonReply(class QNetworkReply* reply, std::function<void(const QJsonDocument&)> ok);
    void uploadMultipart(const QString& path, QHttpMultiPart* multi,
                         const QString& successMessage);

    QNetworkAccessManager* network_{};
    QUrl base_;
    QString classroomId_;
    QString token_;
};
