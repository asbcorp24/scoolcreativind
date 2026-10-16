#include "ClientConfig.h"

#include <QCoreApplication>
#include <QDialog>
#include <QDialogButtonBox>
#include <QDir>
#include <QFile>
#include <QFormLayout>
#include <QJsonDocument>
#include <QJsonObject>
#include <QLabel>
#include <QLineEdit>
#include <QUrl>
#include <QVBoxLayout>

QString ClientConfig::filePath() {
    return QDir(QCoreApplication::applicationDirPath()).filePath("config.json");
}

ClientConfig ClientConfig::load(const QString& defaultName) {
    ClientConfig cfg;
    cfg.serverUrl = "http://127.0.0.1:8080";
    cfg.lessonId = "101";
    cfg.displayName = defaultName;

    QFile f(filePath());
    if (!f.open(QIODevice::ReadOnly))
        return cfg;

    const auto doc = QJsonDocument::fromJson(f.readAll());
    const auto obj = doc.object();
    const QString server = obj.value("server_url").toString().trimmed();
    const QString lesson = obj.value("lesson_id").toString().trimmed();
    const QString name = obj.value("display_name").toString().trimmed();
    if (!server.isEmpty()) cfg.serverUrl = server;
    if (!lesson.isEmpty()) cfg.lessonId = lesson;
    if (!name.isEmpty()) cfg.displayName = name;
    return cfg;
}

bool ClientConfig::save(QString* error) const {
    QJsonObject obj{
        {"server_url", serverUrl.trimmed()},
        {"lesson_id", lessonId.trimmed()},
        {"display_name", displayName.trimmed()}
    };
    QFile f(filePath());
    if (!f.open(QIODevice::WriteOnly | QIODevice::Truncate)) {
        if (error) *error = f.errorString();
        return false;
    }
    if (f.write(QJsonDocument(obj).toJson(QJsonDocument::Indented)) < 0) {
        if (error) *error = f.errorString();
        return false;
    }
    return true;
}

bool ClientConfig::edit(QWidget* parent, const QString& title, QString* error) {
    QDialog dlg(parent);
    dlg.setWindowTitle(title);
    dlg.setMinimumWidth(480);

    auto* layout = new QVBoxLayout(&dlg);
    auto* form = new QFormLayout;
    auto* server = new QLineEdit(serverUrl);
    server->setPlaceholderText("https://classroom.example.ru");
    auto* lesson = new QLineEdit(lessonId);
    auto* name = new QLineEdit(displayName);

    form->addRow("Сервер:", server);
    form->addRow("Занятие / класс:", lesson);
    form->addRow("Имя:", name);
    layout->addLayout(form);

    auto* hint = new QLabel(
        "В config.json сохраняются только настройки подключения. "
        "PIN и внутренний токен доступа не сохраняются.");
    hint->setWordWrap(true);
    hint->setProperty("muted", true);
    layout->addWidget(hint);

    auto* buttons = new QDialogButtonBox(QDialogButtonBox::Save | QDialogButtonBox::Cancel);
    layout->addWidget(buttons);
    QObject::connect(buttons, &QDialogButtonBox::accepted, &dlg, &QDialog::accept);
    QObject::connect(buttons, &QDialogButtonBox::rejected, &dlg, &QDialog::reject);

    if (dlg.exec() != QDialog::Accepted)
        return false;

    QUrl url = QUrl::fromUserInput(server->text().trimmed());
    if (!url.isValid() || (url.scheme() != "http" && url.scheme() != "https") || url.host().isEmpty()) {
        if (error) *error = "Укажите корректный адрес сервера, например http://192.168.1.10:8080";
        return false;
    }

    QString base = QString("%1://%2").arg(url.scheme(), url.host());
    if (url.port() > 0)
        base += ":" + QString::number(url.port());

    serverUrl = base;
    lessonId = lesson->text().trimmed().isEmpty() ? "101" : lesson->text().trimmed();
    displayName = name->text().trimmed();
    if (displayName.isEmpty())
        displayName = "Пользователь";

    return save(error);
}
