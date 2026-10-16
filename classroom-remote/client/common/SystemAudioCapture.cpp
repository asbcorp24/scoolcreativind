#include "SystemAudioCapture.h"

#include <QMetaObject>
#include <QtGlobal>
#include <algorithm>
#include <cmath>
#include <cstring>
#include <cstdint>

#ifdef Q_OS_WIN
#define NOMINMAX
#include <windows.h>
#include <audioclient.h>
#include <mmdeviceapi.h>
#include <wrl/client.h>

using Microsoft::WRL::ComPtr;

namespace {

bool isFloatFormat(const WAVEFORMATEX* f) {
    if (!f) return false;
    if (f->wFormatTag == WAVE_FORMAT_IEEE_FLOAT)
        return true;
    if (f->wFormatTag == WAVE_FORMAT_EXTENSIBLE && f->cbSize >= 22) {
        const auto* ex = reinterpret_cast<const WAVEFORMATEXTENSIBLE*>(f);
        return ex->SubFormat.Data1 == WAVE_FORMAT_IEEE_FLOAT;
    }
    return false;
}

bool isPCMFormat(const WAVEFORMATEX* f) {
    if (!f) return false;
    if (f->wFormatTag == WAVE_FORMAT_PCM)
        return true;
    if (f->wFormatTag == WAVE_FORMAT_EXTENSIBLE && f->cbSize >= 22) {
        const auto* ex = reinterpret_cast<const WAVEFORMATEXTENSIBLE*>(f);
        return ex->SubFormat.Data1 == WAVE_FORMAT_PCM;
    }
    return false;
}

float readSample(const BYTE* frame, int channel, const WAVEFORMATEX* f) {
    const int bytesPerSample = f->wBitsPerSample / 8;
    const BYTE* p = frame + channel * bytesPerSample;

    if (isFloatFormat(f) && f->wBitsPerSample == 32) {
        float v = 0.0f;
        std::memcpy(&v, p, sizeof(float));
        return std::clamp(v, -1.0f, 1.0f);
    }
    if (isPCMFormat(f) && f->wBitsPerSample == 16) {
        int16_t v = 0;
        std::memcpy(&v, p, sizeof(v));
        return float(v) / 32768.0f;
    }
    if (isPCMFormat(f) && f->wBitsPerSample == 32) {
        int32_t v = 0;
        std::memcpy(&v, p, sizeof(v));
        return float(double(v) / 2147483648.0);
    }
    if (isPCMFormat(f) && f->wBitsPerSample == 24) {
        int32_t v = int32_t(p[0]) | (int32_t(p[1]) << 8) | (int32_t(p[2]) << 16);
        if (v & 0x800000) v |= ~0xFFFFFF;
        return float(double(v) / 8388608.0);
    }
    return 0.0f;
}

} // namespace
#endif

SystemAudioCapture::SystemAudioCapture(QObject* parent) : QObject(parent) {}

SystemAudioCapture::~SystemAudioCapture() {
    stop();
}

bool SystemAudioCapture::start() {
#ifdef Q_OS_WIN
    stop();
    running_.store(true);
    worker_ = std::thread([this] { captureLoop(); });
    return true;
#else
    emit errorText("Системный звук поддерживается только в Windows.");
    return false;
#endif
}

void SystemAudioCapture::stop() {
    running_.store(false);
    if (worker_.joinable())
        worker_.join();
}

