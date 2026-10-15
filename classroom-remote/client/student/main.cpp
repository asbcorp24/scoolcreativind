#include <QApplication>
#include <QTimer>
#include <QUrl>
#include "StudentWindow.h"
#include "../common/InviteLink.h"
#include "../common/SciTheme.h"

int main(int argc, char** argv) {
    QApplication app(argc, argv);
    QApplication::setApplicationName("Classroom Student");
    SciTheme::apply(app);

    InviteLink::registerProtocol("classroomstudent", "Classroom Student Invite");

    StudentWindow w;
    w.resize(960, 600);
    w.show();

    if (argc > 1) {
        const QUrl invite = QUrl::fromUserInput(QString::fromLocal8Bit(argv[1]));
        QTimer::singleShot(0, &w, [&w, invite] { w.openInviteUrl(invite); });
    }
    return app.exec();
}
