#include "TeacherWindow.h"
#include "RemoteView.h"
#include "TeacherLessonsWidget.h"
#include "RegionSelector.h"
#include "../common/WsClient.h"
#include "../common/RtcRelayClient.h"
#include "../common/AudioPipe.h"
#include "../common/SystemAudioCapture.h"
#include "../common/MediaCodec.h"
#include "../common/InviteLink.h"
#include "../common/AnnotationOverlay.h"

#include <QBuffer>
#include <QApplication>
#include <QRadioButton>
#include <QButtonGroup>
#include <QComboBox>
#include <QCheckBox>
#include <QSlider>
#include <QProgressBar>
#include <QSpinBox>
#include <QWindowCapture>
#include <QCapturableWindow>
#include <QSettings>
#include <QFileInfo>
#include <QDir>
#include <QDesktopServices>
#include <QDialog>
#include <QProcess>
#include <QStandardPaths>
#include <QAudioDevice>
#include <QCameraDevice>
#include <QScreenCapture>
#include <QMediaRecorder>
#include <QMediaPlayer>
#include <QMediaFormat>
#include <QAudioInput>
#include <QAudioFormat>
#include <QAudioBufferOutput>
#include <QAudioBuffer>
#include <QCamera>
#include <QDateTime>
#include <QGuiApplication>
#include <QHBoxLayout>
#include <QGridLayout>
#include <QJsonValue>
#include <QHttpMultiPart>
#include <QStackedLayout>
#include <QPlainTextEdit>
#include <QInputDialog>
#include <QImage>
#include <QJsonArray>
#include <QJsonDocument>
#include <QJsonObject>
#include <QLabel>
#include <QLineEdit>
#include <QListWidget>
#include <QListWidgetItem>
#include <QMediaCaptureSession>
#include <QMediaDevices>
#include <QFile>
#include <QFileInfo>
#include <QFileDialog>
#include <QNetworkAccessManager>
#include <QNetworkReply>
#include <QNetworkRequest>
#include <QNetworkInterface>
#include <QUdpSocket>
#include <QNetworkDatagram>
#include <QHostAddress>
#include <QTcpServer>
#include <QUrl>
#include <QMessageBox>
#include <QPixmap>
#include <QPainter>
#include <QCursor>
#include <QShortcut>
#include <QKeySequence>
#include <QPushButton>
#include <QRegularExpressionValidator>
#include <QScreen>
#include <QSplitter>
#include <QTabWidget>
#include <QTimer>
#include <QUuid>
#include <QVBoxLayout>
#include <QVideoFrame>
#include <QVideoSink>
#include <QWidget>
#include <QSystemTrayIcon>
#include <QMenu>
#include <QAction>
#include <QEvent>
#include <QStyle>

static QImage drawCursorOnFrame(const QImage& source, const QRect& capturedGeometry);