void SystemAudioCapture::captureLoop() {
#ifdef Q_OS_WIN
    const HRESULT ci = CoInitializeEx(nullptr, COINIT_MULTITHREADED);
    const bool comReady = SUCCEEDED(ci) || ci == RPC_E_CHANGED_MODE;

    auto fail = [this](const QString& text) {
        QMetaObject::invokeMethod(this, [this, text] { emit errorText(text); }, Qt::QueuedConnection);
    };

    ComPtr<IMMDeviceEnumerator> enumerator;
    HRESULT hr = CoCreateInstance(__uuidof(MMDeviceEnumerator), nullptr, CLSCTX_ALL,
                                  IID_PPV_ARGS(&enumerator));
    if (FAILED(hr)) {
        fail("WASAPI: не удалось открыть перечислитель аудиоустройств.");
        running_.store(false);
        if (comReady && ci != RPC_E_CHANGED_MODE) CoUninitialize();
        return;
    }

    ComPtr<IMMDevice> device;
    hr = enumerator->GetDefaultAudioEndpoint(eRender, eConsole, &device);
    if (FAILED(hr)) {
        fail("WASAPI: устройство вывода Windows не найдено.");
        running_.store(false);
        if (comReady && ci != RPC_E_CHANGED_MODE) CoUninitialize();
        return;
    }

    ComPtr<IAudioClient> audioClient;
    hr = device->Activate(__uuidof(IAudioClient), CLSCTX_ALL, nullptr,
                          reinterpret_cast<void**>(audioClient.GetAddressOf()));
    if (FAILED(hr)) {
        fail("WASAPI: не удалось открыть системный аудиопоток.");
        running_.store(false);
        if (comReady && ci != RPC_E_CHANGED_MODE) CoUninitialize();
        return;
    }

    WAVEFORMATEX* mix = nullptr;
    hr = audioClient->GetMixFormat(&mix);
    if (FAILED(hr) || !mix) {
        fail("WASAPI: не удалось получить формат системного звука.");
        running_.store(false);
        if (comReady && ci != RPC_E_CHANGED_MODE) CoUninitialize();
        return;
    }

    hr = audioClient->Initialize(AUDCLNT_SHAREMODE_SHARED,
                                 AUDCLNT_STREAMFLAGS_LOOPBACK,
                                 10000000, 0, mix, nullptr);
    if (FAILED(hr)) {
        CoTaskMemFree(mix);
        fail("WASAPI: loopback-захват системного звука недоступен.");
        running_.store(false);
        if (comReady && ci != RPC_E_CHANGED_MODE) CoUninitialize();
        return;
    }

    ComPtr<IAudioCaptureClient> capture;
    hr = audioClient->GetService(__uuidof(IAudioCaptureClient),
                                 reinterpret_cast<void**>(capture.GetAddressOf()));
    if (FAILED(hr)) {
        CoTaskMemFree(mix);
        fail("WASAPI: не удалось создать loopback capture client.");
        running_.store(false);
        if (comReady && ci != RPC_E_CHANGED_MODE) CoUninitialize();
        return;
    }

    audioClient->Start();

    const int channels = qMax(1, int(mix->nChannels));
    const int inputRate = qMax(1, int(mix->nSamplesPerSec));
    const int frameBytes = qMax(1, int(mix->nBlockAlign));
    qint64 rateAccumulator = 0;

    while (running_.load()) {
        UINT32 packetFrames = 0;
        hr = capture->GetNextPacketSize(&packetFrames);
        if (FAILED(hr)) break;

        if (packetFrames == 0) {
            Sleep(5);
            continue;
        }

        BYTE* data = nullptr;
        UINT32 frames = 0;
        DWORD flags = 0;
        hr = capture->GetBuffer(&data, &frames, &flags, nullptr, nullptr);
        if (FAILED(hr)) break;

        QByteArray out;
        out.reserve(int(double(frames) * 48000.0 / inputRate * 2.0) + 16);
        int peak = 0;

        for (UINT32 i = 0; i < frames; ++i) {
            float mono = 0.0f;
            if (!(flags & AUDCLNT_BUFFERFLAGS_SILENT) && data) {
                const BYTE* frame = data + i * frameBytes;
                for (int ch = 0; ch < channels; ++ch)
                    mono += readSample(frame, ch, mix);
                mono /= float(channels);
            }
            mono = std::clamp(mono, -1.0f, 1.0f);
            const qint16 sample = qint16(std::clamp(int(std::lround(mono * 32767.0f)), -32768, 32767));
            peak = qMax(peak, std::abs(int(sample)));

            rateAccumulator += 48000;
            while (rateAccumulator >= inputRate) {
                out.append(reinterpret_cast<const char*>(&sample), int(sizeof(sample)));
                rateAccumulator -= inputRate;
            }
        }

        capture->ReleaseBuffer(frames);

        if (!out.isEmpty()) {
            const int level = qBound(0, int((peak * 100.0) / 32767.0), 100);
            QMetaObject::invokeMethod(this, [this, out, level] {
                emit levelChanged(level);
                emit captured(out);
            }, Qt::QueuedConnection);
        }
    }

    audioClient->Stop();
    CoTaskMemFree(mix);
    running_.store(false);
    if (comReady && ci != RPC_E_CHANGED_MODE) CoUninitialize();
#else
    running_.store(false);
#endif
}
