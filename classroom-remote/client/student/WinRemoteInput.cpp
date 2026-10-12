#include "WinRemoteInput.h"

#ifdef Q_OS_WIN
#include <windows.h>
#endif

bool WinRemoteInput::apply(const QJsonObject& e) {
#ifdef Q_OS_WIN
    const auto type = e.value("event").toString();
    INPUT in{};
    if (type == "mouse_move") {
        in.type = INPUT_MOUSE;
        const double x = e.value("x").toDouble();
        const double y = e.value("y").toDouble();
        in.mi.dx = LONG(x * 65535.0);
        in.mi.dy = LONG(y * 65535.0);
        in.mi.dwFlags = MOUSEEVENTF_MOVE | MOUSEEVENTF_ABSOLUTE;
        return SendInput(1, &in, sizeof(INPUT)) == 1;
    }
    if (type == "mouse_down" || type == "mouse_up") {
        in.type = INPUT_MOUSE;
        const auto button = e.value("button").toString();
        if (button == "right")
            in.mi.dwFlags = type == "mouse_down" ? MOUSEEVENTF_RIGHTDOWN : MOUSEEVENTF_RIGHTUP;
        else
            in.mi.dwFlags = type == "mouse_down" ? MOUSEEVENTF_LEFTDOWN : MOUSEEVENTF_LEFTUP;
        return SendInput(1, &in, sizeof(INPUT)) == 1;
    }
    if (type == "mouse_wheel") {
        in.type = INPUT_MOUSE;
        in.mi.mouseData = DWORD(e.value("delta").toInt());
        in.mi.dwFlags = MOUSEEVENTF_WHEEL;
        return SendInput(1, &in, sizeof(INPUT)) == 1;
    }
    if (type == "key_down" || type == "key_up") {
        in.type = INPUT_KEYBOARD;
        in.ki.wVk = WORD(e.value("vk").toInt());
        if (type == "key_up") in.ki.dwFlags = KEYEVENTF_KEYUP;
        return SendInput(1, &in, sizeof(INPUT)) == 1;
    }
#else
    Q_UNUSED(e);
#endif
    return false;
}
