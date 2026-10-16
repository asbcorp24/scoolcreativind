#include "AudioPipe.h"

#include <QAudioFormat>
#include <QAudioDevice>
#include <QAudioSink>
#include <QAudioSource>
#include <QIODevice>
#include <QMediaDevices>
#include <QtGlobal>
#include <cmath>

static QAudioFormat classroomAudioFormat() {
    QAudioFormat f;
    f.setSampleRate(48000);
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
    return startCapture(QMediaDevices::defaultAudioInput());
}

bool AudioPipe::startCapture(const QAudioDevice& device) {
    stopCapture();
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
        if (!data.isEmpty()) {
            const auto* samples = reinterpret_cast<const qint16*>(data.constData());
            const int count = data.size() / int(sizeof(qint16));
            int peak = 0;
            for (int i = 0; i < count; ++i)
                peak = qMax(peak, std::abs(int(samples[i])));
            emit levelChanged(qBound(0, int((peak * 100.0) / 32767.0), 100));
            emit captured(data);
        }
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