TeacherWindow::TeacherWindow(QWidget* parent)
    : QMainWindow(parent),
      ws_(new WsClient(this)),
      rtc_(new RtcRelayClient(ws_, this)),
      audio_(new AudioPipe(this)),
      systemAudio_(new SystemAudioCapture(this)),
      codec_(new MediaCodec),
      screenTimer_(new QTimer(this)),
      lessonTimer_(new QTimer(this)),
      taskTimer_(new QTimer(this)),
      broadcastElapsedTimer_(new QTimer(this)),
      network_(new QNetworkAccessManager(this)) {

    auto* root = new QWidget;
    auto* outer = new QVBoxLayout(root);
    outer->setContentsMargins(6, 5, 6, 5);
    outer->setSpacing(4);
    root->setStyleSheet(
        "QPushButton{min-height:24px;padding:3px 7px;}"
        "QComboBox,QLineEdit,QSpinBox{min-height:24px;}"
        "QTabBar::tab{padding:5px 10px;}"
    );
    setMinimumSize(760, 520);

    config_ = ClientConfig::load("Преподаватель");

    invite_ = new QLineEdit(this);
    invite_->hide();
    server_ = new QLineEdit(this);
    server_->hide();
    lesson_ = new QLineEdit(config_.lessonId, this);
    lesson_->hide();
    token_ = new QLineEdit(this);
    token_->hide();
    teacherName_ = new QLineEdit(config_.displayName, this);
    teacherName_->hide();

    auto* loginCard = new QWidget;
    loginCard->setObjectName("sciCard");
    auto* loginLayout = new QHBoxLayout(loginCard);
    loginLayout->setContentsMargins(12, 8, 12, 8);
    loginLayout->setSpacing(6);

    auto* loginTitle = new QLabel("<b>CLASSROOM / TEACHER</b>");
    loginTitle->setObjectName("sciEyebrow");
    pin_ = new QLineEdit;
    pin_->setPlaceholderText("Числовой пароль");
    pin_->setText(config_.pin);
    pin_->setEchoMode(QLineEdit::Password);
    pin_->setMaxLength(10);
    pin_->setMinimumWidth(130);
    pin_->setMaximumWidth(170);
    pin_->setValidator(new QRegularExpressionValidator(QRegularExpression("\\d{0,10}"), pin_));

    auto* loginBtn = new QPushButton("Войти");
    loginBtn->setProperty("accent", "primary");
    auto* settingsBtn = new QPushButton("⚙ Настройки");

    loginLayout->addWidget(loginTitle);
    loginLayout->addStretch();
    loginLayout->addWidget(new QLabel("Пароль:"));
    loginLayout->addWidget(pin_);
    loginLayout->addWidget(loginBtn);
    loginLayout->addWidget(settingsBtn);
    outer->addWidget(loginCard);

    connect(loginBtn, &QPushButton::clicked, this, &TeacherWindow::loginWithPIN);
    connect(pin_, &QLineEdit::returnPressed, this, &TeacherWindow::loginWithPIN);
    connect(settingsBtn, &QPushButton::clicked, this, &TeacherWindow::editConnectionSettings);

    auto* toolbar = new QGridLayout;
    auto* screenBtn = new QPushButton("🖥 Экран");
    auto* cameraBtn = new QPushButton("🎥 Камера");
    auto* fileBtn = new QPushButton("▶ Видео");
    auto* presentationBtn = new QPushButton("📊 PPT/PDF");
    auto* regionBtn = new QPushButton("▱ Область");
    auto* tvBtn = new QPushButton("📺 ТВ");
    auto* screenshotTaskBtn = new QPushButton("🖼 Скрин");
    auto* localServerBtn = new QPushButton("📡 LAN");
    auto* stopLectureBtn = new QPushButton("⏹ СТОП");
    stopLectureBtn->hide();
    broadcastToggleButton_ = new QPushButton("▶ НАЧАТЬ ТРАНСЛЯЦИЮ");
    broadcastToggleButton_->setMinimumHeight(30);
    broadcastToggleButton_->setStyleSheet("QPushButton{font-weight:900;padding:5px 10px;border-radius:8px;background:#16a34a;color:white;border:1px solid #22c55e;} QPushButton:hover{background:#15803d;}");
    micButton_ = new QPushButton("🎤 Микрофон");

    cameraCombo_ = new QComboBox;
    micCombo_ = new QComboBox;
    cameraCombo_->setMinimumWidth(110);
    micCombo_->setMinimumWidth(110);

    QSettings deviceSettings("SHKI", "ClassroomRemoteTeacher");
    const QByteArray savedCameraId = deviceSettings.value("devices/camera_id").toByteArray();
    const QByteArray savedMicId = deviceSettings.value("devices/microphone_id").toByteArray();

    int savedCameraIndex = -1;
    const auto cameras = QMediaDevices::videoInputs();
    for (int i = 0; i < cameras.size(); ++i) {
        cameraCombo_->addItem(cameras[i].description(), cameras[i].id());
        if (cameras[i].id() == savedCameraId)
            savedCameraIndex = i;
    }
    if (savedCameraIndex >= 0)
        cameraCombo_->setCurrentIndex(savedCameraIndex);

    int savedMicIndex = -1;
    const auto microphones = QMediaDevices::audioInputs();
    for (int i = 0; i < microphones.size(); ++i) {
        micCombo_->addItem(microphones[i].description(), microphones[i].id());
        if (microphones[i].id() == savedMicId)
            savedMicIndex = i;
    }
    if (savedMicIndex >= 0)
        micCombo_->setCurrentIndex(savedMicIndex);

    connect(cameraCombo_, &QComboBox::currentIndexChanged, this, [this](int) {
        QSettings s("SHKI", "ClassroomRemoteTeacher");
        s.setValue("devices/camera_id", cameraCombo_->currentData().toByteArray());
    });
    connect(micCombo_, &QComboBox::currentIndexChanged, this, [this](int) {
        QSettings s("SHKI", "ClassroomRemoteTeacher");
        s.setValue("devices/microphone_id", micCombo_->currentData().toByteArray());
    });
    connect(presentationBtn, &QPushButton::clicked, this, [this] {
        const QString file = QFileDialog::getOpenFileName(
            this,
            "Выберите презентацию",
            QString(),
            "Презентации (*.ppt *.pptx *.pdf);;PowerPoint (*.ppt *.pptx);;PDF (*.pdf)");
        if (file.isEmpty())
            return;

        const QFileInfo info(file);
        const QString suffix = info.suffix().toLower();
        bool launched = false;

#ifdef Q_OS_WIN
        if (suffix == "ppt" || suffix == "pptx") {
            const QString powerPoint = QStandardPaths::findExecutable("POWERPNT.EXE");
            if (!powerPoint.isEmpty())
                launched = QProcess::startDetached(powerPoint, {"/S", QDir::toNativeSeparators(file)});
        }
#endif
        if (!launched)
            launched = QDesktopServices::openUrl(QUrl::fromLocalFile(file));

        if (!launched) {
            QMessageBox::warning(this, "Презентация", "Не удалось открыть файл презентации.");
            return;
        }

        status_->setText("Открываю презентацию…");
        QTimer::singleShot(1800, this, [this, base = info.completeBaseName()] {
            refreshCaptureSources();
            int match = -1;
            for (int i = 0; i < sourceCombo_->count(); ++i) {
                const QString text = sourceCombo_->itemText(i);
                if (text.contains(base, Qt::CaseInsensitive) ||
                    text.contains("PowerPoint", Qt::CaseInsensitive) ||
                    text.contains("Slide Show", Qt::CaseInsensitive) ||
                    text.contains("Слайд", Qt::CaseInsensitive)) {
                    match = i;
                    break;
                }
            }

            if (match >= 0) {
                sourceCombo_->setCurrentIndex(match);
                selectedBroadcastMode_ = "screen";
                status_->setText("Презентация выбрана как источник. Нажмите «НАЧАТЬ ТРАНСЛЯЦИЮ».");
            } else {
                status_->setText("Презентация открыта. Выберите её окно в списке «Источник» и нажмите «Экран».");
            }
        });
    });

    connect(regionBtn, &QPushButton::clicked, this, [this] {
        const auto screens = QGuiApplication::screens();
        if (screens.isEmpty())
            return;

        QScreen* screen = QGuiApplication::primaryScreen();
        const QString data = sourceCombo_ ? sourceCombo_->currentData().toString() : QString();
        if (data.startsWith("screen:")) {
            bool ok = false;
            const int index = data.mid(7).toInt(&ok);
            if (ok && index >= 0 && index < screens.size())
                screen = screens[index];
        }

        const int screenIndex = screens.indexOf(screen);
        new RegionSelector(screen, [this, screenIndex](const QRect& region) {
            const QString data = QString("area:%1:%2:%3:%4:%5")
                .arg(screenIndex)
                .arg(region.x())
                .arg(region.y())
                .arg(region.width())
                .arg(region.height());
            const QString label = QString("▱ Область %1×%2 (%3,%4)")
                .arg(region.width()).arg(region.height()).arg(region.x()).arg(region.y());

            const int existing = sourceCombo_->findData(data);
            if (existing < 0)
                sourceCombo_->addItem(label, data);

            const int idx = sourceCombo_->findData(data);
            if (idx >= 0)
                sourceCombo_->setCurrentIndex(idx);

            QSettings s("SHKI", "ClassroomRemoteTeacher");
            s.setValue("capture/area_source", data);
            s.setValue("capture/area_label", label);
            selectedBroadcastMode_ = "screen";
            status_->setText("Область экрана выбрана. Нажмите «НАЧАТЬ ТРАНСЛЯЦИЮ».");
        }, this);
    });


    toolbar->setHorizontalSpacing(4);
    toolbar->setVerticalSpacing(3);

    broadcastStatus_ = new QLabel("● ТРАНСЛЯЦИЯ ВЫКЛ");
    broadcastStatus_->setAlignment(Qt::AlignCenter);
    broadcastStatus_->setMinimumWidth(150);
    broadcastStatus_->setStyleSheet("QLabel{background:#311217;color:#ff8a98;font-weight:800;padding:5px 8px;border:1px solid #7f1d2d;border-radius:8px;}");
    toolbar->addWidget(broadcastStatus_, 0, 0);
    toolbar->addWidget(broadcastToggleButton_, 0, 1);
    toolbar->addWidget(screenBtn, 0, 2);
    toolbar->addWidget(cameraBtn, 0, 3);
    toolbar->addWidget(fileBtn, 0, 4);
    toolbar->addWidget(presentationBtn, 0, 5);
    toolbar->addWidget(regionBtn, 0, 6);

    toolbar->addWidget(tvBtn, 1, 0);
    toolbar->addWidget(screenshotTaskBtn, 1, 1);
    toolbar->addWidget(localServerBtn, 1, 2);
    toolbar->addWidget(stopLectureBtn, 1, 3);
    toolbar->addWidget(micButton_, 1, 4);
    toolbar->setColumnStretch(7, 1);
    outer->addLayout(toolbar);

    auto* devicesRow = new QHBoxLayout;
    devicesRow->setSpacing(5);
    devicesRow->addWidget(new QLabel("Камера:"));
    devicesRow->addWidget(cameraCombo_, 1);
    devicesRow->addWidget(new QLabel("Микрофон:"));
    devicesRow->addWidget(micCombo_, 1);
    outer->addLayout(devicesRow);

    auto* broadcastRow = new QHBoxLayout;
    sourceCombo_ = new QComboBox;
    qualityCombo_ = new QComboBox;
    auto* refreshSourcesBtn = new QPushButton("↻");
    auto* previewSourceBtn = new QPushButton("👁");
    sourceCombo_->setMinimumWidth(150);
    qualityCombo_->addItem("Авто / 720p · 10 FPS", "1280x720@10");
    qualityCombo_->addItem("480p · 10 FPS", "854x480@10");
    qualityCombo_->addItem("720p · 5 FPS", "1280x720@5");
    qualityCombo_->addItem("720p · 15 FPS", "1280x720@15");
    qualityCombo_->addItem("1080p · 15 FPS", "1920x1080@15");
    qualityCombo_->addItem("1080p · 30 FPS", "1920x1080@30");

    micVolume_ = new QSlider(Qt::Horizontal);
    micVolume_->setRange(0, 200);
    micVolume_->setValue(deviceSettings.value("audio/mic_volume", 100).toInt());
    micVolume_->setMaximumWidth(95);
    micLevel_ = new QProgressBar;
    micLevel_->setRange(0, 100);
    micLevel_->setValue(0);
    micLevel_->setTextVisible(false);
    micLevel_->setMaximumWidth(80);
    muteButton_ = new QPushButton("🔊 Mute");
    muteButton_->setCheckable(true);
    systemAudioCheck_ = new QCheckBox("Звук компьютера");
    systemAudioCheck_->setChecked(deviceSettings.value("audio/system_sound", false).toBool());
    connect(systemAudioCheck_, &QCheckBox::toggled, this, [](bool checked) {
        QSettings s("SHKI", "ClassroomRemoteTeacher");
        s.setValue("audio/system_sound", checked);
    });

    refreshCaptureSources();
    connect(refreshSourcesBtn, &QPushButton::clicked, this, &TeacherWindow::refreshCaptureSources);
    connect(sourceCombo_, &QComboBox::currentIndexChanged, this, [this](int) {
        selectedBroadcastMode_ = "screen";
    });
    connect(previewSourceBtn, &QPushButton::clicked, this, [this] {
        if (!sourceCombo_) return;
        const QString data = sourceCombo_->currentData().toString();
        if (data.startsWith("screen:")) {
            bool ok=false;
            const int index=data.mid(7).toInt(&ok);
            const auto screens=QGuiApplication::screens();
            if (ok && index>=0 && index<screens.size())
                showLocalPreview(screens[index]->grabWindow(0).toImage());
            return;
        }
        if (data.startsWith("area:")) {
            const QStringList p = data.split(':');
            if (p.size() == 6) {
                bool ok0=false, ok1=false, ok2=false, ok3=false, ok4=false;
                const int index=p[1].toInt(&ok0);
                const int x=p[2].toInt(&ok1);
                const int y=p[3].toInt(&ok2);
                const int w=p[4].toInt(&ok3);
                const int h=p[5].toInt(&ok4);
                const auto screens=QGuiApplication::screens();
                if(ok0&&ok1&&ok2&&ok3&&ok4&&index>=0&&index<screens.size())
                    showLocalPreview(screens[index]->grabWindow(0, x, y, w, h).toImage());
            }
            return;
        }
        if (data.startsWith("window:")) {
            bool ok=false;
            const int index=data.mid(7).toInt(&ok);
            const auto windows=QWindowCapture::capturableWindows();
            if (!ok || index<0 || index>=windows.size()) return;
            auto* wc = new QWindowCapture(this);
            auto* session = new QMediaCaptureSession(this);
            auto* sink = new QVideoSink(this);
            session->setWindowCapture(wc);
            session->setVideoSink(sink);
            connect(sink, &QVideoSink::videoFrameChanged, this,
                    [this, wc, session, sink](const QVideoFrame& frame) {
                const QImage image=frame.toImage();
                if(image.isNull()) return;
                showLocalPreview(image);
                wc->stop();
                sink->deleteLater();
                session->deleteLater();
                wc->deleteLater();
            });
            wc->setWindow(windows[index]);
            wc->start();
        }
    });
    connect(qualityCombo_, &QComboBox::currentIndexChanged, this, [this](int) {
        applySelectedQuality();
    });
    connect(micVolume_, &QSlider::valueChanged, this, [](int value) {
        QSettings s("SHKI", "ClassroomRemoteTeacher");
        s.setValue("audio/mic_volume", value);
    });
    connect(muteButton_, &QPushButton::toggled, this, [this](bool checked) {
        micMuted_ = checked;
        muteButton_->setText(checked ? "🔇 Muted" : "🔊 Mute");
        updateOperationalStatus();
    });
    connect(audio_, &AudioPipe::levelChanged, this, [this](int level) {
        if (micLevel_)
            micLevel_->setValue(level);
    });

    broadcastRow->setSpacing(5);
    broadcastRow->addWidget(new QLabel("Источник:"));
    broadcastRow->addWidget(sourceCombo_, 1);
    broadcastRow->addWidget(refreshSourcesBtn);
    broadcastRow->addWidget(previewSourceBtn);
    broadcastRow->addWidget(new QLabel("Качество:"));
    broadcastRow->addWidget(qualityCombo_);
    broadcastRow->addWidget(new QLabel("Громкость:"));
    broadcastRow->addWidget(micVolume_);
    broadcastRow->addWidget(muteButton_);
    broadcastRow->addWidget(systemAudioCheck_);
    broadcastRow->addWidget(new QLabel("Уровень:"));
    broadcastRow->addWidget(micLevel_);
    outer->addLayout(broadcastRow);

    auto* liveStatusRow = new QHBoxLayout;
    liveStatusRow->setSpacing(4);
    serverStateLabel_ = new QLabel("Сервер ○");
    rtcStateLabel_ = new QLabel("WebRTC ○");
    studentsStateLabel_ = new QLabel("Ученики 0");
    micStateLabel_ = new QLabel("Микрофон ○");
    broadcastTimeLabel_ = new QLabel("Трансляция 00:00:00");
    for (auto* label : {serverStateLabel_, rtcStateLabel_, studentsStateLabel_, micStateLabel_, broadcastTimeLabel_}) {
        label->setStyleSheet("QLabel{background:#111827;color:#cbd5e1;padding:4px 7px;border:1px solid #334155;border-radius:7px;font-size:11px;font-weight:700;}");
        liveStatusRow->addWidget(label);
    }
    liveStatusRow->addStretch();
    outer->addLayout(liveStatusRow);

    broadcastElapsedTimer_->setInterval(1000);
    connect(broadcastElapsedTimer_, &QTimer::timeout, this, [this] {
        if (broadcastActive_)
            ++broadcastElapsedSeconds_;
        updateOperationalStatus();
    });

    auto* lessonTools = new QHBoxLayout;
    lessonTimerLabel_ = new QLabel("Урок: 00:00:00");
    auto* lessonTimerStart = new QPushButton("▶ Таймер урока");
    auto* lessonTimerReset = new QPushButton("↺ Сброс");
    taskMinutes_ = new QSpinBox;
    taskMinutes_->setRange(1, 180);
    taskMinutes_->setValue(10);
    taskTimerLabel_ = new QLabel("Задание: --:--");
    auto* taskTimerStart = new QPushButton("⏱ Старт задания");
    auto* attentionBtn = new QPushButton("⚠ Внимание");
    auto* pollYesNoBtn = new QPushButton("Да / Нет");
    auto* pollUnderstandBtn = new QPushButton("Понятно?");
    auto* pollABCD = new QPushButton("A/B/C/D");

    lessonTools->setSpacing(5);
    lessonTools->addWidget(lessonTimerLabel_);
    lessonTools->addWidget(lessonTimerStart);
    lessonTools->addWidget(lessonTimerReset);
    lessonTools->addSpacing(12);
    lessonTools->addWidget(new QLabel("Мин:"));
    lessonTools->addWidget(taskMinutes_);
    lessonTools->addWidget(taskTimerStart);
    lessonTools->addWidget(taskTimerLabel_);
    lessonTools->addSpacing(12);
    lessonTools->addWidget(attentionBtn);
    lessonTools->addWidget(new QLabel("Опрос:"));
    lessonTools->addWidget(pollYesNoBtn);
    lessonTools->addWidget(pollUnderstandBtn);
    lessonTools->addWidget(pollABCD);
    lessonTools->addStretch();
    outer->addLayout(lessonTools);

    lessonTimer_->setInterval(1000);
    taskTimer_->setInterval(1000);
    connect(lessonTimerStart, &QPushButton::clicked, this, [this, lessonTimerStart] {
        if (lessonTimer_->isActive()) {
            lessonTimer_->stop();
            lessonTimerStart->setText("▶ Продолжить");
        } else {
            lessonTimer_->start();
            lessonTimerStart->setText("⏸ Пауза");
        }
    });
    connect(lessonTimerReset, &QPushButton::clicked, this, [this] {
        lessonElapsedSeconds_ = 0;
        lessonTimerLabel_->setText("Урок: 00:00:00");
    });
    connect(lessonTimer_, &QTimer::timeout, this, [this] {
        ++lessonElapsedSeconds_;
        const int h = lessonElapsedSeconds_ / 3600;
        const int m = (lessonElapsedSeconds_ % 3600) / 60;
        const int s = lessonElapsedSeconds_ % 60;
        lessonTimerLabel_->setText(QString("Урок: %1:%2:%3")
            .arg(h,2,10,QChar('0')).arg(m,2,10,QChar('0')).arg(s,2,10,QChar('0')));
    });
    connect(taskTimerStart, &QPushButton::clicked, this, [this] {
        taskRemainingSeconds_ = taskMinutes_->value() * 60;
        taskTimer_->start();
        if (ws_->isConnected()) {
            ws_->send(QJsonObject{
                {"type", "task_timer"},
                {"active", true},
                {"seconds", taskRemainingSeconds_}
            });
        }
    });
    connect(taskTimer_, &QTimer::timeout, this, [this] {
        if (taskRemainingSeconds_ <= 0) {
            taskTimer_->stop();
            taskTimerLabel_->setText("Задание: время вышло");
            QApplication::beep();
            return;
        }
        --taskRemainingSeconds_;
        taskTimerLabel_->setText(QString("Задание: %1:%2")
            .arg(taskRemainingSeconds_/60,2,10,QChar('0'))
            .arg(taskRemainingSeconds_%60,2,10,QChar('0')));
    });

    connect(attentionBtn, &QPushButton::clicked, this, [this] {
        bool ok=false;
        const QString text=QInputDialog::getText(this, "Внимание всем",
            "Сообщение:", QLineEdit::Normal, "Смотрим на экран преподавателя", &ok).trimmed();
        if(ok && !text.isEmpty())
            ws_->send(QJsonObject{{"type","attention"},{"active",true},{"text",text}});
    });
    connect(pollYesNoBtn, &QPushButton::clicked, this, [this] {
        launchQuickPoll("Ваш ответ?", {"Да","Нет"});
    });
    connect(pollUnderstandBtn, &QPushButton::clicked, this, [this] {
        launchQuickPoll("Материал понятен?", {"Понятно","Не понятно"});
    });
    connect(pollABCD, &QPushButton::clicked, this, [this] {
        launchQuickPoll("Выберите вариант", {"A","B","C","D"});
    });

    auto* recordToolbar = new QHBoxLayout;
    auto* recordCameraBtn = new QPushButton("⏺ Камера");
    auto* recordScreenBtn = new QPushButton("⏺ Экран");
    auto* stopRecordBtn = new QPushButton("⏹ Стоп");
    recordToolbar->setSpacing(5);
    recordToolbar->addWidget(new QLabel("<b>Запись:</b>"));
    recordToolbar->addWidget(recordCameraBtn);
    recordToolbar->addWidget(recordScreenBtn);
    recordToolbar->addWidget(stopRecordBtn);
    recordToolbar->addStretch();
    outer->addLayout(recordToolbar);

    status_ = new QLabel("Не подключено");
    status_->setStyleSheet("QLabel{color:#94a3b8;font-size:11px;padding:1px 4px;}");
    outer->addWidget(status_);

    auto* split = new QSplitter;

    auto* tabs = new QTabWidget;

    auto* previewPage = new QWidget;
    auto* previewLayout = new QVBoxLayout(previewPage);
    auto* annotationBar = new QHBoxLayout;
    auto* annOff = new QPushButton("Курсор");
    auto* annMarker = new QPushButton("Маркер");
    auto* annArrow = new QPushButton("Стрелка");
    auto* annRect = new QPushButton("Прямоугольник");
    auto* annText = new QPushButton("Текст");
    auto* annLaser = new QPushButton("Лазер");
    auto* annClear = new QPushButton("Очистить");
    annotationBar->addWidget(new QLabel("<b>Аннотации:</b>"));
    annotationBar->addWidget(annOff);
    annotationBar->addWidget(annMarker);
    annotationBar->addWidget(annArrow);
    annotationBar->addWidget(annRect);
    annotationBar->addWidget(annText);
    annotationBar->addWidget(annLaser);
    annotationBar->addWidget(annClear);
    annotationBar->addStretch();
    previewLayout->addLayout(annotationBar);

    auto* previewStackHost = new QWidget;
    auto* previewStack = new QStackedLayout(previewStackHost);
    previewStack->setStackingMode(QStackedLayout::StackAll);
    lecturePreview_ = new QLabel("Предпросмотр лекции");
    lecturePreview_->setAlignment(Qt::AlignCenter);
    lecturePreview_->setMinimumSize(420, 236);
    lecturePreview_->setStyleSheet("background:#0c0f16;color:#f5f7ff;font-size:22px;border:1px solid rgba(255,255,255,28);border-radius:18px;");
    annotationOverlay_ = new AnnotationOverlay;
    annotationOverlay_->setEditable(true);
    previewStack->addWidget(lecturePreview_);
    previewStack->addWidget(annotationOverlay_);
    previewStack->setCurrentWidget(annotationOverlay_);
    annotationOverlay_->raise();
    previewLayout->addWidget(previewStackHost, 1);
    tabs->addTab(previewPage, "Моя трансляция");

    remoteView_ = new RemoteView;
    tabs->addTab(remoteView_, "Экран ученика");

    lessonsWidget_ = new TeacherLessonsWidget;
    tabs->addTab(lessonsWidget_, "Уроки и ДЗ");

    auto* interactivePage = new QWidget;
    auto* interactiveLayout = new QVBoxLayout(interactivePage);

    auto* pollRow = new QHBoxLayout;
    auto* pollBtn = new QPushButton("Запустить быстрый опрос");
    auto* pollResultsBtn = new QPushButton("Результаты опроса");
    pollStatus_ = new QLabel("Опрос не запущен");
    pollStatus_->setWordWrap(true);
    pollRow->addWidget(pollBtn);
    pollRow->addWidget(pollResultsBtn);
    pollRow->addWidget(pollStatus_, 1);
    interactiveLayout->addLayout(pollRow);

    auto* chatControls = new QHBoxLayout;
    chatToggle_ = new QPushButton("Общий чат: ВКЛ");
    auto* quickFileBtn = new QPushButton("Отправить файл сейчас");
    auto* attendanceBtn = new QPushButton("Посещаемость");
    chatControls->addWidget(chatToggle_);
    chatControls->addWidget(quickFileBtn);
    chatControls->addWidget(attendanceBtn);
    chatControls->addStretch();
    interactiveLayout->addLayout(chatControls);

    chatLog_ = new QPlainTextEdit;
    chatLog_->setReadOnly(true);
    chatLog_->setPlaceholderText("Сообщения урока");
    interactiveLayout->addWidget(chatLog_, 1);

    auto* chatRow = new QHBoxLayout;
    chatInput_ = new QLineEdit;
    chatInput_->setPlaceholderText("Сообщение...");
    auto* sendAllBtn = new QPushButton("Всем");
    auto* sendPrivateBtn = new QPushButton("Выбранному");
    chatRow->addWidget(chatInput_, 1);
    chatRow->addWidget(sendAllBtn);
    chatRow->addWidget(sendPrivateBtn);
    interactiveLayout->addLayout(chatRow);

    tabs->addTab(interactivePage, "Интерактив");

    auto* right = new QWidget;
    auto* rv = new QVBoxLayout(right);
    rv->addWidget(new QLabel("<b>Выберите ученика</b>"));

    selectedStudentLabel_ = new QLabel("Никто не выбран");
    selectedStudentLabel_->setWordWrap(true);
    selectedStudentLabel_->setStyleSheet(
        "background:#0d1017;color:#cfd5e2;padding:10px;border:1px solid rgba(255,255,255,28);border-radius:12px;");
    rv->addWidget(selectedStudentLabel_);

    students_ = new QListWidget;
    students_->setSelectionMode(QAbstractItemView::NoSelection);
    students_->setSpacing(2);
    studentButtons_ = new QButtonGroup(this);
    studentButtons_->setExclusive(true);
    rv->addWidget(students_, 1);

    auto* viewBtn = new QPushButton("Посмотреть экран");
    auto* controlBtn = new QPushButton("Управлять");
    auto* stopBtn = new QPushButton("Отключиться от ученика");
    auto* audioBtn = new QPushButton("Аудиодиалог");
    auto* downloadBtn = new QPushButton("Скачать запись MP4");
    auto* downloadZipBtn = new QPushButton("Скачать кадры ZIP");

    screenBtn->setProperty("accent", "cyan");
    cameraBtn->setProperty("accent", "cyan");
    fileBtn->setProperty("accent", "primary");
    stopLectureBtn->setProperty("accent", "danger");
    recordCameraBtn->setProperty("accent", "primary");
    recordScreenBtn->setProperty("accent", "primary");
    stopRecordBtn->setProperty("accent", "danger");
    viewBtn->setProperty("accent", "cyan");
    controlBtn->setProperty("accent", "primary");
    audioBtn->setProperty("accent", "success");
    stopBtn->setProperty("accent", "danger");
    rv->addWidget(viewBtn);
    rv->addWidget(controlBtn);
    rv->addWidget(audioBtn);
    rv->addWidget(downloadBtn);
    rv->addWidget(downloadZipBtn);
    rv->addWidget(stopBtn);

    split->addWidget(tabs);
    split->addWidget(right);
    split->setStretchFactor(0, 4);
    split->setStretchFactor(1, 1);
    outer->addWidget(split, 1);

    setCentralWidget(root);
    setWindowTitle("Classroom Teacher");

    if (QSystemTrayIcon::isSystemTrayAvailable()) {
        trayIcon_ = new QSystemTrayIcon(this);
        QIcon trayIcon = windowIcon();
        if (trayIcon.isNull())
            trayIcon = style()->standardIcon(QStyle::SP_ComputerIcon);
        trayIcon_->setIcon(trayIcon);
        trayIcon_->setToolTip("Classroom Teacher");

        auto* trayMenu = new QMenu(this);
        trayStatusAction_ = trayMenu->addAction("🟡 0 учеников · без трансляции");
        trayStatusAction_->setEnabled(false);
        trayMenu->addSeparator();
        auto* showAction = trayMenu->addAction("Показать");
        auto* hideAction = trayMenu->addAction("Скрыть в трей");
        trayMenu->addSeparator();
        trayBroadcastAction_ = trayMenu->addAction("▶ Начать трансляцию");
        trayMuteAction_ = trayMenu->addAction("🎤 Включить микрофон");
        trayMenu->addSeparator();
        auto* quitAction = trayMenu->addAction("Выход");
        trayIcon_->setContextMenu(trayMenu);

        connect(showAction, &QAction::triggered, this, &TeacherWindow::restoreFromTray);
        connect(hideAction, &QAction::triggered, this, &QWidget::hide);
        connect(trayBroadcastAction_, &QAction::triggered, this, [this] {
            if (broadcastToggleButton_)
                broadcastToggleButton_->click();
        });
        connect(trayMuteAction_, &QAction::triggered, this, &TeacherWindow::toggleMicrophoneFromTray);
        connect(quitAction, &QAction::triggered, qApp, &QCoreApplication::quit);
        connect(trayIcon_, &QSystemTrayIcon::activated, this,
                [this](QSystemTrayIcon::ActivationReason reason) {
            if (reason == QSystemTrayIcon::Trigger ||
                reason == QSystemTrayIcon::DoubleClick)
                restoreFromTray();
        });
        trayIcon_->show();
        updateTrayStatus();
    }

    if (!codec_->initialize())
        status_->setText("Ошибка инициализации OpenH264/Opus");

    screenTimer_->setInterval(100);

    connect(screenBtn, &QPushButton::clicked, this, [this] {
        selectedBroadcastMode_ = "screen";
        status_->setText("Источник выбран: экран/окно/область. Нажмите «НАЧАТЬ ТРАНСЛЯЦИЮ».");
    });
    connect(cameraBtn, &QPushButton::clicked, this, [this] {
        selectedBroadcastMode_ = "camera";
        status_->setText("Источник выбран: камера. Нажмите «НАЧАТЬ ТРАНСЛЯЦИЮ».");
    });
    connect(fileBtn, &QPushButton::clicked, this, [this] {
        selectedBroadcastMode_ = "file";
        status_->setText("Источник выбран: видеофайл. Нажмите «НАЧАТЬ ТРАНСЛЯЦИЮ».");
    });
    connect(broadcastToggleButton_, &QPushButton::clicked, this, [this] {
        if (broadcastActive_) {
            stopLecture();
            return;
        }
        if (selectedBroadcastMode_ == "camera")
            startCameraLecture();
        else if (selectedBroadcastMode_ == "file")
            startFileLecture();
        else
            startScreenLecture();
    });
    connect(stopLectureBtn, &QPushButton::clicked, this, &TeacherWindow::stopLecture);
    connect(tvBtn, &QPushButton::clicked, this, &TeacherWindow::openTVWindow);
    connect(screenshotTaskBtn, &QPushButton::clicked, this, &TeacherWindow::sendScreenshotTask);
    connect(localServerBtn, &QPushButton::clicked, this, [this, localServerBtn] {
        if (localServerProcess_ && localServerProcess_->state() != QProcess::NotRunning) {
            stopLocalServer();
            localServerBtn->setText("📡 Локальный сервер");
        } else {
            startLocalServer();
            if (localServerProcess_ && localServerProcess_->state() != QProcess::NotRunning)
                localServerBtn->setText("⏹ Остановить LAN");
        }
    });
    connect(recordCameraBtn, &QPushButton::clicked, this, &TeacherWindow::startCameraRecording);
    connect(recordScreenBtn, &QPushButton::clicked, this, &TeacherWindow::startScreenRecording);
    connect(stopRecordBtn, &QPushButton::clicked, this, &TeacherWindow::stopRecording);

    connect(annOff, &QPushButton::clicked, this, [this] { annotationOverlay_->setTool(AnnotationOverlay::Tool::Off); });
    connect(annMarker, &QPushButton::clicked, this, [this] { annotationOverlay_->setTool(AnnotationOverlay::Tool::Marker); });
    connect(annArrow, &QPushButton::clicked, this, [this] { annotationOverlay_->setTool(AnnotationOverlay::Tool::Arrow); });
    connect(annRect, &QPushButton::clicked, this, [this] { annotationOverlay_->setTool(AnnotationOverlay::Tool::Rectangle); });
    connect(annText, &QPushButton::clicked, this, [this] { annotationOverlay_->setTool(AnnotationOverlay::Tool::Text); });
    connect(annLaser, &QPushButton::clicked, this, [this] { annotationOverlay_->setTool(AnnotationOverlay::Tool::Laser); });
    connect(annClear, &QPushButton::clicked, this, [this] {
        annotationOverlay_->clearAll();
        ws_->send(QJsonObject{{"type", "annotation_clear"}});
    });
    connect(annotationOverlay_, &AnnotationOverlay::annotationCreated, this,
            [this](const QJsonObject& obj) { ws_->send(obj); });

    connect(pollBtn, &QPushButton::clicked, this, &TeacherWindow::startPoll);
    connect(pollResultsBtn, &QPushButton::clicked, this, &TeacherWindow::showPollResults);
    connect(quickFileBtn, &QPushButton::clicked, this, &TeacherWindow::uploadQuickFile);
    connect(attendanceBtn, &QPushButton::clicked, this, &TeacherWindow::showAttendance);
    connect(chatToggle_, &QPushButton::clicked, this, [this] {
        publicChatEnabled_ = !publicChatEnabled_;
        chatToggle_->setText(publicChatEnabled_ ? "Общий чат: ВКЛ" : "Общий чат: ВЫКЛ");
        ws_->send(QJsonObject{{"type", "chat_control"}, {"enabled", publicChatEnabled_}});
    });
    connect(sendAllBtn, &QPushButton::clicked, this, [this] { sendChat(false); });
    connect(sendPrivateBtn, &QPushButton::clicked, this, [this] { sendChat(true); });
    connect(chatInput_, &QLineEdit::returnPressed, this, [this] { sendChat(false); });

    connect(micButton_, &QPushButton::clicked, this, [this] {
        if (!privateAudioTarget_.isEmpty()) {
            QMessageBox::information(this, "Микрофон",
                "Сначала завершите приватный аудиодиалог.");
            return;
        }
        micOn_ = !micOn_;
        if (micOn_) {
            QAudioDevice selectedMic;
            const QByteArray wanted = micCombo_->currentData().toByteArray();
            for (const auto& d : QMediaDevices::audioInputs()) {
                if (d.id() == wanted) {
                    selectedMic = d;
                    break;
                }
            }
            if (selectedMic.isNull())
                selectedMic = QMediaDevices::defaultAudioInput();

            if (!audio_->startCapture(selectedMic)) {
                micOn_ = false;
                QMessageBox::warning(this, "Микрофон", "Не удалось открыть выбранный микрофон.");
            }
        } else {
            audio_->stopCapture();
        }
        micButton_->setText(micOn_ ? "🎤 Микрофон: ВКЛ" : "🎤 Микрофон");
        updateOperationalStatus();
    });

    connect(systemAudio_, &SystemAudioCapture::captured, this, [this](const QByteArray& pcm) {
        if (privateAudioTarget_.isEmpty() && micOn_) {
            systemAudioBuffer_.append(pcm);
            const int maxBytes = 48000 * 2 * 2;
            if (systemAudioBuffer_.size() > maxBytes)
                systemAudioBuffer_.remove(0, systemAudioBuffer_.size() - maxBytes);
            return;
        }

        if (!privateAudioTarget_.isEmpty() || !systemAudioCheck_ || !systemAudioCheck_->isChecked() || !rtc_->isOpen())
            return;

        const auto packets = codec_->encodeAudio(pcm, "lecture-system");
        for (const auto& packet : packets)
            rtc_->sendAudio(RtcRelayClient::LectureAudioTrack, packet, 20);
    });
    connect(systemAudio_, &SystemAudioCapture::errorText, this, [this](const QString& error) {
        status_->setText(error);
        if (systemAudioCheck_)
            systemAudioCheck_->setChecked(false);
    });

    connect(audio_, &AudioPipe::captured, this, [this](const QByteArray& pcm) {
        if (privateAudioTarget_.isEmpty() && !micOn_)
            return;

        QByteArray adjusted = pcm;
        auto* samples = reinterpret_cast<qint16*>(adjusted.data());
        const int count = adjusted.size() / int(sizeof(qint16));
        const double gain = micMuted_ ? 0.0 : (micVolume_ ? micVolume_->value() / 100.0 : 1.0);
        for (int i = 0; i < count; ++i) {
            const int scaled = qRound(double(samples[i]) * gain);
            samples[i] = qBound(-32768, scaled, 32767);
        }

        if (privateAudioTarget_.isEmpty() && systemAudioCheck_ && systemAudioCheck_->isChecked() &&
            systemAudioBuffer_.size() >= adjusted.size()) {
            QByteArray sys = systemAudioBuffer_.left(adjusted.size());
            systemAudioBuffer_.remove(0, adjusted.size());
            auto* sysSamples = reinterpret_cast<const qint16*>(sys.constData());
            for (int i = 0; i < count; ++i) {
                const int mixed = int(samples[i]) + int(sysSamples[i]);
                samples[i] = qBound(-32768, mixed, 32767);
            }
        }

        const QString track = !privateAudioTarget_.isEmpty()
            ? RtcRelayClient::PrivateAudioTrack
            : RtcRelayClient::LectureAudioTrack;
        const auto packets = codec_->encodeAudio(adjusted, track);
        for (const auto& packet : packets)
            rtc_->sendAudio(track, packet, 20);
    });

    connect(screenTimer_, &QTimer::timeout, this, [this] {
        if (!rtc_->isOpen() || !sourceCombo_)
            return;
        const QString data = sourceCombo_->currentData().toString();
        const auto screens=QGuiApplication::screens();

        if (data.startsWith("screen:")) {
            bool ok=false;
            const int index=data.mid(7).toInt(&ok);
            if (!ok || index < 0 || index >= screens.size())
                return;
            const QImage frame = screens[index]->grabWindow(0).toImage();
            sendLectureImage(drawCursorOnFrame(frame, screens[index]->geometry()));
            return;
        }

        if (data.startsWith("area:")) {
            const QStringList p = data.split(':');
            if (p.size() != 6)
                return;
            bool ok0=false, ok1=false, ok2=false, ok3=false, ok4=false;
            const int index=p[1].toInt(&ok0);
            const int x=p[2].toInt(&ok1);
            const int y=p[3].toInt(&ok2);
            const int w=p[4].toInt(&ok3);
            const int h=p[5].toInt(&ok4);
            if(!ok0||!ok1||!ok2||!ok3||!ok4||index<0||index>=screens.size())
                return;
            const QImage frame = screens[index]->grabWindow(0, x, y, w, h).toImage();
            const QRect area(screens[index]->geometry().topLeft() + QPoint(x, y), QSize(w, h));
            sendLectureImage(drawCursorOnFrame(frame, area));
        }
    });

    connect(viewBtn, &QPushButton::clicked, this, [this] {
        const auto id = selectedStudentId();
        if (id.isEmpty()) {
            QMessageBox::information(this, "Ученик", "Сначала выберите ученика.");
            return;
        }
        activeStudent_ = id;
        remoteView_->setTarget({});
        ws_->send(QJsonObject{{"type", "remote_request"}, {"target", id}, {"mode", "view"}});
    });
    connect(controlBtn, &QPushButton::clicked, this, [this] {
        const auto id = selectedStudentId();
        if (id.isEmpty()) {
            QMessageBox::information(this, "Ученик", "Сначала выберите ученика.");
            return;
        }
        activeStudent_ = id;
        remoteView_->setTarget(id);
        ws_->send(QJsonObject{{"type", "remote_request"}, {"target", id}, {"mode", "control"}});
        remoteView_->setFocus();
    });
    connect(stopBtn, &QPushButton::clicked, this, [this] {
        sendToSelected("remote_stop");
        activeStudent_.clear();
        remoteView_->setTarget({});
        remoteView_->setText("Экран выбранного ученика");
    });
    connect(audioBtn, &QPushButton::clicked, this, [this, audioBtn] {
        const auto id = selectedStudentId();
        if (id.isEmpty()) {
            QMessageBox::information(this, "Ученик", "Сначала выберите ученика.");
            return;
        }

        if (privateAudioTarget_ == id) {
            ws_->send(QJsonObject{{"type", "audio_stop"}, {"target", id}});
            privateAudioTarget_.clear();
            audio_->stopCapture();
            if (micOn_)
                audio_->startCapture();
            audioBtn->setText("Аудиодиалог");
            return;
        }

        if (!privateAudioTarget_.isEmpty())
            ws_->send(QJsonObject{{"type", "audio_stop"}, {"target", privateAudioTarget_}});

        privateAudioTarget_ = id;
        ws_->send(QJsonObject{{"type", "audio_request"}, {"target", id}});
        audio_->startPlayback();
        audio_->startCapture();
        audioBtn->setText("Завершить аудиодиалог");
    });
    connect(downloadBtn, &QPushButton::clicked, this, &TeacherWindow::downloadRecording);
    connect(downloadZipBtn, &QPushButton::clicked, this, &TeacherWindow::downloadFramesZip);

    connect(students_, &QListWidget::itemClicked, this, [this](QListWidgetItem* item) {
        if (!item) return;
        if (auto* row = students_->itemWidget(item)) {
            if (auto* radio = row->findChild<QRadioButton*>())
                radio->setChecked(true);
        }
    });

    connect(remoteView_, &RemoteView::remoteEvent, this, [this](const QJsonObject& e) {
        rtc_->sendText(e);
    });

    connect(ws_, &WsClient::connected, this, [this] {
        signalingConnected_ = true;
        status_->setText("● Сигналинг подключён, получаю ICE/TURN конфигурацию...");
        updateOperationalStatus();
    });
    connect(ws_, &WsClient::disconnected, this, [this] {
        signalingConnected_ = false;
        rtcReady_ = false;
        status_->setText("○ Связь потеряна — переподключаюсь автоматически...");
        rtc_->stop();
        updateOperationalStatus();
    });
    connect(ws_, &WsClient::errorText, this, [this](const QString& e) {
        status_->setText("Ошибка: " + e);
    });
    connect(ws_, &WsClient::message, this, &TeacherWindow::onMessage);

    connect(rtc_, &RtcRelayClient::opened, this, [this] {
        rtcReady_ = true;
        status_->setText("● WebRTC готов");
        updateOperationalStatus();
    });
    connect(rtc_, &RtcRelayClient::closed, this, [this] {
        rtcReady_ = false;
        updateOperationalStatus();
    });
    connect(rtc_, &RtcRelayClient::stateText, this, [this](const QString& s) {
        status_->setText(s);
    });
    connect(rtc_, &RtcRelayClient::videoFrameReceived, this, &TeacherWindow::onRtcVideo);
    connect(rtc_, &RtcRelayClient::audioFrameReceived, this, &TeacherWindow::onRtcAudio);
}

