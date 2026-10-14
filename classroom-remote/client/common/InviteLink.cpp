#include "InviteLink.h"

#include <QCoreApplication>
#include <QSettings>
#include <QUrlQuery>

namespace InviteLink {

void registerProtocol(const QString& scheme, const QString& description) {
#ifdef Q_OS_WIN
    const QString root = QString("HKEY_CURRENT_USER\\Software\\Classes\\%1").arg(scheme);
    QSettings base(root, QSettings::NativeFormat);
    base.setValue(".", "URL:" + description);
    base.setValue("URL Protocol", "");

    QSettings icon(root + "\\DefaultIcon", QSettings::NativeFormat);
    icon.setValue(".", QString(""%1",0").arg(QCoreApplication::applicationFilePath()));

    QSettings command(root + "\\shell\\open\\command", QSettings::NativeFormat);
    command.setValue(".", QString(""%1" "%2"")
        .arg(QCoreApplication::applicationFilePath(), "%1"));
    base.sync();
    icon.sync();
    command.sync();
#else
    Q_UNUSED(scheme)
    Q_UNUSED(description)
#endif
}

QUrl normalizeInviteUrl(const QUrl& input) {
    if (input.scheme() == "classroomteacher" || input.scheme() == "classroomstudent") {
        QUrlQuery q(input);
        const QString invite = q.queryItemValue("invite", QUrl::FullyDecoded);
        return QUrl(invite);
    }
    return input;
}

QUrl resolveEndpoint(const QUrl& input) {
    QUrl invite = normalizeInviteUrl(input);
    const QStringList parts = invite.path().split('/', Qt::SkipEmptyParts);
    if (parts.size() >= 2 && parts.at(parts.size() - 2) == "join") {
        const QString code = parts.constLast();
        invite.setPath("/api/invite/" + code);
        invite.setQuery({});
        invite.setFragment({});
    }
    return invite;
}

}
