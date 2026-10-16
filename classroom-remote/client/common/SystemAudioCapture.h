#pragma once

#include <QObject>
#include <QByteArray>
#include <atomic>
#include <thread>

class SystemAudioCapture final : public QObject {
    Q_OBJECT
public:
    explicit SystemAudioCapture(QObject* parent = nullptr);
    ~SystemAudioCapture() override;

    bool start();
    void stop();
    bool isRunning() const { return running_.load(); }

signals:
    void captured(const QByteArray& pcm48Mono);
    void levelChanged(int percent);
    void errorText(const QString& error);

private:
    void captureLoop();

    std::atomic_bool running_{false};
    std::thread worker_;
};
