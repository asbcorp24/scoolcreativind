#include "LearningApi.h"

#include <QFile>
#include <QFileInfo>
#include <QHttpMultiPart>
#include <QJsonDocument>
#include <QJsonArray>
#include <QNetworkAccessManager>
#include <QNetworkReply>
#include <QUrlQuery>

LearningApi::LearningApi(QObject* parent)
    : QObject(parent), network_(new QNetworkAccessManager(this)) {}

void LearningApi::setSession(const QUrl& httpBase, const QString& classroomId, const QString& token) {
    base_ = httpBase;
    classroomId_ = classroomId;
    token_ = token;
}

bool LearningApi::ready() const {
    return base_.isValid() && !classroomId_.isEmpty() && !token_.isEmpty();
}

QNetworkRequest LearningApi::request(const QString& path) const {
    QUrl url = base_;
    url.setPath(path);
    url.setQuery({});
    QNetworkRequest req(url);
    req.setRawHeader("Authorization", "Bearer " + token_.toUtf8());
    return req;
}

void LearningApi::handleJsonReply(QNetworkReply* reply, std::function<void(const QJsonDocument&)> ok) {
    connect(reply, &QNetworkReply::finished, this, [this, reply, ok = std::move(ok)] {
        const QByteArray body = reply->readAll();
        if (reply->error() != QNetworkReply::NoError) {
            emit operationFailed(QString::fromUtf8(body).trimmed().isEmpty()
                ? reply->errorString()
                : QString::fromUtf8(body).trimmed());
            reply->deleteLater();
            return;
        }
        const QJsonDocument doc = QJsonDocument::fromJson(body);
        ok(doc);
        reply->deleteLater();
    });
}

void LearningApi::listLessons() {
    if (!ready()) return;
    const QString path = QString("/api/v1/classrooms/%1/lessons").arg(classroomId_);
    auto* reply = network_->get(request(path));
    handleJsonReply(reply, [this](const QJsonDocument& doc) {
        emit lessonsLoaded(doc.array());
    });
}

void LearningApi::createLesson(const QJsonObject& lesson) {
    if (!ready()) return;
    QNetworkRequest req = request(QString("/api/v1/classrooms/%1/lessons").arg(classroomId_));
    req.setHeader(QNetworkRequest::ContentTypeHeader, "application/json");
    auto* reply = network_->post(req, QJsonDocument(lesson).toJson(QJsonDocument::Compact));
    handleJsonReply(reply, [this](const QJsonDocument&) {
        emit operationSucceeded("Урок создан");
        listLessons();
    });
}

void LearningApi::updateLesson(qint64 lessonId, const QJsonObject& lesson) {
    if (!ready()) return;
    QNetworkRequest req = request(QString("/api/v1/classrooms/%1/lessons/%2").arg(classroomId_).arg(lessonId));
    req.setHeader(QNetworkRequest::ContentTypeHeader, "application/json");
    auto* reply = network_->put(req, QJsonDocument(lesson).toJson(QJsonDocument::Compact));
    handleJsonReply(reply, [this](const QJsonDocument&) {
        emit operationSucceeded("Урок сохранён");
        listLessons();
    });
}

void LearningApi::uploadMultipart(const QString& path, QHttpMultiPart* multi,
                                  const QString& successMessage) {
    QNetworkRequest req = request(path);
    auto* reply = network_->post(req, multi);
    multi->setParent(reply);
    handleJsonReply(reply, [this, successMessage](const QJsonDocument&) {
        emit operationSucceeded(successMessage);
        listLessons();
    });
}

void LearningApi::uploadMaterial(qint64 lessonId, const QString& path) {
    if (!ready() || path.isEmpty()) return;
    auto* multi = new QHttpMultiPart(QHttpMultiPart::FormDataType);
    auto* file = new QFile(path, multi);
    if (!file->open(QIODevice::ReadOnly)) {
        emit operationFailed("Не удалось открыть файл");
        delete multi;
        return;
    }
    QHttpPart part;
    part.setHeader(QNetworkRequest::ContentDispositionHeader,
        QString("form-data; name=\"file\"; filename=\"%1\"").arg(QFileInfo(path).fileName()));
    part.setBodyDevice(file);
    multi->append(part);
    uploadMultipart(QString("/api/v1/classrooms/%1/lessons/%2/materials").arg(classroomId_).arg(lessonId),
                    multi, "Материал загружен");
}

