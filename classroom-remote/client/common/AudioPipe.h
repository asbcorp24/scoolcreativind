#pragma once

#include <QObject>
#include <QByteArray>
#include <memory>

class QAudioSource;
class QAudioSink;
class QAudioDevice;
class QIODevice;

class AudioPipe final : public QObject {
    Q_OBJECT
public:
    explicit AudioPipe(QObject* parent = nullptr);
    ~AudioPipe() override;

    bool startCapture();
    bool startCapture(const QAudioDevice& device);
    void stopCapture();
    bool startPlayback();
    void stopPlayback();
    void play(const QByteArray& pcm);

signals:
    void captured(const QByteArray& pcm);

private:
    std::unique_ptr<QAudioSource> source_;
    std::unique_ptr<QAudioSink> sink_;
    QIODevice* input_{};
    QIODevice* output_{};
};
