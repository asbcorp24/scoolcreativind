#pragma once

#include <QUrl>
#include <QString>

namespace InviteLink {
void registerProtocol(const QString& scheme, const QString& description);
QUrl normalizeInviteUrl(const QUrl& input);
QUrl resolveEndpoint(const QUrl& inviteUrl);
}