void TeacherWindow::toggleMicrophoneFromTray() {
    if (micButton_)
        micButton_->click();
}

void TeacherWindow::updateTrayStatus() {
    if (!trayIcon_)
        return;

    const QString mode = broadcastActive_ ? "Трансляция" : "без трансляции";
    const QString liveText = QString("%1 %2/%3 учеников · %4")
        .arg(signalingConnected_ ? "🟢" : "🟡")
        .arg(onlineStudentCount_)
        .arg(qMax(onlineStudentCount_, int(knownStudentIds_.size())))
        .arg(mode);
    trayIcon_->setToolTip(liveText);
    if (trayStatusAction_)
        trayStatusAction_->setText(liveText);

    if (trayBroadcastAction_)
        trayBroadcastAction_->setText(broadcastActive_
            ? "⏹ Остановить трансляцию"
            : "▶ Начать трансляцию");

    if (trayMuteAction_) {
        if (!micOn_)
            trayMuteAction_->setText("🎤 Включить микрофон");
        else if (micMuted_)
            trayMuteAction_->setText("🔊 Включить звук микрофона");
        else
            trayMuteAction_->setText("🔇 Выключить микрофон");
    }
}

void TeacherWindow::updateOperationalStatus() {
    const auto badge = [](bool ok, const QString& on, const QString& off) {
        return QString("%1 %2").arg(ok ? "●" : "○", ok ? on : off);
    };

    if (serverStateLabel_) {
        serverStateLabel_->setText(badge(signalingConnected_, "Сервер", "Сервер"));
        serverStateLabel_->setStyleSheet(signalingConnected_
            ? "QLabel{background:#052e1b;color:#86efac;padding:4px 7px;border:1px solid #16a34a;border-radius:7px;font-size:11px;font-weight:700;}"
            : "QLabel{background:#3f1d1d;color:#fca5a5;padding:4px 7px;border:1px solid #991b1b;border-radius:7px;font-size:11px;font-weight:700;}");
    }
    if (rtcStateLabel_) {
        rtcStateLabel_->setText(badge(rtcReady_, "WebRTC", "WebRTC"));
        rtcStateLabel_->setStyleSheet(rtcReady_
            ? "QLabel{background:#052e1b;color:#86efac;padding:4px 7px;border:1px solid #16a34a;border-radius:7px;font-size:11px;font-weight:700;}"
            : "QLabel{background:#3f2a0a;color:#fcd34d;padding:4px 7px;border:1px solid #a16207;border-radius:7px;font-size:11px;font-weight:700;}");
    }
    if (studentsStateLabel_)
        studentsStateLabel_->setText(QString("Ученики %1/%2")
            .arg(onlineStudentCount_)
            .arg(qMax(onlineStudentCount_, int(knownStudentIds_.size()))));
    if (micStateLabel_) {
        const QString micText = !micOn_ ? "Микрофон ○" : (micMuted_ ? "Микрофон 🔇" : "Микрофон ●");
        micStateLabel_->setText(micText);
    }
    if (broadcastTimeLabel_) {
        const int h = broadcastElapsedSeconds_ / 3600;
        const int m = (broadcastElapsedSeconds_ % 3600) / 60;
        const int s = broadcastElapsedSeconds_ % 60;
        broadcastTimeLabel_->setText(QString("Трансляция %1:%2:%3")
            .arg(h, 2, 10, QChar('0'))
            .arg(m, 2, 10, QChar('0'))
            .arg(s, 2, 10, QChar('0')));
    }

    updateTrayStatus();
}

