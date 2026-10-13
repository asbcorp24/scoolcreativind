#include "MediaCodec.h"

#include <QtGlobal>
#include <algorithm>
#include <cmath>
#include <cstring>
#include <vector>

#include <wels/codec_api.h>
#include <opus/opus.h>

namespace {

inline unsigned char clampByte(int v) {
    return static_cast<unsigned char>(std::clamp(v, 0, 255));
}

QImage letterbox(const QImage& source, int width, int height) {
    if (source.isNull())
        return {};

    QImage canvas(width, height, QImage::Format_RGB888);
    canvas.fill(Qt::black);

    const QImage scaled = source.convertToFormat(QImage::Format_RGB888)
        .scaled(width, height, Qt::KeepAspectRatio, Qt::SmoothTransformation);

    const int ox = (width - scaled.width()) / 2;
    const int oy = (height - scaled.height()) / 2;

    for (int y = 0; y < scaled.height(); ++y) {
        std::memcpy(canvas.scanLine(oy + y) + ox * 3,
                    scaled.constScanLine(y),
                    static_cast<size_t>(scaled.width() * 3));
    }
    return canvas;
}

void rgbToI420(const QImage& rgb, std::vector<unsigned char>& yuv,
               int width, int height) {
    const int ySize = width * height;
    const int uvWidth = width / 2;
    const int uvHeight = height / 2;
    const int uvSize = uvWidth * uvHeight;

    yuv.resize(static_cast<size_t>(ySize + uvSize * 2));
    auto* yPlane = yuv.data();
    auto* uPlane = yPlane + ySize;
    auto* vPlane = uPlane + uvSize;

    for (int y = 0; y < height; ++y) {
        const auto* row = rgb.constScanLine(y);
        for (int x = 0; x < width; ++x) {
            const int r = row[x * 3 + 0];
            const int g = row[x * 3 + 1];
            const int b = row[x * 3 + 2];

            const int yy = ((66 * r + 129 * g + 25 * b + 128) >> 8) + 16;
            yPlane[y * width + x] = clampByte(yy);
        }
    }

    for (int y = 0; y < height; y += 2) {
        const auto* row0 = rgb.constScanLine(y);
        const auto* row1 = rgb.constScanLine(std::min(y + 1, height - 1));
        for (int x = 0; x < width; x += 2) {
            int sumU = 0;
            int sumV = 0;
            for (int dy = 0; dy < 2; ++dy) {
                const auto* row = dy == 0 ? row0 : row1;
                for (int dx = 0; dx < 2; ++dx) {
                    const int xx = std::min(x + dx, width - 1);
                    const int r = row[xx * 3 + 0];
                    const int g = row[xx * 3 + 1];
                    const int b = row[xx * 3 + 2];
                    sumU += ((-38 * r - 74 * g + 112 * b + 128) >> 8) + 128;
                    sumV += ((112 * r - 94 * g - 18 * b + 128) >> 8) + 128;
                }
            }
            const int uvIndex = (y / 2) * uvWidth + (x / 2);
            uPlane[uvIndex] = clampByte(sumU / 4);
            vPlane[uvIndex] = clampByte(sumV / 4);
        }
    }
}

QImage i420ToRgb(const unsigned char* yPlane,
                 const unsigned char* uPlane,
                 const unsigned char* vPlane,
                 int strideY, int strideU, int strideV,
                 int width, int height) {
    QImage image(width, height, QImage::Format_RGB888);
    for (int y = 0; y < height; ++y) {
        auto* dst = image.scanLine(y);
        const auto* py = yPlane + y * strideY;
        const auto* pu = uPlane + (y / 2) * strideU;
        const auto* pv = vPlane + (y / 2) * strideV;

        for (int x = 0; x < width; ++x) {
            const int c = std::max(0, int(py[x]) - 16);
            const int d = int(pu[x / 2]) - 128;
            const int e = int(pv[x / 2]) - 128;

            const int r = (298 * c + 409 * e + 128) >> 8;
            const int g = (298 * c - 100 * d - 208 * e + 128) >> 8;
            const int b = (298 * c + 516 * d + 128) >> 8;

            dst[x * 3 + 0] = clampByte(r);
            dst[x * 3 + 1] = clampByte(g);
            dst[x * 3 + 2] = clampByte(b);
        }
    }
    return image;
}

} // namespace

struct MediaCodec::Impl {
    ISVCEncoder* videoEncoder{};
    ISVCDecoder* videoDecoder{};
    OpusEncoder* audioEncoder{};
    OpusDecoder* audioDecoder{};

    int width{1280};
    int height{720};
    int fps{10};
    int videoBitrate{1800000};
    int audioBitrate{64000};
    int frameCounter{0};
    bool forceKeyframe{true};

