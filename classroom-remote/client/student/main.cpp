#include <QApplication>
#include "StudentWindow.h"

int main(int argc, char** argv) {
    QApplication app(argc, argv);
    QApplication::setApplicationName("Classroom Student");
    StudentWindow w;
    w.resize(960, 600);
    w.show();
    return app.exec();
}