void TeacherWindow::restoreFromTray() {
    showNormal();
    raise();
    activateWindow();
}

void TeacherWindow::changeEvent(QEvent* event) {
    QMainWindow::changeEvent(event);
    if (event->type() == QEvent::WindowStateChange && isMinimized() && trayIcon_) {
        QTimer::singleShot(0, this, [this] {
            hide();
            if (trayIcon_)
                trayIcon_->showMessage("Classroom Teacher",
                    "Приложение продолжает работать в системном трее.",
                    QSystemTrayIcon::Information, 1800);
        });
    }
}

TeacherWindow::~TeacherWindow() {
    stopRecording();
    stopLecture();
    stopLocalServer();
    delete codec_;
}

void TeacherWindow::openInviteUrl(const QUrl& rawUrl) {
    const QUrl inviteUrl = InviteLink::normalizeInviteUrl(rawUrl);
    const QUrl endpoint = InviteLink::resolveEndpoint(inviteUrl);
    if (!endpoint.isValid() || endpoint.scheme().isEmpty()) {
        QMessageBox::warning(this, "Ссылка", "Некорректная ссылка приглашения.");
        return;
    }

    invite_->setText(inviteUrl.toString());
    status_->setText("Проверяю ссылку приглашения...");

    auto* reply = network_->get(QNetworkRequest(endpoint));
    connect(reply, &QNetworkReply::finished, this, [this, reply] {
        const QByteArray body = reply->readAll();
        if (reply->error() != QNetworkReply::NoError) {
            QMessageBox::warning(this, "Ссылка",
                "Ссылка недействительна, отозвана или сервер недоступен.");
            status_->setText("Не удалось войти по ссылке");
            reply->deleteLater();
            return;
        }

        const QJsonDocument doc = QJsonDocument::fromJson(body);
        if (!doc.isObject()) {
            QMessageBox::warning(this, "Ссылка", "Сервер вернул некорректный ответ.");
            reply->deleteLater();
            return;
        }

        const QJsonObject obj = doc.object();
        if (obj.value("role").toString() != "teacher") {
            QMessageBox::warning(this, "Ссылка",
                "Это ссылка ученика. Откройте её через ClassroomStudent.");
            reply->deleteLater();
            return;
        }

        server_->setText(obj.value("ws_url").toString());
        lesson_->setText(obj.value("lesson_id").toString());
        token_->setText(obj.value("access_token").toString());
        lessonsWidget_->setMaxUploadMB(obj.value("max_upload_mb").toInt(50));
        status_->setText("Ссылка принята, подключаюсь...");
        reply->deleteLater();
        connectServer();
    });
}

void TeacherWindow::editConnectionSettings() {
    QString error;
    if (!config_.edit(this, "Настройки подключения", true, &error)) {
        if (!error.isEmpty())
            QMessageBox::warning(this, "Настройки", error);
        return;
    }
    lesson_->setText(config_.lessonId);
    teacherName_->setText(config_.displayName);
    status_->setText("Настройки сохранены: " + ClientConfig::filePath());
}

void TeacherWindow::loginWithPIN() {
    const QString pin = pin_->text().trimmed();
    if (pin.size() < 4) {
        QMessageBox::information(this, "Вход", "Введите числовой пароль.");
        pin_->setFocus();
        return;
    }

    QUrl url(config_.serverUrl);
    url.setPath("/api/client/login");
    url.setQuery(QString());
    url.setFragment(QString());

    QNetworkRequest req(url);
    req.setHeader(QNetworkRequest::ContentTypeHeader, "application/json");

    const QByteArray body = QJsonDocument(QJsonObject{
        {"role", "teacher"},
        {"pin", pin},
        {"lesson_id", config_.lessonId}
    }).toJson(QJsonDocument::Compact);

    status_->setText("Проверяю пароль...");
    auto* reply = network_->post(req, body);
    connect(reply, &QNetworkReply::finished, this, [this, reply, pin] {
        const QByteArray data = reply->readAll();
        if (reply->error() != QNetworkReply::NoError) {
            if (reply->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt() == 429)
                QMessageBox::warning(this, "Вход", "Слишком много попыток. Повторите через минуту.");
            else
                QMessageBox::warning(this, "Вход", "Неверный пароль или сервер недоступен.");
            status_->setText("Не удалось войти");
            pin_->selectAll();
            pin_->setFocus();
            reply->deleteLater();
            return;
        }

        const QJsonDocument doc = QJsonDocument::fromJson(data);
        if (!doc.isObject()) {
            QMessageBox::warning(this, "Вход", "Сервер вернул некорректный ответ.");
            reply->deleteLater();
            return;
        }
        const QJsonObject obj = doc.object();
        token_->setText(obj.value("access_token").toString());
        lesson_->setText(obj.value("lesson_id").toString(config_.lessonId));
        server_->setText(obj.value("ws_url").toString());
        
        teacherName_->setText(config_.displayName);
        config_.pin = pin;
        QString saveError;
        if (!config_.save(&saveError))
            status_->setText("PIN принят, но не удалось сохранить config.json: " + saveError);
        pin_->setText(config_.pin);
        status_->setText("Пароль принят, подключаюсь...");
        reply->deleteLater();
        connectServer();
    });
}

void TeacherWindow::connectServer() {
    QUrl wsUrl(server_->text());
    QUrl httpUrl = wsUrl;
    if (httpUrl.scheme() == "wss") httpUrl.setScheme("https");
    else if (httpUrl.scheme() == "ws") httpUrl.setScheme("http");
    httpUrl.setPath("");
    httpUrl.setQuery(QString());
    lessonsWidget_->setSession(httpUrl, lesson_->text(), token_->text());

    ws_->connectTo(wsUrl, lesson_->text(),
                   "teacher-" + QUuid::createUuid().toString(QUuid::WithoutBraces),
                   "teacher", teacherName_->text(), token_->text());
}

