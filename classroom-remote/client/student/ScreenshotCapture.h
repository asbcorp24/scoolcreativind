#pragma once

#include <QImage>
#include <QString>

class QWidget;

class ScreenshotCapture {
public:
    // Returns a null image if the user cancelled or capture failed.
    // description receives a human-readable source name.
    static QImage capture(QWidget* owner, QString* description = nullptr);
};
