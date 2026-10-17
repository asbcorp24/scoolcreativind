#pragma once

#include <QString>

class QWidget;

struct ClientConfig {
    QString serverUrl;
    QString lessonId;
    QString displayName;
    bool localNetwork{false};

    static QString filePath();
    static ClientConfig load(const QString& defaultName);
    bool save(QString* error = nullptr) const;
    bool edit(QWidget* parent, const QString& title, QString* error = nullptr);
};