void TeacherWindow::onMessage(const QJsonObject& obj) {
    const auto type = obj.value("type").toString();
    if (type == "server_config") {
        lessonsWidget_->setMaxUploadMB(obj.value("max_upload_mb").toInt(50));
        QStringList iceServers;
        for (const auto& v : obj.value("ice_servers").toArray())
            iceServers << v.toString();
        status_->setText("● ICE/TURN получен, устанавливаю WebRTC...");
        rtc_->start("teacher", iceServers);
        return;
    }
    if (type == "presence") {
        const QString keepSelected = selectedStudentId_;
        students_->clear();
        for (auto* button : studentButtons_->buttons())
            studentButtons_->removeButton(button);
        names_.clear();

        bool selectedStillOnline = false;
        int studentCount = 0;
        for (const auto& v : obj.value("users").toArray()) {
            const auto u = v.toObject();
            if (u.value("role").toString() != "student")
                continue;

            const QString id = u.value("id").toString();
            const QString name = u.value("name").toString();
            names_[id] = name;
            knownStudentIds_.insert(id);
            ++studentCount;

            QStringList badges;
            if (u.value("hand_raised").toBool()) badges << "✋ рука";
            if (u.value("confused").toBool()) badges << "❓ не понял";
            if (!u.value("rtc_ready").toBool()) badges << "RTC нет";
            if (!u.value("watching").toBool()) badges << "не смотрит";
            if (u.value("homework_busy").toBool()) badges << "сдаёт ДЗ";
            const QString state = u.value("status").toString();
            if (state == "minimized") badges << "свёрнуто";
            else if (state == "background") badges << "фон";

            auto* item = new QListWidgetItem;
            item->setData(Qt::UserRole, id);
            item->setSizeHint(QSize(210, badges.isEmpty() ? 40 : 54));
            students_->addItem(item);

            auto* row = new QWidget;
            auto* layout = new QVBoxLayout(row);
            layout->setContentsMargins(8, 4, 8, 4);
            layout->setSpacing(1);

            auto* radio = new QRadioButton(name);
            radio->setProperty("student_id", id);
            radio->setStyleSheet("QRadioButton{font-weight:600;font-size:14px;}");
            studentButtons_->addButton(radio);
            layout->addWidget(radio);

            auto* status = new QLabel(badges.isEmpty()
                ? "● онлайн"
                : "● онлайн · " + badges.join(" · "));
            status->setStyleSheet(badges.isEmpty()
                ? "color:#22c55e;font-size:11px;"
                : "color:#f59e0b;font-size:11px;");
            layout->addWidget(status);

            students_->setItemWidget(item, row);

            connect(radio, &QRadioButton::toggled, this,
                    [this, id, name, row](bool checked) {
                if (!checked) {
                    row->setStyleSheet(QString());
                    return;
                }
                selectedStudentId_ = id;
                selectedStudentLabel_->setText("Выбран: <b>" + name.toHtmlEscaped() + "</b>");
                selectedStudentLabel_->setStyleSheet(
                    "background:rgba(138,92,255,55);color:#ffffff;padding:10px;border:1px solid rgba(0,229,255,85);border-radius:12px;");
                row->setStyleSheet(
                    "background:rgba(138,92,255,32);border:1px solid rgba(0,229,255,90);border-radius:10px;");
            });

            if (id == keepSelected) {
                selectedStillOnline = true;
                radio->setChecked(true);
            }
        }

        onlineStudentCount_ = studentCount;
        updateOperationalStatus();

        if (!selectedStillOnline) {
            selectedStudentId_.clear();
            selectedStudentLabel_->setText("Никто не выбран");
            selectedStudentLabel_->setStyleSheet(
                "background:#0f172a;color:#cbd5e1;padding:8px;border-radius:6px;");
        }
    } else if (type == "hand_raise" || type == "hand_down") {
        // Актуальный статус руки приходит следом в presence и обновляет строку ученика.
    } else if (type == "student_confused") {
        const QString name = obj.value("student_name").toString();
        const bool active = obj.value("active").toBool();
        if (active) {
            status_->setText("❓ " + (name.isEmpty() ? QString("Ученик") : name) + " не понял материал");
            QApplication::beep();
        } else {
            status_->setText((name.isEmpty() ? QString("Ученик") : name) + " снял отметку «Не понял»");
        }
        updateOperationalStatus();
    } else if (type == "poll_answer") {
        if (obj.value("poll_id").toString() == activePollId_) {
            const int answer = obj.value("answer").toInt(-1);
            const QString studentId = obj.value("student_id").toString();
            const QString studentName = obj.value("student_name").toString();
            if (answer >= 0 && !studentId.isEmpty() && !pollStudentAnswers_.contains(studentId)) {
                pollStudentAnswers_[studentId] = answer;
                pollStudentNames_[studentId] = studentName;
                pollCounts_[answer] = pollCounts_.value(answer) + 1;
                ++pollResponses_;
                updatePollStatus();
            }
        }
    } else if (type == "chat_message") {
        const QString from = obj.value("from_name").toString();
        const QString text = obj.value("text").toString();
        const QString target = obj.value("target").toString();
        chatLog_->appendPlainText(QString("%1%2: %3")
            .arg(target.isEmpty() ? "" : "[личное] ", from, text));
    } else if (type == "keyframe_request") {
        codec_->requestKeyframe();
    }
}

QString TeacherWindow::selectedStudentId() const {
    return selectedStudentId_;
}

void TeacherWindow::sendToSelected(const QString& type) {
    const auto id = selectedStudentId();
    if (id.isEmpty()) {
        QMessageBox::information(this, "Ученик", "Сначала выберите ученика.");
        return;
    }
    activeStudent_ = id;
    remoteView_->setTarget(id);
    ws_->send(QJsonObject{{"type", type}, {"target", id}});
}

void TeacherWindow::refreshCaptureSources() {
    if (!sourceCombo_) return;

    const QString previous = sourceCombo_->currentData().toString();
    sourceCombo_->clear();

    const auto screens = QGuiApplication::screens();
    for (int i = 0; i < screens.size(); ++i) {
        QString label = screens[i]->name().trimmed();
        if (label.isEmpty())
            label = QString("Монитор %1").arg(i + 1);
        sourceCombo_->addItem(QString("🖥 %1").arg(label), QString("screen:%1").arg(i));
    }

    QSettings s("SHKI", "ClassroomRemoteTeacher");
    const QString savedArea = s.value("capture/area_source").toString();
    const QString savedAreaLabel = s.value("capture/area_label").toString();
    if (savedArea.startsWith("area:"))
        sourceCombo_->addItem(savedAreaLabel.isEmpty() ? "▱ Сохранённая область" : savedAreaLabel, savedArea);

    const auto windows = QWindowCapture::capturableWindows();
    for (int i = 0; i < windows.size(); ++i) {
        const QString title = windows[i].description().trimmed();
        if (!title.isEmpty())
            sourceCombo_->addItem(QString("▣ Окно: %1").arg(title), QString("window:%1").arg(i));
    }

    const int restore = sourceCombo_->findData(previous);
    if (restore >= 0)
        sourceCombo_->setCurrentIndex(restore);
}

void TeacherWindow::applySelectedQuality() {
    if (!qualityCombo_) return;

    const QString preset = qualityCombo_->currentData().toString();
    QRegularExpression re("^(\\d+)x(\\d+)@(\\d+)$");
    const auto m = re.match(preset);
    if (!m.hasMatch()) return;

    const int width = m.captured(1).toInt();
    const int height = m.captured(2).toInt();
    const int fps = qBound(1, m.captured(3).toInt(), 30);
    const int bitrate = width >= 1920 ? 3500000 : (width >= 1280 ? 1800000 : 900000);
    codec_->initialize(width, height, fps, bitrate, 64000);
    selectedFrameIntervalMs_ = qMax(33, 1000 / fps);
    screenTimer_->setInterval(selectedFrameIntervalMs_);
    codec_->requestKeyframe();
}

void TeacherWindow::startScreenLecture() {
    stopLecture();
    applySelectedQuality();

    QAudioDevice selectedMic;
    const QByteArray wantedMic = micCombo_->currentData().toByteArray();
    for (const auto& d : QMediaDevices::audioInputs()) {
        if (d.id() == wantedMic) {
            selectedMic = d;
            break;
        }
    }
    if (selectedMic.isNull())
        selectedMic = QMediaDevices::defaultAudioInput();

    micOn_ = audio_->startCapture(selectedMic);
    micButton_->setText(micOn_ ? "🎤 Микрофон: ВКЛ" : "🎤 Микрофон");
    if (!micOn_)
        QMessageBox::warning(this, "Микрофон", "Микрофон открыть не удалось.");
    systemAudioBuffer_.clear();
    if (systemAudioCheck_ && systemAudioCheck_->isChecked())
        systemAudio_->start();

    const QString source = sourceCombo_ ? sourceCombo_->currentData().toString() : QString("screen:0");
    if (source.startsWith("window:")) {
        bool ok=false;
        const int index=source.mid(7).toInt(&ok);
        const auto windows=QWindowCapture::capturableWindows();
        if (!ok || index<0 || index>=windows.size()) {
            QMessageBox::warning(this, "Источник", "Выбранное окно больше недоступно. Обновите список источников.");
            return;
        }

        windowCapture_ = new QWindowCapture(this);
        windowCaptureSession_ = new QMediaCaptureSession(this);
        windowCaptureSink_ = new QVideoSink(this);
        windowCaptureSession_->setWindowCapture(windowCapture_);
        windowCaptureSession_->setVideoSink(windowCaptureSink_);
        lastWindowSendMs_ = 0;
        connect(windowCaptureSink_, &QVideoSink::videoFrameChanged, this, [this](const QVideoFrame& frame) {
            if (!rtc_->isOpen()) return;
            const qint64 now = QDateTime::currentMSecsSinceEpoch();
            if (now - lastWindowSendMs_ < selectedFrameIntervalMs_)
                return;
            lastWindowSendMs_ = now;
            const QImage image=frame.toImage();
            if (!image.isNull())
                sendLectureImage(image);
        });
        connect(windowCapture_, &QWindowCapture::errorOccurred, this,
                [this](QWindowCapture::Error, const QString& error) {
            status_->setText("Ошибка захвата окна: " + error);
        });
        windowCapture_->setWindow(windows[index]);
        windowCapture_->start();
        setBroadcastState(true, "ОКНО");
        ws_->send(QJsonObject{{"type","lecture_state"},{"mode","window"},{"active",true}});
        return;
    }

    screenTimer_->start();
    const QString lectureMode = source.startsWith("area:") ? "area" : "screen";
    setBroadcastState(true, lectureMode == "area" ? "ОБЛАСТЬ ЭКРАНА" : "ЭКРАН");
    ws_->send(QJsonObject{{"type", "lecture_state"}, {"mode", lectureMode}, {"active", true}});
}

void TeacherWindow::startCameraLecture() {
    stopLecture();
    applySelectedQuality();

    QCameraDevice device;
    const QByteArray wantedCamera = cameraCombo_->currentData().toByteArray();
    for (const auto& d : QMediaDevices::videoInputs()) {
        if (d.id() == wantedCamera) {
            device = d;
            break;
        }
    }
    if (device.isNull())
        device = QMediaDevices::defaultVideoInput();

    if (device.isNull()) {
        QMessageBox::warning(this, "Камера", "Камера не найдена.");
        return;
    }

    QAudioDevice selectedMic;
    const QByteArray wantedMic = micCombo_->currentData().toByteArray();
    for (const auto& d : QMediaDevices::audioInputs()) {
        if (d.id() == wantedMic) {
            selectedMic = d;
            break;
        }
    }
    if (selectedMic.isNull())
        selectedMic = QMediaDevices::defaultAudioInput();

    micOn_ = audio_->startCapture(selectedMic);
    micButton_->setText(micOn_ ? "🎤 Микрофон: ВКЛ" : "🎤 Микрофон");
    if (!micOn_)
        QMessageBox::warning(this, "Микрофон", "Микрофон открыть не удалось.");
    systemAudioBuffer_.clear();
    if (systemAudioCheck_ && systemAudioCheck_->isChecked())
        systemAudio_->start();

    camera_ = new QCamera(device, this);
    captureSession_ = new QMediaCaptureSession(this);
    videoSink_ = new QVideoSink(this);
    captureSession_->setCamera(camera_);
    captureSession_->setVideoSink(videoSink_);

    connect(videoSink_, &QVideoSink::videoFrameChanged, this, [this](const QVideoFrame& frame) {
        if (!rtc_->isOpen())
            return;
        const qint64 now = QDateTime::currentMSecsSinceEpoch();
        if (now - lastCameraSendMs_ < selectedFrameIntervalMs_)
            return;
        lastCameraSendMs_ = now;
        const QImage image = frame.toImage();
        if (!image.isNull())
            sendLectureImage(image);
    });

    camera_->start();
    setBroadcastState(true, "КАМЕРА");
    ws_->send(QJsonObject{{"type", "lecture_state"}, {"mode", "camera"}, {"active", true}});
}

void TeacherWindow::startFileLecture() {
    if (!privateAudioTarget_.isEmpty()) {
        QMessageBox::information(this, "Видео-файл",
            "Сначала завершите приватный аудиодиалог.");
        return;
    }

    const QString path = QFileDialog::getOpenFileName(
        this,
        "Выберите видео для трансляции",
        QString(),
        "Видео (*.mp4 *.mkv *.avi *.mov *.webm);;Все файлы (*.*)");
    if (path.isEmpty())
        return;

    stopLecture();
    systemAudio_->stop();
    systemAudioBuffer_.clear();

    if (micOn_) {
        micOn_ = false;
        audio_->stopCapture();
        micButton_->setText("🎤 Микрофон");
    }

    filePlayer_ = new QMediaPlayer(this);
    fileVideoSink_ = new QVideoSink(this);

    QAudioFormat audioFormat;
    audioFormat.setSampleRate(48000);
    audioFormat.setChannelCount(1);
    audioFormat.setSampleFormat(QAudioFormat::Int16);
    fileAudioBuffer_ = new QAudioBufferOutput(audioFormat, this);

    filePlayer_->setVideoOutput(fileVideoSink_);
    filePlayer_->setAudioBufferOutput(fileAudioBuffer_);

    lastFileVideoSendMs_ = 0;
    codec_->requestKeyframe();

    connect(fileVideoSink_, &QVideoSink::videoFrameChanged, this,
            [this](const QVideoFrame& frame) {
        if (!rtc_->isOpen())
            return;

        const qint64 now = QDateTime::currentMSecsSinceEpoch();
        if (now - lastFileVideoSendMs_ < selectedFrameIntervalMs_)
            return;
        lastFileVideoSendMs_ = now;

        const QImage image = frame.toImage();
        if (!image.isNull())
            sendLectureImage(image);
    });

    connect(fileAudioBuffer_, &QAudioBufferOutput::audioBufferReceived, this,
            [this](const QAudioBuffer& buffer) {
        if (!rtc_->isOpen() || !buffer.isValid() || buffer.byteCount() <= 0)
            return;

        const QByteArray pcm(
            reinterpret_cast<const char*>(buffer.constData<char>()),
            buffer.byteCount());
        const auto packets = codec_->encodeAudio(pcm, "lecture-file");
        for (const auto& packet : packets)
            rtc_->sendAudio(RtcRelayClient::LectureAudioTrack, packet, 20);
    });

    connect(filePlayer_, &QMediaPlayer::mediaStatusChanged, this,
            [this](QMediaPlayer::MediaStatus status) {
        if (status == QMediaPlayer::EndOfMedia) {
            status_->setText("Видео завершено");
            stopLecture();
        } else if (status == QMediaPlayer::InvalidMedia) {
            QMessageBox::warning(this, "Видео-файл",
                "Не удалось открыть видео: " + filePlayer_->errorString());
            stopLecture();
        }
    });

    filePlayer_->setSource(QUrl::fromLocalFile(path));
    filePlayer_->play();

    status_->setText("▶ Трансляция видео: " + QFileInfo(path).fileName());
    setBroadcastState(true, "ВИДЕО");
    ws_->send(QJsonObject{
        {"type", "lecture_state"},
        {"mode", "file"},
        {"name", QFileInfo(path).fileName()},
        {"active", true}
    });
}

