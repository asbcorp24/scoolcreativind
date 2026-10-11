#pragma once
#include <QMainWindow>
#include <QJsonObject>

class QLabel;
class QLineEdit;
class QPushButton;
class QTimer;
class QNetworkAccessManager;
class WsClient;

class StudentWindow final : public QMainWindow {
    Q_OBJECT
public:
    explicit StudentWindow(QWidget* parent = nullptr);

private:
    void connectServer();
    void onMessage(const QJsonObject& obj);
    void uploadSnapshot();

    WsClient* ws_{};
    QLineEdit* wsUrl_{};
    QLineEdit* httpUrl_{};
    QLineEdit* lesson_{};
    QLineEdit* name_{};
    QLabel* status_{};
    QLabel* lecture_{};
    QPushButton* hand_{};
    QTimer* snapshotTimer_{};
    QNetworkAccessManager* network_{};
    QString studentId_;
    bool handRaised_{false};
};
