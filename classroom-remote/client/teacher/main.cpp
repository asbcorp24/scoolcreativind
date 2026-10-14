#include <QApplication>
#include <QTimer>
#include <QUrl>
#include "TeacherWindow.h"
#include "../common/InviteLink.h"

int main(int argc, char** argv) {
    QApplication app(argc, argv);
    QApplication::setApplicationName("Classroom Teacher");

    InviteLink::registerProtocol("classroomteacher", "Classroom Teacher Invite");

    TeacherWindow w;
    w.resize(1100, 720);
    w.show();

    if (argc > 1) {
        const QUrl invite = QUrl::fromUserInput(QString::fromLocal8Bit(argv[1]));
        QTimer::singleShot(0, &w, [&w, invite] { w.openInviteUrl(invite); });
    }
    return app.exec();
}