void TeacherWindow::startCameraRecording() {
    startRecording(false);
}

void TeacherWindow::startScreenRecording() {
    startRecording(true);
}

void TeacherWindow::startRecording(bool screen) {
    if (mediaRecorder_ &&
        mediaRecorder_->recorderState() == QMediaRecorder::RecordingState) {
        QMessageBox::information(this, "Запись",
            "Сначала остановите текущую запись.");
        return;
    }

    const QString suggested = QString("lesson-%1-%2.mp4")
        .arg(lesson_->text(),
             QDateTime::currentDateTime().toString("yyyyMMdd-HHmmss"));
    const QString path = QFileDialog::getSaveFileName(
        this,
        screen ? "Записать экран" : "Записать камеру",
        suggested,
        "MP4 (*.mp4)");
    if (path.isEmpty())
        return;

    stopLecture();
    if (mediaRecorder_) {
        QMessageBox::information(this, "Запись",
            "Дождитесь завершения предыдущей записи.");
        return;
    }

    recordingPath_ = path;
    recordingSession_ = new QMediaCaptureSession(this);
    mediaRecorder_ = new QMediaRecorder(this);
    recordingAudio_ = new QAudioInput(this);
    {
        const QByteArray wantedMic = micCombo_->currentData().toByteArray();
        for (const auto& d : QMediaDevices::audioInputs()) {
            if (d.id() == wantedMic) {
                recordingAudio_->setDevice(d);
                break;
            }
        }
    }
    recordingPreviewSink_ = new QVideoSink(this);

    recordingSession_->setRecorder(mediaRecorder_);
    recordingSession_->setAudioInput(recordingAudio_);
    recordingSession_->setVideoSink(recordingPreviewSink_);

    connect(recordingPreviewSink_, &QVideoSink::videoFrameChanged, this,
            [this](const QVideoFrame& frame) {
        const QImage image = frame.toImage();
        if (!image.isNull())
            showLocalPreview(image);
    });

    if (screen) {
        recordingScreen_ = new QScreenCapture(this);
        recordingScreen_->setScreen(QGuiApplication::primaryScreen());
        recordingSession_->setScreenCapture(recordingScreen_);
        recordingScreen_->start();
    } else {
        QCameraDevice device;
        const QByteArray wantedCamera = cameraCombo_->currentData().toByteArray();
        for (const auto& d : QMediaDevices::videoInputs()) {
            if (d.id() == wantedCamera) {
                device = d;
                break;
            }
        }
        if (device.isNull())
            device = QMediaDevices::defaultVideoInput();
        if (device.isNull()) {
            QMessageBox::warning(this, "Запись", "Камера не найдена.");
            stopRecording();
            return;
        }
        recordingCamera_ = new QCamera(device, this);
        recordingSession_->setCamera(recordingCamera_);
        recordingCamera_->start();
    }

    QMediaFormat format(QMediaFormat::MPEG4);
    format.setVideoCodec(QMediaFormat::VideoCodec::H264);
    format.setAudioCodec(QMediaFormat::AudioCodec::AAC);
    format.resolveForEncoding(QMediaFormat::RequiresVideo);

    mediaRecorder_->setMediaFormat(format);
    mediaRecorder_->setQuality(QMediaRecorder::HighQuality);
    mediaRecorder_->setVideoResolution(1280, 720);
    mediaRecorder_->setVideoFrameRate(30.0);
    mediaRecorder_->setOutputLocation(QUrl::fromLocalFile(path));

    connect(mediaRecorder_, &QMediaRecorder::errorOccurred, this,
            [this](QMediaRecorder::Error, const QString& errorString) {
        QMessageBox::warning(this, "Запись",
            "Ошибка записи: " + errorString);
        status_->setText("Ошибка записи");
    });

    connect(mediaRecorder_, &QMediaRecorder::recorderStateChanged, this,
            [this](QMediaRecorder::RecorderState state) {
        if (state == QMediaRecorder::RecordingState) {
            status_->setText("⏺ Идёт запись: " + recordingPath_);
        } else if (state == QMediaRecorder::StoppedState &&
                   !recordingPath_.isEmpty()) {
            status_->setText("Запись сохранена: " + recordingPath_);
            cleanupRecording();
        }
    });

    mediaRecorder_->record();
}

void TeacherWindow::stopRecording() {
    if (!mediaRecorder_)
        return;

    if (recordingCamera_)
        recordingCamera_->stop();
    if (recordingScreen_)
        recordingScreen_->stop();

    if (mediaRecorder_->recorderState() != QMediaRecorder::StoppedState) {
        status_->setText("Завершаю запись...");
        mediaRecorder_->stop();
        return;
    }

    cleanupRecording();
}

void TeacherWindow::cleanupRecording() {
    if (recordingCamera_) {
        recordingCamera_->deleteLater();
        recordingCamera_ = nullptr;
    }
    if (recordingScreen_) {
        recordingScreen_->deleteLater();
        recordingScreen_ = nullptr;
    }
    if (recordingAudio_) {
        recordingAudio_->deleteLater();
        recordingAudio_ = nullptr;
    }
    if (recordingPreviewSink_) {
        recordingPreviewSink_->deleteLater();
        recordingPreviewSink_ = nullptr;
    }
    if (recordingSession_) {
        recordingSession_->deleteLater();
        recordingSession_ = nullptr;
    }
    if (mediaRecorder_) {
        mediaRecorder_->deleteLater();
        mediaRecorder_ = nullptr;
    }
    recordingPath_.clear();
}

void TeacherWindow::stopLecture() {
    screenTimer_->stop();
    setBroadcastState(false);

    if (filePlayer_) {
        filePlayer_->stop();
        filePlayer_->deleteLater();
        filePlayer_ = nullptr;
    }
    if (fileVideoSink_) {
        fileVideoSink_->deleteLater();
        fileVideoSink_ = nullptr;
    }
    if (fileAudioBuffer_) {
        fileAudioBuffer_->deleteLater();
        fileAudioBuffer_ = nullptr;
    }

    if (camera_) {
        camera_->stop();
        camera_->deleteLater();
        camera_ = nullptr;
    }
    if (captureSession_) {
        captureSession_->deleteLater();
        captureSession_ = nullptr;
    }
    if (videoSink_) {
        videoSink_->deleteLater();
        videoSink_ = nullptr;
    }
    if (windowCapture_) {
        windowCapture_->stop();
        windowCapture_->deleteLater();
        windowCapture_ = nullptr;
    }
    if (windowCaptureSink_) {
        windowCaptureSink_->deleteLater();
        windowCaptureSink_ = nullptr;
    }
    if (windowCaptureSession_) {
        windowCaptureSession_->deleteLater();
        windowCaptureSession_ = nullptr;
    }

    if (privateAudioTarget_.isEmpty()) {
        audio_->stopCapture();
        micOn_ = false;
        if (micButton_)
            micButton_->setText("🎤 Микрофон");
    }
    systemAudio_->stop();
    systemAudioBuffer_.clear();

    if (ws_->isConnected())
        ws_->send(QJsonObject{{"type", "lecture_state"}, {"active", false}});
}

void TeacherWindow::setBroadcastState(bool active, const QString& mode) {
    const bool wasActive = broadcastActive_;
    broadcastActive_ = active;
    broadcastMode_ = active ? mode : QString();
    if (active && !wasActive) {
        broadcastElapsedSeconds_ = 0;
        broadcastElapsedTimer_->start();
    } else if (!active) {
        broadcastElapsedTimer_->stop();
    }

    if (broadcastToggleButton_) {
        if (active) {
            broadcastToggleButton_->setText("⏹ ОСТАНОВИТЬ ТРАНСЛЯЦИЮ");
            broadcastToggleButton_->setStyleSheet("QPushButton{font-weight:900;padding:8px 16px;border-radius:10px;background:#b91c1c;color:white;border:1px solid #ef4444;} QPushButton:hover{background:#991b1b;}");
        } else {
            broadcastToggleButton_->setText("▶ НАЧАТЬ ТРАНСЛЯЦИЮ");
            broadcastToggleButton_->setStyleSheet("QPushButton{font-weight:900;padding:8px 16px;border-radius:10px;background:#16a34a;color:white;border:1px solid #22c55e;} QPushButton:hover{background:#15803d;}");
        }
    }

    updateOperationalStatus();

    if (!broadcastStatus_)
        return;

    if (active) {
        const QString suffix = broadcastMode_.isEmpty() ? QString() : " · " + broadcastMode_;
        broadcastStatus_->setText("● ТРАНСЛЯЦИЯ ИДЁТ" + suffix);
        broadcastStatus_->setStyleSheet(
            "QLabel{background:#0f2f24;color:#86efac;font-weight:900;padding:7px 12px;"
            "border:1px solid #22c55e;border-radius:9px;}");
    } else {
        broadcastStatus_->setText("● ТРАНСЛЯЦИЯ ОСТАНОВЛЕНА");
        broadcastStatus_->setStyleSheet(
            "QLabel{background:#311217;color:#ff8a98;font-weight:800;padding:7px 12px;"
            "border:1px solid #7f1d2d;border-radius:9px;}");
    }
}


void TeacherWindow::openTVWindow() {
    if (!tvWindow_) {
        tvWindow_ = new QDialog(nullptr);
        tvWindow_->setAttribute(Qt::WA_DeleteOnClose, false);
        tvWindow_->setWindowTitle("Трансляция преподавателя — ТВ / проектор");
        tvWindow_->setStyleSheet("QDialog{background:#000;} QLabel{background:#000;color:white;}");
        auto* layout = new QVBoxLayout(tvWindow_);
        layout->setContentsMargins(0, 0, 0, 0);
        layout->setSpacing(0);

        auto* topBar = new QWidget(tvWindow_);
        topBar->setStyleSheet("background:rgba(0,0,0,180);");
        auto* topLayout = new QHBoxLayout(topBar);
        topLayout->setContentsMargins(12, 8, 12, 8);
        auto* title = new QLabel("Трансляция на ТВ");
        title->setStyleSheet("font-size:16px;font-weight:700;");
        auto* closeBtn = new QPushButton("✕ Закрыть ТВ");
        closeBtn->setMinimumHeight(34);
        closeBtn->setStyleSheet(
            "QPushButton{background:#b91c1c;color:white;font-weight:800;padding:6px 12px;border-radius:8px;}"
            " QPushButton:hover{background:#991b1b;}");
        topLayout->addWidget(title);
        topLayout->addStretch();
        topLayout->addWidget(closeBtn);
        layout->addWidget(topBar);

        tvPreview_ = new QLabel("Ожидание трансляции");
        tvPreview_->setAlignment(Qt::AlignCenter);
        tvPreview_->setStyleSheet("font-size:28px;");
        layout->addWidget(tvPreview_, 1);

        connect(closeBtn, &QPushButton::clicked, tvWindow_, &QDialog::hide);
        auto* esc = new QShortcut(QKeySequence(Qt::Key_Escape), tvWindow_);
        connect(esc, &QShortcut::activated, tvWindow_, &QDialog::hide);
    }

    const auto screens = QGuiApplication::screens();
    QScreen* target = QGuiApplication::primaryScreen();
    if (screens.size() > 1) {
        QStringList names;
        for (int i = 0; i < screens.size(); ++i) {
            QString name = screens[i]->name();
            if (name.isEmpty())
                name = QString("Экран %1").arg(i + 1);
            names << QString("%1 — %2×%3").arg(name)
                         .arg(screens[i]->geometry().width())
                         .arg(screens[i]->geometry().height());
        }
        bool ok = false;
        const QString selected = QInputDialog::getItem(
            this, "Экран для трансляции", "Куда вывести увеличенную трансляцию:",
            names, screens.indexOf(target), false, &ok);
        if (!ok)
            return;
        const int index = names.indexOf(selected);
        if (index >= 0)
            target = screens[index];
    }

    if (target)
        tvWindow_->setGeometry(target->geometry());
    tvWindow_->showFullScreen();
    tvWindow_->raise();
}

void TeacherWindow::sendScreenshotTask() {
    if (!ws_->isConnected() || token_->text().isEmpty()) {
        QMessageBox::information(this, "Скрин-задание", "Сначала подключитесь к занятию.");
        return;
    }

    bool ok = false;
    const QStringList variants{
        "Снять весь экран преподавателя",
        "Выделить произвольную область экрана",
        "Выбрать изображение с диска"
    };
    const QString variant = QInputDialog::getItem(
        this, "Скрин-задание", "Источник изображения:", variants, 0, false, &ok);
    if (!ok)
        return;

    if (variant == variants[0]) {
        QScreen* screen = QGuiApplication::primaryScreen();
        if (!screen)
            return;

        QByteArray imageData;
        QBuffer buffer(&imageData);
        buffer.open(QIODevice::WriteOnly);
        screen->grabWindow(0).save(&buffer, "PNG");
        uploadScreenshotTask(imageData, "task-screenshot.png", "image/png");
        return;
    }

    if (variant == variants[1]) {
        const auto screens = QGuiApplication::screens();
        if (screens.isEmpty())
            return;

        QScreen* screen = QGuiApplication::primaryScreen();
        if (screens.size() > 1) {
            QStringList labels;
            for (int i = 0; i < screens.size(); ++i) {
                QString name = screens[i]->name().trimmed();
                if (name.isEmpty())
                    name = QString("Экран %1").arg(i + 1);
                labels << QString("%1 — %2×%3")
                              .arg(name)
                              .arg(screens[i]->geometry().width())
                              .arg(screens[i]->geometry().height());
            }

            const int initial = qMax(0, screens.indexOf(screen));
            const QString selected = QInputDialog::getItem(
                this, "Скрин-задание", "На каком экране выделить область:",
                labels, initial, false, &ok);
            if (!ok)
                return;

            const int index = labels.indexOf(selected);
            if (index >= 0)
                screen = screens[index];
        }

        QScreen* selectedScreen = screen;
        new RegionSelector(selectedScreen, [this, selectedScreen](const QRect& region) {
            if (!selectedScreen || region.width() < 40 || region.height() < 40)
                return;

            const QPixmap pix = selectedScreen->grabWindow(
                0, region.x(), region.y(), region.width(), region.height());
            if (pix.isNull()) {
                QMessageBox::warning(this, "Скрин-задание",
                                     "Не удалось захватить выбранную область.");
                return;
            }

            QByteArray imageData;
            QBuffer buffer(&imageData);
            buffer.open(QIODevice::WriteOnly);
            pix.save(&buffer, "PNG");
            uploadScreenshotTask(imageData, "task-region.png", "image/png");
        });
        return;
    }

    const QString path = QFileDialog::getOpenFileName(
        this, "Выберите изображение", QString(),
        "Изображения (*.png *.jpg *.jpeg *.webp *.bmp)");
    if (path.isEmpty())
        return;

    QFile file(path);
    if (!file.open(QIODevice::ReadOnly)) {
        QMessageBox::warning(this, "Скрин-задание", "Не удалось открыть изображение.");
        return;
    }

    const QString suffix = QFileInfo(path).suffix().toLower();
    QString contentType = "image/png";
    if (suffix == "jpg" || suffix == "jpeg")
        contentType = "image/jpeg";
    else if (suffix == "webp")
        contentType = "image/webp";
    else if (suffix == "bmp")
        contentType = "image/bmp";

    uploadScreenshotTask(file.readAll(), QFileInfo(path).fileName(), contentType);
}

