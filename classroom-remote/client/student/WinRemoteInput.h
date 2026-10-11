#pragma once
#include <QJsonObject>

class WinRemoteInput {
public:
    static bool apply(const QJsonObject& event);
};