    std::vector<unsigned char> yuv;
    QByteArray audioBuffer;
};

MediaCodec::MediaCodec() : d_(std::make_unique<Impl>()) {}

MediaCodec::~MediaCodec() {
    shutdown();
}

bool MediaCodec::initialize(int width, int height, int fps,
                            int videoBitrate, int audioBitrate) {
    shutdown();

    width &= ~1;
    height &= ~1;
    if (width <= 0 || height <= 0 || fps <= 0)
        return false;

    d_->width = width;
    d_->height = height;
    d_->fps = fps;
    d_->videoBitrate = videoBitrate;
    d_->audioBitrate = audioBitrate;

    if (WelsCreateSVCEncoder(&d_->videoEncoder) != 0 || !d_->videoEncoder)
        return false;

    SEncParamExt enc{};
    if (d_->videoEncoder->GetDefaultParams(&enc) != cmResultSuccess) {
        shutdown();
        return false;
    }

    enc.iUsageType = CAMERA_VIDEO_REAL_TIME;
    enc.iPicWidth = width;
    enc.iPicHeight = height;
    enc.iTargetBitrate = videoBitrate;
    enc.iMaxBitrate = videoBitrate;
    enc.iRCMode = RC_BITRATE_MODE;
    enc.fMaxFrameRate = static_cast<float>(fps);
    enc.iTemporalLayerNum = 1;
    enc.iSpatialLayerNum = 1;
    enc.uiIntraPeriod = static_cast<unsigned int>(std::max(1, fps * 2));
    enc.iComplexityMode = LOW_COMPLEXITY;
    enc.bEnableFrameSkip = true;
    enc.eSpsPpsIdStrategy = CONSTANT_ID;

    enc.sSpatialLayers[0].iVideoWidth = width;
    enc.sSpatialLayers[0].iVideoHeight = height;
    enc.sSpatialLayers[0].fFrameRate = static_cast<float>(fps);
    enc.sSpatialLayers[0].iSpatialBitrate = videoBitrate;
    enc.sSpatialLayers[0].iMaxSpatialBitrate = videoBitrate;
    enc.sSpatialLayers[0].uiProfileIdc = PRO_BASELINE;
    enc.sSpatialLayers[0].uiLevelIdc = LEVEL_3_1;

    if (d_->videoEncoder->InitializeExt(&enc) != cmResultSuccess) {
        shutdown();
        return false;
    }

    int format = videoFormatI420;
    d_->videoEncoder->SetOption(ENCODER_OPTION_DATAFORMAT, &format);

    if (WelsCreateDecoder(&d_->videoDecoder) != 0 || !d_->videoDecoder) {
        shutdown();
        return false;
    }

    SDecodingParam dec{};
    dec.sVideoProperty.eVideoBsType = VIDEO_BITSTREAM_AVC;
    if (d_->videoDecoder->Initialize(&dec) != 0) {
        shutdown();
        return false;
    }

    int opusError = OPUS_OK;
    d_->audioEncoder = opus_encoder_create(48000, 1, OPUS_APPLICATION_VOIP, &opusError);
    if (opusError != OPUS_OK || !d_->audioEncoder) {
        shutdown();
        return false;
    }
    opus_encoder_ctl(d_->audioEncoder, OPUS_SET_BITRATE(audioBitrate));
    opus_encoder_ctl(d_->audioEncoder, OPUS_SET_COMPLEXITY(5));
    opus_encoder_ctl(d_->audioEncoder, OPUS_SET_INBAND_FEC(1));
    opus_encoder_ctl(d_->audioEncoder, OPUS_SET_PACKET_LOSS_PERC(5));

    d_->audioDecoder = opus_decoder_create(48000, 1, &opusError);
    if (opusError != OPUS_OK || !d_->audioDecoder) {
        shutdown();
        return false;
    }

    d_->frameCounter = 0;
    d_->forceKeyframe = true;
    d_->audioBuffer.clear();
    return true;
}

void MediaCodec::shutdown() {
    if (d_->videoEncoder) {
        d_->videoEncoder->Uninitialize();
        WelsDestroySVCEncoder(d_->videoEncoder);
        d_->videoEncoder = nullptr;
    }
    if (d_->videoDecoder) {
        d_->videoDecoder->Uninitialize();
        WelsDestroyDecoder(d_->videoDecoder);
        d_->videoDecoder = nullptr;
    }
    if (d_->audioEncoder) {
        opus_encoder_destroy(d_->audioEncoder);
        d_->audioEncoder = nullptr;
    }
    if (d_->audioDecoder) {
        opus_decoder_destroy(d_->audioDecoder);
        d_->audioDecoder = nullptr;
    }
    d_->audioBuffer.clear();
    d_->yuv.clear();
}