void TeacherWindow::uploadScreenshotTask(const QByteArray& imageData, const QString& fileName,
                                         const QString& contentType) {
    if (imageData.isEmpty()) {
        QMessageBox::warning(this, "Скрин-задание", "Получено пустое изображение.");
        return;
    }

    bool ok = false;
    const QString task = QInputDialog::getMultiLineText(
        this, "Задание ученикам", "Текст задания / пояснение:",
        "Посмотрите на изображение и выполните задание.", &ok).trimmed();
    if (!ok)
        return;

    auto* multi = new QHttpMultiPart(QHttpMultiPart::FormDataType);

    QHttpPart purposePart;
    purposePart.setHeader(QNetworkRequest::ContentDispositionHeader,
                          "form-data; name=\"purpose\"");
    purposePart.setBody("screenshot_task");
    multi->append(purposePart);

    auto* buffer = new QBuffer(multi);
    buffer->setData(imageData);
    buffer->open(QIODevice::ReadOnly);

    QHttpPart part;
    part.setHeader(QNetworkRequest::ContentDispositionHeader,
                   QString("form-data; name=\"file\"; filename=\"%1\"").arg(fileName));
    part.setHeader(QNetworkRequest::ContentTypeHeader, contentType);
    part.setBodyDevice(buffer);
    multi->append(part);

    QUrl url = httpBaseUrl();
    url.setPath(QString("/api/v1/classrooms/%1/quick-files").arg(lesson_->text()));
    QNetworkRequest req(url);
    req.setRawHeader("Authorization", "Bearer " + token_->text().toUtf8());

    status_->setText("Отправляю скрин-задание ученикам...");
    auto* reply = network_->post(req, multi);
    multi->setParent(reply);
    connect(reply, &QNetworkReply::finished, this, [this, reply, task, fileName] {
        const QByteArray response = reply->readAll();
        if (reply->error() != QNetworkReply::NoError) {
            QMessageBox::warning(this, "Скрин-задание",
                                 "Ошибка отправки: " + reply->errorString());
            reply->deleteLater();
            return;
        }

        const QJsonObject result = QJsonDocument::fromJson(response).object();
        const qint64 fileId = qint64(result.value("id").toDouble());
        if (fileId <= 0) {
            QMessageBox::warning(this, "Скрин-задание", "Сервер не вернул ID изображения.");
            reply->deleteLater();
            return;
        }

        ws_->send(QJsonObject{
            {"type", "screenshot_task"},
            {"file_id", double(fileId)},
            {"name", fileName},
            {"title", "Задание преподавателя"},
            {"text", task}
        });
        status_->setText("Скрин-задание отправлено и появится у учеников в разделе «Скрин-задания»");
        reply->deleteLater();
    });
}

QString TeacherWindow::localIPv4Address() const {
    QString fallback;
    for (const QHostAddress& address : QNetworkInterface::allAddresses()) {
        if (address.protocol() != QAbstractSocket::IPv4Protocol || address.isLoopback())
            continue;
        const QString value = address.toString();
        if (value.startsWith("169.254."))
            continue;

        const QStringList octets = value.split('.');
        const int second = octets.size() > 1 ? octets[1].toInt() : -1;
        const bool privateAddress =
            value.startsWith("10.") ||
            value.startsWith("192.168.") ||
            (value.startsWith("172.") && second >= 16 && second <= 31);
        if (privateAddress)
            return value;
        if (fallback.isEmpty())
            fallback = value;
    }
    return fallback.isEmpty() ? QString("127.0.0.1") : fallback;
}

QString TeacherWindow::localServerExecutable() const {
    const QString appDir = QCoreApplication::applicationDirPath();
#ifdef Q_OS_WIN
    const QString name = "classroom-server.exe";
#else
    const QString name = "classroom-server";
#endif
    const QStringList candidates{
        QDir(appDir).filePath(name),
        QDir(appDir).filePath("../Server/" + name),
        QDir(appDir).filePath("../server/" + name),
        QDir(appDir).filePath("../../server/" + name)
    };
    for (const QString& path : candidates) {
        if (QFileInfo::exists(path))
            return QFileInfo(path).absoluteFilePath();
    }
    return {};
}

void TeacherWindow::startLocalServer() {
    if (localServerProcess_ && localServerProcess_->state() != QProcess::NotRunning)
        return;

    const QString exe = localServerExecutable();
    if (exe.isEmpty()) {
        QMessageBox::warning(this, "Локальный сервер",
            "Рядом с ClassroomTeacher не найден classroom-server.exe. "
            "В готовом комплекте он должен лежать рядом с Teacher или в папке Server.");
        return;
    }

    bool ok = false;
    QString studentPin = QInputDialog::getText(
        this, "Локальный класс", "PIN для учеников (4–10 цифр):",
        QLineEdit::Normal, "12345", &ok).trimmed();
    if (!ok)
        return;
    QRegularExpression pinRe("^\\d{4,10}$");
    if (!pinRe.match(studentPin).hasMatch()) {
        QMessageBox::warning(this, "Локальный класс", "PIN должен содержать 4–10 цифр.");
        return;
    }

    QString teacherPin = pin_->text().trimmed();
    if (!pinRe.match(teacherPin).hasMatch())
        teacherPin = "54321";
    if (teacherPin == studentPin)
        teacherPin = studentPin == "54321" ? "54322" : "54321";

    const QString localDir = QDir(QCoreApplication::applicationDirPath()).filePath("local-server");
    QDir().mkpath(QDir(localDir).filePath("data/storage"));
    const QString configPath = QDir(localDir).filePath("config.json");

    QJsonObject cfg{
        {"http_addr", ":" + QString::number(localServerPort_)},
        {"https_addr", ":443"},
        {"domain", ""},
        {"acme_email", ""},
        {"public_ip", ""},
        {"storage_dir", QDir(localDir).filePath("data/storage")},
        {"db_path", QDir(localDir).filePath("data/classroom.db")},
        {"teacher_token", ""},
        {"student_token", ""},
        {"teacher_pin", teacherPin},
        {"student_pin", studentPin},
        {"rtc_min_port", 50000},
        {"rtc_max_port", 50100},
        {"turn_enabled", false},
        {"turn_listen", ":3478"},
        {"turn_realm", "classroom"},
        {"turn_username", "classroom"},
        {"turn_password", ""},
        {"turn_min_port", 49160},
        {"turn_max_port", 49260},
        {"ffmpeg_path", ""},
        {"max_upload_mb", 200}
    };
    QFile configFile(configPath);
    if (!configFile.open(QIODevice::WriteOnly | QIODevice::Truncate)) {
        QMessageBox::warning(this, "Локальный сервер", "Не удалось создать локальный config.json.");
        return;
    }
    configFile.write(QJsonDocument(cfg).toJson(QJsonDocument::Indented));
    configFile.close();

    localStudentPin_ = studentPin;
    localTeacherPin_ = teacherPin;
    localServerPort_ = findAvailableLocalPort();
    if (localServerPort_ <= 0) {
        QMessageBox::warning(this, "Локальный сервер", "Не удалось найти свободный TCP-порт для локального класса.");
        return;
    }

    // Перезаписываем конфигурацию уже с выбранным свободным портом.
    cfg["http_addr"] = ":" + QString::number(localServerPort_);
    if (!configFile.open(QIODevice::WriteOnly | QIODevice::Truncate)) {
        QMessageBox::warning(this, "Локальный сервер", "Не удалось обновить локальный config.json.");
        return;
    }
    configFile.write(QJsonDocument(cfg).toJson(QJsonDocument::Indented));
    configFile.close();

    localServerProcess_ = new QProcess(this);
    localServerProcess_->setWorkingDirectory(localDir);
    localServerProcess_->setProgram(exe);
    localServerProcess_->setArguments({"-config", configPath});
    localServerProcess_->setProcessChannelMode(QProcess::MergedChannels);

    connect(localServerProcess_, &QProcess::finished, this,
            [this](int exitCode, QProcess::ExitStatus) {
        if (!localServerProcess_)
            return;
        const QString log = QString::fromUtf8(localServerProcess_->readAll()).trimmed();
        if (localServerPort_ > 0) {
            status_->setText(QString("Локальный сервер остановлен (код %1)").arg(exitCode));
            if (!log.isEmpty())
                QMessageBox::warning(this, "Локальный сервер остановлен", log);
        }
    });

    localServerProcess_->start();
    if (!localServerProcess_->waitForStarted(3000)) {
        QMessageBox::warning(this, "Локальный сервер",
            "Не удалось запустить classroom-server.\n" + localServerProcess_->errorString());
        localServerProcess_->deleteLater();
        localServerProcess_ = nullptr;
        localServerPort_ = 0;
        return;
    }

    status_->setText(QString("Запускаю локальный сервер на порту %1...").arg(localServerPort_));
    checkLocalServerReady(1);
}

int TeacherWindow::findAvailableLocalPort() const {
    for (int port = 18080; port <= 18120; ++port) {
        QTcpServer probe;
        if (probe.listen(QHostAddress::LocalHost, quint16(port))) {
            probe.close();
            return port;
        }
    }
    return 0;
}

void TeacherWindow::checkLocalServerReady(int attempt) {
    if (!localServerProcess_ || localServerProcess_->state() == QProcess::NotRunning) {
        QString log;
        if (localServerProcess_)
            log = QString::fromUtf8(localServerProcess_->readAll()).trimmed();
        QMessageBox::warning(this, "Локальный сервер",
            log.isEmpty() ? "classroom-server завершился до запуска HTTP-сервера." : log);
        stopLocalServer();
        return;
    }

    QUrl health(QString("http://127.0.0.1:%1/health").arg(localServerPort_));
    auto* reply = network_->get(QNetworkRequest(health));
    auto* timeout = new QTimer(reply);
    timeout->setSingleShot(true);
    timeout->setInterval(1200);
    connect(timeout, &QTimer::timeout, reply, [reply] {
        if (reply->isRunning())
            reply->abort();
    });
    timeout->start();

    connect(reply, &QNetworkReply::finished, this, [this, reply, attempt] {
        const bool ok = reply->error() == QNetworkReply::NoError &&
                        reply->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt() == 200;
        reply->deleteLater();

        if (ok) {
            advertiseLocalServer();
            return;
        }

        if (attempt >= 8) {
            QString log;
            if (localServerProcess_)
                log = QString::fromUtf8(localServerProcess_->readAll()).trimmed();
            QMessageBox::warning(this, "Локальный сервер не готов",
                QString("Сервер не ответил на /health после 8 попыток.\nПорт: %1\n\n%2")
                    .arg(localServerPort_)
                    .arg(log.isEmpty() ? "Проверьте, не блокирует ли запуск защитное ПО." : log));
            stopLocalServer();
            return;
        }

        status_->setText(QString("Жду локальный сервер... %1/8").arg(attempt));
        QTimer::singleShot(500, this, [this, attempt] {
            checkLocalServerReady(attempt + 1);
        });
    });
}

void TeacherWindow::advertiseLocalServer() {
    if (!lanDiscovery_)
        lanDiscovery_ = new QUdpSocket(this);
    lanDiscovery_->abort();

    if (!lanDiscovery_->bind(QHostAddress::AnyIPv4, 45454,
                             QUdpSocket::ShareAddress | QUdpSocket::ReuseAddressHint)) {
        status_->setText("Локальный сервер готов, но UDP-автопоиск недоступен");
    } else {
        disconnect(lanDiscovery_, nullptr, this, nullptr);
        connect(lanDiscovery_, &QUdpSocket::readyRead, this, [this] {
            while (lanDiscovery_->hasPendingDatagrams()) {
                const QNetworkDatagram request = lanDiscovery_->receiveDatagram();
                if (request.data().trimmed() != "SHKI_DISCOVER")
                    continue;
                const QByteArray response = QString("SHKI_CLASSROOM|%1|%2|%3|%4")
                    .arg(localIPv4Address())
                    .arg(localServerPort_)
                    .arg(lesson_->text())
                    .arg(localStudentPin_)
                    .toUtf8();
                lanDiscovery_->writeDatagram(response, request.senderAddress(), request.senderPort());
            }
        });
    }

    config_.serverUrl = QString("http://127.0.0.1:%1").arg(localServerPort_);
    QString ignored;
    config_.save(&ignored);
    pin_->setText(localTeacherPin_);

    const QString ip = localIPv4Address();
    status_->setText(QString("📡 Локальный класс: http://%1:%2 · PIN учеников: %3")
                     .arg(ip)
                     .arg(localServerPort_)
                     .arg(localStudentPin_));

    QMessageBox::information(this, "Локальный класс готов",
        QString("Сервер проверен и отвечает. Интернет не требуется.\n\n"
                "Адрес: http://%1:%2\n"
                "PIN учеников: %3\n"
                "Занятие: %4")
            .arg(ip)
            .arg(localServerPort_)
            .arg(localStudentPin_)
            .arg(lesson_->text()));

    loginWithPIN();
}

void TeacherWindow::stopLocalServer() {
    // Mark the shutdown as intentional before QProcess::finished is emitted.
    localServerPort_ = 0;
    if (lanDiscovery_) {
        lanDiscovery_->close();
        lanDiscovery_->deleteLater();
        lanDiscovery_ = nullptr;
    }
    if (!localServerProcess_)
        return;
    if (localServerProcess_->state() != QProcess::NotRunning) {
        localServerProcess_->terminate();
        if (!localServerProcess_->waitForFinished(1500))
            localServerProcess_->kill();
    }
    localServerProcess_->deleteLater();
    localServerProcess_ = nullptr;
    localStudentPin_.clear();
    localTeacherPin_.clear();
    localServerPort_ = 0;
}

