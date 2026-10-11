#include <QApplication>
#include "TeacherWindow.h"

int main(int argc, char** argv) {
    QApplication app(argc, argv);
    QApplication::setApplicationName("Classroom Teacher");
    TeacherWindow w;
    w.resize(1100, 720);
    w.show();
    return app.exec();
}
