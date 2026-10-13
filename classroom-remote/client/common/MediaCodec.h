#pragma once

#include <QByteArray>
#include <QImage>
#include <QList>
#include <QString>
#include <memory>

class MediaCodec final {
public:
    MediaCodec();
    ~MediaCodec();

    MediaCodec(const MediaCodec&) = delete;
    MediaCodec& operator=(const MediaCodec&) = delete;

    bool initialize(int width = 1280, int height = 720, int fps = 10,
                    int videoBitrate = 1800000, int audioBitrate = 64000);
    void shutdown();

    QByteArray encodeVideo(const QImage& image);
    QImage decodeVideo(const QByteArray& h264);

    QList<QByteArray> encodeAudio(const QByteArray& pcmS16Mono48k,
                                  const QString& streamId = "default");
    QByteArray decodeAudio(const QByteArray& opusPacket,
                           const QString& streamId = "default");

    void requestKeyframe();

    int width() const;
    int height() const;
    int fps() const;

private:
    struct Impl;
    std::unique_ptr<Impl> d_;
};
