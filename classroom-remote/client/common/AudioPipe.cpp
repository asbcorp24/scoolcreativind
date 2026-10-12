#include "AudioPipe.h"

#include <QAudioFormat>
#include <QAudioSink>
#include <QAudioSource>
#include <QIODevice>
#include <QMediaDevices>

static QAudioFormat classroomAudioFormat() {
    QAudioFormat f;
    f.setSampleRate(16000);
    f.setChannelCount(1);
    f.setSampleFormat(QAudioFormat::Int16);
    return f;
}

AudioPipe::AudioPipe(QObject* parent) : QObject(parent) {}

AudioPipe::~AudioPipe() {
    stopCapture();
    stopPlayback();
}

bool AudioPipe::startCapture() {
    stopCapture();
    const auto device = QMediaDevices::defaultAudioInput();
    if (device.isNull())
        return false;

    source_ = std::make_unique<QAudioSource>(device, classroomAudioFormat());
    input_ = source_->start();
    if (!input_)
        return false;

    connect(input_, &QIODevice::readyRead, this, [this] {
        if (!input_)
            return;
        const QByteArray data = input_->readAll();
        if (!data.isEmpty())
            emit captured(data);
    });
    return true;
}

void AudioPipe::stopCapture() {
    if (source_)
        source_->stop();
    input_ = nullptr;
    source_.reset();
}

bool AudioPipe::startPlayback() {
    stopPlayback();
    const auto device = QMediaDevices::defaultAudioOutput();
    if (device.isNull())
        return false;

    sink_ = std::make_unique<QAudioSink>(device, classroomAudioFormat());
    output_ = sink_->start();
    return output_ != nullptr;
}

void AudioPipe::stopPlayback() {
    if (sink_)
        sink_->stop();
    output_ = nullptr;
    sink_.reset();
}

void AudioPipe::play(const QByteArray& pcm) {
    if (!output_)
        startPlayback();
    if (output_ && !pcm.isEmpty())
        output_->write(pcm);
}
