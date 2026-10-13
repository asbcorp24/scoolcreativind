#include "../common/MediaCodec.h"

#include <QByteArray>
#include <QImage>
#include <QDebug>
#include <cmath>

int main() {
    MediaCodec codec;
    if (!codec.initialize(640, 360, 10, 600000, 48000)) {
        qCritical() << "MediaCodec initialize failed";
        return 1;
    }

    QImage image(640, 360, QImage::Format_RGB888);
    for (int y = 0; y < image.height(); ++y) {
        auto* row = image.scanLine(y);
        for (int x = 0; x < image.width(); ++x) {
            row[x * 3 + 0] = static_cast<unsigned char>((x * 255) / image.width());
            row[x * 3 + 1] = static_cast<unsigned char>((y * 255) / image.height());
            row[x * 3 + 2] = 96;
        }
    }

    const QByteArray h264 = codec.encodeVideo(image);
    if (h264.isEmpty()) {
        qCritical() << "H264 encoder returned empty frame";
        return 2;
    }

    const QImage decoded = codec.decodeVideo(h264);
    if (decoded.isNull() || decoded.width() != 640 || decoded.height() != 360) {
        qCritical() << "H264 decoder failed" << decoded.size();
        return 3;
    }

    QByteArray pcm(960 * 2, Qt::Uninitialized);
    auto* samples = reinterpret_cast<qint16*>(pcm.data());
    for (int i = 0; i < 960; ++i) {
        const double phase = 2.0 * 3.141592653589793 * 440.0 * double(i) / 48000.0;
        samples[i] = static_cast<qint16>(std::sin(phase) * 12000.0);
    }

    const auto opusPackets = codec.encodeAudio(pcm);
    if (opusPackets.size() != 1 || opusPackets.first().isEmpty()) {
        qCritical() << "Opus encoder failed";
        return 4;
    }

    const QByteArray decodedPcm = codec.decodeAudio(opusPackets.first());
    if (decodedPcm.size() != pcm.size()) {
        qCritical() << "Opus decoder returned unexpected PCM size" << decodedPcm.size();
        return 5;
    }

    qInfo() << "OpenH264/Opus smoke test passed"
            << "H264 bytes:" << h264.size()
            << "Opus bytes:" << opusPackets.first().size();
    return 0;
}