void LearningApi::listSubmissions(qint64 lessonId) {
    if (!ready()) return;
    auto* reply = network_->get(request(
        QString("/api/v1/classrooms/%1/lessons/%2/submissions").arg(classroomId_).arg(lessonId)));
    handleJsonReply(reply, [this, lessonId](const QJsonDocument& doc) {
        emit submissionsLoaded(lessonId, doc.array());
    });
}

void LearningApi::listMySubmissions(qint64 lessonId, const QString& studentId) {
    if (!ready()) return;
    QNetworkRequest req = request(
        QString("/api/v1/classrooms/%1/lessons/%2/my-submissions").arg(classroomId_).arg(lessonId));
    QUrl url = req.url();
    QUrlQuery q;
    q.addQueryItem("student_id", studentId);
    url.setQuery(q);
    req.setUrl(url);
    auto* reply = network_->get(req);
    handleJsonReply(reply, [this, lessonId](const QJsonDocument& doc) {
        emit mySubmissionsLoaded(lessonId, doc.array());
    });
}

void LearningApi::submitHomework(qint64 lessonId, const QString& studentId,
                                 const QString& studentName, const QString& note,
                                 const QStringList& files) {
    if (!ready() || files.isEmpty()) return;

    auto* multi = new QHttpMultiPart(QHttpMultiPart::FormDataType);
    auto addText = [multi](const QString& name, const QString& value) {
        QHttpPart p;
        p.setHeader(QNetworkRequest::ContentDispositionHeader,
                    QString("form-data; name=\"%1\"").arg(name));
        p.setBody(value.toUtf8());
        multi->append(p);
    };
    addText("student_id", studentId);
    addText("student_name", studentName);
    addText("note", note);

    for (const QString& path : files) {
        auto* file = new QFile(path, multi);
        if (!file->open(QIODevice::ReadOnly)) continue;
        QHttpPart p;
        p.setHeader(QNetworkRequest::ContentDispositionHeader,
                    QString("form-data; name=\"files\"; filename=\"%1\"")
                        .arg(QFileInfo(path).fileName()));
        p.setBodyDevice(file);
        multi->append(p);
    }

    const QString path = QString("/api/v1/classrooms/%1/lessons/%2/submissions")
        .arg(classroomId_).arg(lessonId);
    QNetworkRequest req = request(path);
    auto* reply = network_->post(req, multi);
    multi->setParent(reply);
    handleJsonReply(reply, [this, lessonId, studentId](const QJsonDocument&) {
        emit operationSucceeded("Домашняя работа отправлена");
        listMySubmissions(lessonId, studentId);
    });
}

static void saveReplyTo(QNetworkReply* reply, const QString& path, QObject* owner,
                        std::function<void(QString)> failed,
                        std::function<void(QString)> succeeded) {
    QObject::connect(reply, &QNetworkReply::finished, owner,
        [reply, path, failed = std::move(failed), succeeded = std::move(succeeded)] {
        const QByteArray data = reply->readAll();
        if (reply->error() != QNetworkReply::NoError) {
            failed(reply->errorString());
            reply->deleteLater();
            return;
        }
        QFile f(path);
        if (!f.open(QIODevice::WriteOnly) || f.write(data) != data.size()) {
            failed("Не удалось сохранить файл");
            reply->deleteLater();
            return;
        }
        f.close();
        succeeded(path);
        reply->deleteLater();
    });
}

void LearningApi::downloadMaterial(qint64 lessonId, qint64 fileId, const QString& savePath) {
    auto* reply = network_->get(request(
        QString("/api/v1/classrooms/%1/lessons/%2/materials/%3")
            .arg(classroomId_).arg(lessonId).arg(fileId)));
    saveReplyTo(reply, savePath, this,
        [this](const QString& e){ emit operationFailed(e); },
        [this](const QString& p){ emit operationSucceeded("Файл сохранён: " + p); });
}

void LearningApi::downloadSubmissionFile(qint64 lessonId, qint64 submissionId,
                                         qint64 fileId, const QString& savePath) {
    auto* reply = network_->get(request(
        QString("/api/v1/classrooms/%1/lessons/%2/submissions/%3/files/%4")
            .arg(classroomId_).arg(lessonId).arg(submissionId).arg(fileId)));
    saveReplyTo(reply, savePath, this,
        [this](const QString& e){ emit operationFailed(e); },
        [this](const QString& p){ emit operationSucceeded("Файл сохранён: " + p); });
}