QByteArray MediaCodec::encodeVideo(const QImage& source) {
    if (!d_->videoEncoder || source.isNull())
        return {};

    const QImage rgb = letterbox(source, d_->width, d_->height);
    if (rgb.isNull())
        return {};

    rgbToI420(rgb, d_->yuv, d_->width, d_->height);

    if (d_->forceKeyframe || (d_->frameCounter % std::max(1, d_->fps * 2)) == 0) {
        d_->videoEncoder->ForceIntraFrame(true);
        d_->forceKeyframe = false;
    }

    const int ySize = d_->width * d_->height;
    const int uvSize = (d_->width / 2) * (d_->height / 2);

    SSourcePicture pic{};
    pic.iColorFormat = videoFormatI420;
    pic.iPicWidth = d_->width;
    pic.iPicHeight = d_->height;
    pic.iStride[0] = d_->width;
    pic.iStride[1] = d_->width / 2;
    pic.iStride[2] = d_->width / 2;
    pic.pData[0] = d_->yuv.data();
    pic.pData[1] = d_->yuv.data() + ySize;
    pic.pData[2] = d_->yuv.data() + ySize + uvSize;
    pic.uiTimeStamp = static_cast<unsigned long long>(
        (1000LL * d_->frameCounter) / std::max(1, d_->fps));

    SFrameBSInfo info{};
    const int rc = d_->videoEncoder->EncodeFrame(&pic, &info);
    ++d_->frameCounter;
    if (rc != cmResultSuccess || info.eFrameType == videoFrameTypeSkip)
        return {};

    QByteArray encoded;
    for (int layer = 0; layer < info.iLayerNum; ++layer) {
        const SLayerBSInfo& l = info.sLayerInfo[layer];
        int layerBytes = 0;
        for (int n = 0; n < l.iNalCount; ++n)
            layerBytes += l.pNalLengthInByte[n];
        if (layerBytes > 0 && l.pBsBuf)
            encoded.append(reinterpret_cast<const char*>(l.pBsBuf), layerBytes);
    }
    return encoded;
}

QImage MediaCodec::decodeVideo(const QByteArray& h264) {
    if (!d_->videoDecoder || h264.isEmpty())
        return {};

    unsigned char* planes[3]{};
    SBufferInfo info{};
    const DECODING_STATE state = d_->videoDecoder->DecodeFrameNoDelay(
        reinterpret_cast<const unsigned char*>(h264.constData()),
        static_cast<int>(h264.size()),
        planes,
        &info);

    if (state != dsErrorFree || info.iBufferStatus != 1 ||
        !planes[0] || !planes[1] || !planes[2])
        return {};

    const auto& sys = info.UsrData.sSystemBuffer;
    const int width = sys.iWidth;
    const int height = sys.iHeight;
    if (width <= 0 || height <= 0)
        return {};

    return i420ToRgb(
        planes[0], planes[1], planes[2],
        sys.iStride[0], sys.iStride[1], sys.iStride[1],
        width, height);
}

QList<QByteArray> MediaCodec::encodeAudio(const QByteArray& pcm) {
    QList<QByteArray> packets;
    if (!d_->audioEncoder || pcm.isEmpty())
        return packets;

    d_->audioBuffer.append(pcm);

    constexpr int samplesPerFrame = 960; // 20 ms at 48 kHz
    constexpr int bytesPerFrame = samplesPerFrame * int(sizeof(opus_int16));

    while (d_->audioBuffer.size() >= bytesPerFrame) {
        const auto* samples = reinterpret_cast<const opus_int16*>(d_->audioBuffer.constData());

        unsigned char encoded[4000];
        const int bytes = opus_encode(d_->audioEncoder,
                                      samples,
                                      samplesPerFrame,
                                      encoded,
                                      int(sizeof(encoded)));
        if (bytes > 0)
            packets.append(QByteArray(reinterpret_cast<const char*>(encoded), bytes));

        d_->audioBuffer.remove(0, bytesPerFrame);
    }

    return packets;
}

QByteArray MediaCodec::decodeAudio(const QByteArray& packet) {
    if (!d_->audioDecoder || packet.isEmpty())
        return {};

    opus_int16 pcm[5760];
    const int samples = opus_decode(
        d_->audioDecoder,
        reinterpret_cast<const unsigned char*>(packet.constData()),
        packet.size(),
        pcm,
        5760,
        0);

    if (samples <= 0)
        return {};

    return QByteArray(reinterpret_cast<const char*>(pcm),
                      samples * int(sizeof(opus_int16)));
}

void MediaCodec::requestKeyframe() {
    d_->forceKeyframe = true;
}

int MediaCodec::width() const { return d_->width; }
int MediaCodec::height() const { return d_->height; }
int MediaCodec::fps() const { return d_->fps; }
