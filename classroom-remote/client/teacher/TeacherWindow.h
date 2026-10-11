#pragma once
#include <QMainWindow>
#include <QHash>
#include <QJsonObject>

class QListWidget;
class QLabel;
class QPushButton;
class QLineEdit;
class WsClient;

class TeacherWindow final : public QMainWindow {
    Q_OBJECT
public:
    explicit TeacherWindow(QWidget* parent = nullptr);

private:
    void connectServer();
    void onMessage(const QJsonObject& obj);
    QString selectedStudentId() const;
    void sendToSelected(const QString& type);

    WsClient* ws_{};
    QLineEdit* server_{};
    QLineEdit* lesson_{};
    QLineEdit* teacherName_{};
    QLabel* status_{};
    QLabel* lecturePreview_{};
    QListWidget* students_{};
    QHash<QString, QString> names_;
};