static QImage drawCursorOnFrame(const QImage& source, const QRect& capturedGeometry) {
    if (source.isNull())
        return source;

    const QPoint globalPos = QCursor::pos();
    if (!capturedGeometry.contains(globalPos))
        return source;

    const double sx = double(source.width()) / qMax(1, capturedGeometry.width());
    const double sy = double(source.height()) / qMax(1, capturedGeometry.height());
    const QPoint local = globalPos - capturedGeometry.topLeft();
    const QPoint p(qRound(local.x() * sx), qRound(local.y() * sy));

    QImage out = source.convertToFormat(QImage::Format_ARGB32_Premultiplied);
    QPainter painter(&out);
    painter.setRenderHint(QPainter::Antialiasing, true);

    QPolygon arrow;
    arrow << QPoint(0,0) << QPoint(0,22) << QPoint(6,17)
          << QPoint(11,29) << QPoint(16,27) << QPoint(11,15) << QPoint(20,15);
    painter.translate(p);
    painter.setPen(QPen(Qt::black, 4, Qt::SolidLine, Qt::RoundCap, Qt::RoundJoin));
    painter.setBrush(Qt::white);
    painter.drawPolygon(arrow);
    painter.setPen(QPen(Qt::white, 1));
    painter.drawPolygon(arrow);
    return out;
}

void TeacherWindow::sendLectureImage(const QImage& source) {
    if (!rtc_->isOpen() || source.isNull())
        return;

    const QImage image = source.scaled(codec_->width(), codec_->height(),
                                       Qt::KeepAspectRatio, Qt::SmoothTransformation);
    showLocalPreview(image);

    const QByteArray h264 = codec_->encodeVideo(image);
    if (!h264.isEmpty())
        rtc_->sendVideo(RtcRelayClient::LectureVideoTrack, h264, 100);
}

void TeacherWindow::showLocalPreview(const QImage& image) {
    const QPixmap source = QPixmap::fromImage(image);
    lecturePreview_->setPixmap(source.scaled(
        lecturePreview_->size(), Qt::KeepAspectRatio, Qt::SmoothTransformation));
    if (tvPreview_ && tvWindow_ && tvWindow_->isVisible()) {
        tvPreview_->setPixmap(source.scaled(
            tvPreview_->size(), Qt::KeepAspectRatio, Qt::SmoothTransformation));
    }
}

void TeacherWindow::onRtcVideo(const QString& trackId, const QByteArray& h264) {
    if (trackId != RtcRelayClient::RemoteVideoTrack)
        return;

    const QImage image = codec_->decodeVideo(h264);
    if (!image.isNull()) {
        remoteView_->setPixmap(QPixmap::fromImage(image).scaled(
            remoteView_->size(), Qt::KeepAspectRatio, Qt::SmoothTransformation));
    }
}

void TeacherWindow::onRtcAudio(const QString& trackId, const QByteArray& opus) {
    if (trackId != RtcRelayClient::RemoteAudioTrack)
        return;

    const QByteArray pcm = codec_->decodeAudio(opus, trackId);
    if (!pcm.isEmpty())
        audio_->play(pcm);
}


QUrl TeacherWindow::httpBaseUrl() const {
    QUrl url(server_->text());
    if (url.scheme() == "wss") url.setScheme("https");
    else if (url.scheme() == "ws") url.setScheme("http");
    url.setPath("");
    url.setQuery(QString());
    return url;
}

void TeacherWindow::launchQuickPoll(const QString& question, const QStringList& options) {
    if (question.trimmed().isEmpty() || options.size() < 2 || !ws_->isConnected())
        return;

    activePollId_ = QUuid::createUuid().toString(QUuid::WithoutBraces);
    activePollQuestion_ = question.trimmed();
    pollCounts_.clear();
    pollStudentAnswers_.clear();
    pollStudentNames_.clear();
    pollResponses_ = 0;

    QJsonArray arr;
    QStringList clean;
    for (const QString& option : options) {
        const QString v = option.trimmed();
        if (!v.isEmpty()) {
            clean << v;
            arr.append(v);
        }
    }
    if (clean.size() < 2)
        return;

    ws_->send(QJsonObject{
        {"type", "poll_start"},
        {"poll_id", activePollId_},
        {"question", activePollQuestion_},
        {"options", arr},
        {"fullscreen", config_.fullscreenQuestions}
    });
    pollStatus_->setProperty("options", clean);
    updatePollStatus();
}

void TeacherWindow::startPoll() {
    bool ok = false;
    const QString question = QInputDialog::getText(
        this, "Быстрый опрос", "Вопрос:", QLineEdit::Normal, {}, &ok).trimmed();
    if (!ok || question.isEmpty()) return;

    const QString optionText = QInputDialog::getText(
        this, "Быстрый опрос",
        "Варианты через точку с запятой (например: Понятно;Не понятно):",
        QLineEdit::Normal, "Понятно;Не понятно", &ok).trimmed();
    if (!ok) return;

    const QStringList options = optionText.split(';', Qt::SkipEmptyParts);
    if (options.size() < 2) {
        QMessageBox::information(this, "Опрос", "Нужно минимум два варианта.");
        return;
    }

    activePollId_ = QUuid::createUuid().toString(QUuid::WithoutBraces);
    activePollQuestion_ = question;
    pollCounts_.clear();
    pollResponses_ = 0;

    QJsonArray arr;
    for (const QString& option : options)
        arr.append(option.trimmed());
    ws_->send(QJsonObject{
        {"type", "poll_start"},
        {"poll_id", activePollId_},
        {"question", question},
        {"options", arr},
        {"fullscreen", config_.fullscreenQuestions}
    });
    pollStatus_->setProperty("options", options);
    updatePollStatus();
}

void TeacherWindow::updatePollStatus() {
    const QStringList options = pollStatus_->property("options").toStringList();
    QStringList parts;
    for (int i = 0; i < options.size(); ++i) {
        const int count = pollCounts_.value(i);
        const int percent = pollResponses_ > 0 ? qRound(100.0 * count / pollResponses_) : 0;
        parts << QString("%1 — %2 (%3%)").arg(options.at(i)).arg(count).arg(percent);
    }
    pollStatus_->setText(activePollQuestion_ + " | Ответов: " +
                         QString::number(pollResponses_) + " | " + parts.join(" · "));
}

void TeacherWindow::showPollResults() {
    if (activePollId_.isEmpty()) {
        QMessageBox::information(this, "Результаты опроса", "Опрос ещё не запускался.");
        return;
    }

    const QStringList options = pollStatus_->property("options").toStringList();
    QString text = "Вопрос: " + activePollQuestion_ + "\n\n";
    text += QString("Ответило: %1\n\n").arg(pollResponses_);

    for (int i = 0; i < options.size(); ++i) {
        const int count = pollCounts_.value(i);
        const int percent = pollResponses_ > 0 ? qRound(100.0 * count / pollResponses_) : 0;
        text += QString("%1 — %2 голосов (%3%)\n")
            .arg(options.at(i))
            .arg(count)
            .arg(percent);

        QStringList students;
        for (auto it = pollStudentAnswers_.cbegin(); it != pollStudentAnswers_.cend(); ++it) {
            if (it.value() != i)
                continue;
            const QString name = pollStudentNames_.value(it.key()).trimmed();
            students << (name.isEmpty() ? it.key() : name);
        }
        if (!students.isEmpty())
            text += "  Ученики: " + students.join(", ") + "\n";
        text += "\n";
    }

    QMessageBox box(this);
    box.setWindowTitle("Результаты опроса");
    box.setIcon(QMessageBox::Information);
    box.setText(text);
    box.setStandardButtons(QMessageBox::Ok);
    box.exec();
}

void TeacherWindow::sendChat(bool privateMessage) {
    const QString text = chatInput_->text().trimmed();
    if (text.isEmpty()) return;
    QString target;
    if (privateMessage) {
        target = selectedStudentId();
        if (target.isEmpty()) {
            QMessageBox::information(this, "Чат", "Сначала выберите ученика.");
            return;
        }
    }
    ws_->send(QJsonObject{{"type", "chat_message"}, {"text", text}, {"target", target}});
    chatInput_->clear();
}

void TeacherWindow::uploadQuickFile() {
    const QString path = QFileDialog::getOpenFileName(this, "Отправить файл ученикам");
    if (path.isEmpty()) return;

    auto* multi = new QHttpMultiPart(QHttpMultiPart::FormDataType);
    auto* file = new QFile(path, multi);
    if (!file->open(QIODevice::ReadOnly)) {
        delete multi;
        QMessageBox::warning(this, "Файл", "Не удалось открыть файл.");
        return;
    }
    QHttpPart part;
    part.setHeader(QNetworkRequest::ContentDispositionHeader,
                   QString("form-data; name=\"file\"; filename=\"%1\"")
                       .arg(QFileInfo(path).fileName()));
    part.setBodyDevice(file);
    multi->append(part);

    QUrl url = httpBaseUrl();
    url.setPath(QString("/api/v1/classrooms/%1/quick-files").arg(lesson_->text()));
    QNetworkRequest req(url);
    req.setRawHeader("Authorization", "Bearer " + token_->text().toUtf8());
    auto* reply = network_->post(req, multi);
    multi->setParent(reply);
    connect(reply, &QNetworkReply::finished, this, [this, reply] {
        if (reply->error() == QNetworkReply::NoError)
            status_->setText("Файл отправлен ученикам");
        else
            QMessageBox::warning(this, "Файл", "Ошибка загрузки: " + reply->errorString());
        reply->deleteLater();
    });
}

void TeacherWindow::showAttendance() {
    QUrl url = httpBaseUrl();
    url.setPath(QString("/api/v1/classrooms/%1/attendance").arg(lesson_->text()));
    QNetworkRequest req(url);
    req.setRawHeader("Authorization", "Bearer " + token_->text().toUtf8());
    auto* reply = network_->get(req);
    connect(reply, &QNetworkReply::finished, this, [this, reply] {
        const QByteArray data = reply->readAll();
        if (reply->error() != QNetworkReply::NoError) {
            QMessageBox::warning(this, "Посещаемость", reply->errorString());
            reply->deleteLater();
            return;
        }
        const QJsonArray rows = QJsonDocument::fromJson(data).array();
        QString text;
        for (const auto& v : rows) {
            const auto r = v.toObject();
            const qint64 ms = qint64(r.value("online_ms").toDouble());
            const qint64 first = qint64(r.value("first_seen").toDouble());
            const qint64 last = qint64(r.value("last_seen").toDouble());
            text += QString("%1 — %2 мин.\n  вход: %3\n  последняя активность: %4\n  переподключений: %5\n\n")
                .arg(r.value("student_name").toString())
                .arg(ms / 60000)
                .arg(QDateTime::fromMSecsSinceEpoch(first).toString("dd.MM.yyyy HH:mm:ss"))
                .arg(QDateTime::fromMSecsSinceEpoch(last).toString("dd.MM.yyyy HH:mm:ss"))
                .arg(r.value("reconnects").toInt());
        }
        if (text.isEmpty()) text = "Подключений учеников пока не было.";
        QMessageBox::information(this, "Посещаемость занятия", text);
        reply->deleteLater();
    });
}

void TeacherWindow::downloadRecording() {
    const QString studentId = selectedStudentId();
    if (studentId.isEmpty()) {
        QMessageBox::information(this, "Запись", "Сначала выберите ученика.");
        return;
    }

    const QString fileName = QFileDialog::getSaveFileName(
        this,
        "Сохранить запись",
        QString("lesson-%1-%2.mp4").arg(lesson_->text(), names_.value(studentId, studentId)),
        "MP4 (*.mp4)");
    if (fileName.isEmpty())
        return;

    QUrl url(server_->text());
    if (url.scheme() == "wss")
        url.setScheme("https");
    else if (url.scheme() == "ws")
        url.setScheme("http");
    url.setPath(QString("/api/v1/lessons/%1/students/%2/recording.mp4")
                    .arg(lesson_->text(), studentId));
    url.setQuery(QString());

    QNetworkRequest request(url);
    request.setRawHeader("Authorization", "Bearer " + token_->text().toUtf8());
    auto* reply = network_->get(request);
    status_->setText("Формируется MP4...");

    connect(reply, &QNetworkReply::finished, this, [this, reply, fileName] {
        const auto data = reply->readAll();
        if (reply->error() != QNetworkReply::NoError) {
            QMessageBox::warning(this, "Запись", "Ошибка скачивания: " + reply->errorString());
            status_->setText("Ошибка экспорта записи");
            reply->deleteLater();
            return;
        }

        QFile file(fileName);
        if (!file.open(QIODevice::WriteOnly) || file.write(data) != data.size()) {
            QMessageBox::warning(this, "Запись", "Не удалось сохранить файл.");
            status_->setText("Ошибка сохранения записи");
            reply->deleteLater();
            return;
        }
        file.close();
        status_->setText("Запись сохранена: " + fileName);
        reply->deleteLater();
    });
}


void TeacherWindow::downloadFramesZip() {
    const QString studentId = selectedStudentId();
    if (studentId.isEmpty()) {
        QMessageBox::information(this, "Запись", "Сначала выберите ученика.");
        return;
    }

    const QString fileName = QFileDialog::getSaveFileName(
        this,
        "Сохранить архив кадров",
        QString("lesson-%1-%2.zip").arg(lesson_->text(), names_.value(studentId, studentId)),
        "ZIP (*.zip)");
    if (fileName.isEmpty())
        return;

    QUrl url(server_->text());
    if (url.scheme() == "wss")
        url.setScheme("https");
    else if (url.scheme() == "ws")
        url.setScheme("http");
    url.setPath(QString("/api/v1/lessons/%1/students/%2/recording.zip")
                    .arg(lesson_->text(), studentId));
    url.setQuery(QString());

    QNetworkRequest request(url);
    request.setRawHeader("Authorization", "Bearer " + token_->text().toUtf8());
    auto* reply = network_->get(request);
    status_->setText("Скачиваю архив кадров...");

    connect(reply, &QNetworkReply::finished, this, [this, reply, fileName] {
        const auto data = reply->readAll();
        if (reply->error() != QNetworkReply::NoError) {
            QMessageBox::warning(this, "Запись", "Ошибка скачивания: " + reply->errorString());
            status_->setText("Ошибка скачивания архива");
            reply->deleteLater();
            return;
        }

        QFile file(fileName);
        if (!file.open(QIODevice::WriteOnly) || file.write(data) != data.size()) {
            QMessageBox::warning(this, "Запись", "Не удалось сохранить ZIP.");
            status_->setText("Ошибка сохранения архива");
            reply->deleteLater();
            return;
        }
        file.close();
        status_->setText("Архив сохранён: " + fileName);
        reply->deleteLater();
    });
}
