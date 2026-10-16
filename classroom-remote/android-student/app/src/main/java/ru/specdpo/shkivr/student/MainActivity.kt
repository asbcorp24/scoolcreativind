package ru.specdpo.shkivr.student

import android.app.Dialog
import android.content.Intent
import android.graphics.Typeface
import android.net.Uri
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.Gravity
import android.view.View
import android.view.WindowManager
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.RadioButton
import android.widget.RadioGroup
import android.widget.ScrollView
import android.widget.SeekBar
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import com.google.android.material.button.MaterialButton
import okhttp3.OkHttpClient
import org.json.JSONArray
import org.json.JSONObject
import org.webrtc.SurfaceViewRenderer
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.concurrent.TimeUnit

class MainActivity : AppCompatActivity(),
    SignalingClient.Listener,
    RtcStudentClient.Listener {

    private val ui = Handler(Looper.getMainLooper())
    private val httpClient = OkHttpClient.Builder()
        .connectTimeout(10, TimeUnit.SECONDS)
        .readTimeout(0, TimeUnit.MILLISECONDS)
        .build()

    private lateinit var api: ClassroomApi
    private lateinit var learningApi: StudentLearningApi
    private lateinit var signaling: SignalingClient
    private lateinit var rtc: RtcStudentClient

    private lateinit var loginPanel: LinearLayout
    private lateinit var sessionPanel: LinearLayout
    private lateinit var topBar: LinearLayout
    private lateinit var bottomBar: LinearLayout
    private lateinit var nameInput: EditText
    private lateinit var pinInput: EditText
    private lateinit var connectButton: MaterialButton
    private lateinit var disconnectButton: MaterialButton
    private lateinit var handButton: MaterialButton
    private lateinit var confusedButton: MaterialButton
    private lateinit var materialsButton: MaterialButton
    private lateinit var messagesButton: MaterialButton
    private lateinit var audioOnlyButton: MaterialButton
    private lateinit var muteButton: MaterialButton
    private lateinit var fullscreenButton: MaterialButton
    private lateinit var serverText: TextView
    private lateinit var statusText: TextView
    private lateinit var lessonText: TextView
    private lateinit var taskTimerText: TextView
    private lateinit var waitingText: TextView
    private lateinit var annotationView: AnnotationView
    private lateinit var remoteVideo: SurfaceViewRenderer
    private lateinit var volumeSeek: SeekBar
    private lateinit var pollOverlay: LinearLayout
    private lateinit var pollQuestion: TextView
    private lateinit var pollOptions: RadioGroup
    private lateinit var pollSubmitButton: MaterialButton
    private lateinit var materialsPanel: ScrollView
    private lateinit var materialsList: LinearLayout
    private lateinit var messagesPanel: ScrollView
    private lateinit var messagesList: LinearLayout

    private var activeSession: LoginSession? = null
    private var displayName = "Ученик"
    private var handRaised = false
    private var confused = false
    private var rtcReady = false
    private var attentionDialog: Dialog? = null
    private var activePollId = ""
    private var audioOnly = false
    private var muted = false
    private var fullscreen = false
    private var currentTaskEndAt = 0L
    private val teacherMessages = mutableListOf<String>()

    private val statusHeartbeat = object : Runnable {
        override fun run() {
            signaling.send(
                JSONObject()
                    .put("type", "student_status")
                    .put("status", if (hasWindowFocus()) "online" else "background")
                    .put("watching", !audioOnly)
                    .put("homework_busy", materialsPanel.visibility == View.VISIBLE)
                    .put("rtc_ready", rtcReady)
            )
            ui.postDelayed(this, 5_000)
        }
    }

    private val taskTicker = object : Runnable {
        override fun run() {
            if (currentTaskEndAt <= 0L) {
                taskTimerText.visibility = View.GONE
                return
            }
            val remaining = currentTaskEndAt - System.currentTimeMillis()
            if (remaining <= 0L) {
                currentTaskEndAt = 0L
                taskTimerText.text = "Задание: время вышло"
                taskTimerText.visibility = View.VISIBLE
                return
            }
            val total = remaining / 1000
            taskTimerText.text = String.format(
                Locale.getDefault(), "Задание: %02d:%02d", total / 60, total % 60
            )
            taskTimerText.visibility = View.VISIBLE
            ui.postDelayed(this, 1000)
        }
    }

    private val hideFullscreenControls = Runnable {
        if (fullscreen) setViewerControlsVisible(false)
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
        setContentView(R.layout.activity_main)

        bindViews()

        val prefs = getSharedPreferences("student", MODE_PRIVATE)
        nameInput.setText(prefs.getString("name", ""))
        volumeSeek.progress = prefs.getInt("volume", 100)
        serverText.text = BuildConfig.CLASSROOM_SERVER
        loadTeacherMessageHistory()

        api = ClassroomApi(httpClient)
        learningApi = StudentLearningApi(this, httpClient)
        signaling = SignalingClient(httpClient, this)
        rtc = RtcStudentClient(this, remoteVideo, this)

        connectButton.setOnClickListener { login() }
        disconnectButton.setOnClickListener { disconnectToLogin() }

        handButton.setOnClickListener {
            handRaised = !handRaised
            handButton.text = if (handRaised) "✋ Поднята" else "✋ Рука"
            signaling.send(JSONObject().put("type", if (handRaised) "hand_raise" else "hand_down"))
        }

        confusedButton.setOnClickListener {
            confused = !confused
            confusedButton.text = if (confused) "❓ Не понял ✓" else "❓ Не понял"
            signaling.send(
                JSONObject()
                    .put("type", "student_confused")
                    .put("active", confused)
            )
        }

        materialsButton.setOnClickListener {
            val show = materialsPanel.visibility != View.VISIBLE
            closePanels()
            if (show) {
                materialsPanel.visibility = View.VISIBLE
                loadMaterials()
            }
        }

        messagesButton.setOnClickListener {
            val show = messagesPanel.visibility != View.VISIBLE
            closePanels()
            if (show) {
                messagesPanel.visibility = View.VISIBLE
                renderTeacherMessages()
            }
        }

        audioOnlyButton.setOnClickListener {
            audioOnly = !audioOnly
            rtc.setVideoEnabled(!audioOnly)
            audioOnlyButton.text = if (audioOnly) "🎧 Только звук ✓" else "🎧 Только звук"
            waitingText.visibility = if (audioOnly) View.VISIBLE else View.GONE
            if (audioOnly) waitingText.text = "Режим «Только звук»"
        }

        muteButton.setOnClickListener {
            muted = !muted
            rtc.setAudioMuted(muted)
            muteButton.text = if (muted) "🔇" else "🔊"
        }

        volumeSeek.setOnSeekBarChangeListener(object : SeekBar.OnSeekBarChangeListener {
            override fun onProgressChanged(seekBar: SeekBar?, progress: Int, fromUser: Boolean) {
                rtc.setAudioVolume(progress / 100.0)
                if (fromUser) {
                    getSharedPreferences("student", MODE_PRIVATE)
                        .edit().putInt("volume", progress).apply()
                }
            }
            override fun onStartTrackingTouch(seekBar: SeekBar?) = Unit
            override fun onStopTrackingTouch(seekBar: SeekBar?) = Unit
        })

        fullscreenButton.setOnClickListener { toggleFullscreen() }
        remoteVideo.setOnClickListener {
            if (fullscreen) {
                setViewerControlsVisible(true)
                scheduleFullscreenHide()
            }
        }

        pollSubmitButton.setOnClickListener { submitPollAnswer() }
    }

    private fun bindViews() {
        loginPanel = findViewById(R.id.loginPanel)
        sessionPanel = findViewById(R.id.sessionPanel)
        topBar = findViewById(R.id.topBar)
        bottomBar = findViewById(R.id.bottomBar)
        nameInput = findViewById(R.id.nameInput)
        pinInput = findViewById(R.id.pinInput)
        connectButton = findViewById(R.id.connectButton)
        disconnectButton = findViewById(R.id.disconnectButton)
        handButton = findViewById(R.id.handButton)
        confusedButton = findViewById(R.id.confusedButton)
        materialsButton = findViewById(R.id.materialsButton)
        messagesButton = findViewById(R.id.messagesButton)
        audioOnlyButton = findViewById(R.id.audioOnlyButton)
        muteButton = findViewById(R.id.muteButton)
        fullscreenButton = findViewById(R.id.fullscreenButton)
        serverText = findViewById(R.id.serverText)
        statusText = findViewById(R.id.statusText)
        lessonText = findViewById(R.id.lessonText)
        taskTimerText = findViewById(R.id.taskTimerText)
        waitingText = findViewById(R.id.waitingText)
        annotationView = findViewById(R.id.annotationView)
        remoteVideo = findViewById(R.id.remoteVideo)
        volumeSeek = findViewById(R.id.volumeSeek)
        pollOverlay = findViewById(R.id.pollOverlay)
        pollQuestion = findViewById(R.id.pollQuestion)
        pollOptions = findViewById(R.id.pollOptions)
        pollSubmitButton = findViewById(R.id.pollSubmitButton)
        materialsPanel = findViewById(R.id.materialsPanel)
        materialsList = findViewById(R.id.materialsList)
        messagesPanel = findViewById(R.id.messagesPanel)
        messagesList = findViewById(R.id.messagesList)
    }

    private fun login() {
        val pin = pinInput.text.toString().trim()
        displayName = nameInput.text.toString().trim().ifBlank { "Ученик" }

        if (pin.length < 4 || pin.any { !it.isDigit() }) {
            pinInput.error = "Введите числовой PIN"
            return
        }

        connectButton.isEnabled = false
        connectButton.text = "Подключение…"
        getSharedPreferences("student", MODE_PRIVATE)
            .edit()
            .putString("name", displayName)
            .apply()

        api.login(BuildConfig.CLASSROOM_SERVER, pin) { result ->
            runOnUiThread {
                connectButton.isEnabled = true
                connectButton.text = "Подключиться"

                result.onSuccess { session ->
                    activeSession = session
                    pinInput.text?.clear()
                    loginPanel.visibility = View.GONE
                    sessionPanel.visibility = View.VISIBLE
                    lessonText.text = "Урок ${session.lessonId}"
                    statusText.text = "Подключаюсь…"
                    waitingText.visibility = View.VISIBLE
                    signaling.connect(session, displayName)
                    loadMaterials()
                }.onFailure { error ->
                    AlertDialog.Builder(this)
                        .setTitle("Не удалось войти")
                        .setMessage(error.message ?: "Сервер недоступен")
                        .setPositiveButton("ОК", null)
                        .show()
                    pinInput.selectAll()
                }
            }
        }
    }

    private fun disconnectToLogin() {
        ui.removeCallbacks(statusHeartbeat)
        ui.removeCallbacks(taskTicker)
        ui.removeCallbacks(hideFullscreenControls)
        signaling.close()
        rtc.stopPeer()
        activeSession = null
        rtcReady = false
        handRaised = false
        confused = false
        handButton.text = "✋ Рука"
        confusedButton.text = "❓ Не понял"
        attentionDialog?.dismiss()
        attentionDialog = null
        pollOverlay.visibility = View.GONE
        closePanels()
        annotationView.applyRemote(JSONObject().put("type", "annotation_clear"))
        if (fullscreen) toggleFullscreen()
        sessionPanel.visibility = View.GONE
        loginPanel.visibility = View.VISIBLE
    }

    override fun onConnected() {
        runOnUiThread {
            statusText.text = "● Сервер подключён"
            ui.removeCallbacks(statusHeartbeat)
            ui.post(statusHeartbeat)
        }
    }

    override fun onDisconnected() {
        runOnUiThread {
            rtcReady = false
            statusText.text = "○ Переподключение…"
            rtc.stopPeer()
        }
    }

    override fun onError(message: String) {
        runOnUiThread { statusText.text = "Ошибка сети: $message" }
    }

    override fun onMessage(message: JSONObject) {
        runOnUiThread {
            when (message.optString("type")) {
                "server_config" -> {
                    val ice = message.optJSONArray("ice_servers").toStringList()
                    statusText.text = "Настраиваю WebRTC…"
                    rtc.start(ice)
                }
                "rtc_answer" -> {
                    val sdp = message.optString("sdp")
                    if (sdp.isNotBlank()) rtc.handleAnswer(sdp)
                }
                "rtc_error" -> statusText.text = "WebRTC: " + message.optString("message")
                "annotation", "annotation_clear" -> {
                    annotationView.applyRemote(message)
                    annotationView.bringToFront()
                }
                "lecture_state" -> {
                    val active = message.optBoolean("active", false)
                    if (!audioOnly) waitingText.visibility = if (active) View.GONE else View.VISIBLE
                    if (!active) {
                        waitingText.text = "Ожидание трансляции преподавателя…"
                        loadMaterials()
                    }
                }
                "poll_start" -> showPollOverlay(message)
                "attention" -> {
                    if (message.optBoolean("active", false)) {
                        addTeacherMessage("Внимание: " + message.optString("text"))
                    }
                    showAttention(message)
                }
                "chat_message" -> {
                    if (message.optString("from_role") == "teacher") {
                        addTeacherMessage(message.optString("text"))
                    }
                }
                "task_timer" -> handleTaskTimer(message)
                "materials_refresh" -> loadMaterials()
                "audio_request" -> Unit
            }
        }
    }

    override fun onOfferReady(sdp: String) {
        signaling.send(JSONObject().put("type", "rtc_offer").put("sdp", sdp))
    }

    override fun onState(text: String) {
        runOnUiThread {
            if (!text.contains("signaling", ignoreCase = true)) statusText.text = text
        }
    }

    override fun onVideoAvailable() {
        runOnUiThread {
            if (!audioOnly) waitingText.visibility = View.GONE
            statusText.text = "● Трансляция идёт"
            rtc.setAudioMuted(muted)
            rtc.setAudioVolume(volumeSeek.progress / 100.0)
            rtc.setVideoEnabled(!audioOnly)
        }
    }

    override fun onConnectedChanged(connected: Boolean) {
        runOnUiThread {
            rtcReady = connected
            if (connected) {
                statusText.text = "● Подключено"
                rtc.setAudioMuted(muted)
                rtc.setAudioVolume(volumeSeek.progress / 100.0)
                rtc.setVideoEnabled(!audioOnly)
            }
        }
    }

    private fun showPollOverlay(message: JSONObject) {
        activePollId = message.optString("poll_id")
        val options = message.optJSONArray("options").toStringList()
        if (activePollId.isBlank() || options.size < 2) return

        pollQuestion.text = message.optString("question", "Опрос")
        pollOptions.removeAllViews()
        options.forEachIndexed { index, option ->
            pollOptions.addView(RadioButton(this).apply {
                id = View.generateViewId()
                tag = index
                text = option
                setTextColor(getColor(R.color.text))
            })
        }
        pollOverlay.visibility = View.VISIBLE
        pollOverlay.bringToFront()
    }

    private fun submitPollAnswer() {
        val selectedId = pollOptions.checkedRadioButtonId
        if (selectedId == -1 || activePollId.isBlank()) {
            Toast.makeText(this, "Выберите ответ", Toast.LENGTH_SHORT).show()
            return
        }
        val selected = pollOptions.findViewById<RadioButton>(selectedId)
        val answer = selected.tag as? Int ?: return
        signaling.send(
            JSONObject()
                .put("type", "poll_answer")
                .put("poll_id", activePollId)
                .put("answer", answer)
        )
        pollOverlay.visibility = View.GONE
        activePollId = ""
        Toast.makeText(this, "Ответ отправлен", Toast.LENGTH_SHORT).show()
    }

    private fun handleTaskTimer(message: JSONObject) {
        val active = message.optBoolean("active", false)
        if (!active) {
            currentTaskEndAt = 0L
            taskTimerText.visibility = View.GONE
            ui.removeCallbacks(taskTicker)
            return
        }
        currentTaskEndAt = message.optLong("end_at", 0L)
        if (currentTaskEndAt <= System.currentTimeMillis()) {
            val seconds = message.optInt("seconds", 0)
            currentTaskEndAt = System.currentTimeMillis() + seconds * 1000L
        }
        ui.removeCallbacks(taskTicker)
        ui.post(taskTicker)
    }

    private fun loadMaterials() {
        val session = activeSession ?: return
        learningApi.loadLessons(session) { result ->
            runOnUiThread {
                result.onSuccess(::renderLessons)
                result.onFailure {
                    if (materialsPanel.visibility == View.VISIBLE) {
                        Toast.makeText(this, "Не удалось загрузить материалы", Toast.LENGTH_SHORT).show()
                    }
                }
            }
        }
    }

    private fun renderLessons(lessons: List<LessonInfo>) {
        materialsList.removeAllViews()
        addSectionTitle(materialsList, "Материалы урока")

        if (lessons.isEmpty()) {
            addBodyText(materialsList, "Пока нет опубликованных материалов.")
            return
        }

        lessons.sortedByDescending { it.position }.forEach { lesson ->
            val title = TextView(this).apply {
                text = "${lesson.position}. ${lesson.title}"
                textSize = 18f
                setTextColor(getColor(R.color.cyan))
                setTypeface(typeface, Typeface.BOLD)
                setPadding(0, 18, 0, 6)
            }
            materialsList.addView(title)

            if (lesson.description.isNotBlank()) addBodyText(materialsList, lesson.description)
            if (lesson.homework.isNotBlank()) {
                addBodyText(materialsList, "Домашнее задание:\n${lesson.homework}")
            }

            lesson.links.forEach { url ->
                val b = MaterialButton(this).apply {
                    text = "🔗 " + url.take(70)
                    setOnClickListener { openLink(url) }
                }
                materialsList.addView(b)
            }

            lesson.files.forEach { material ->
                val size = if (material.sizeBytes > 0) {
                    " · " + (material.sizeBytes / 1024) + " КБ"
                } else ""
                val b = MaterialButton(this).apply {
                    text = "⬇ ${material.name}$size"
                    setOnClickListener {
                        val session = activeSession ?: return@setOnClickListener
                        learningApi.download(session, material)
                        Toast.makeText(
                            this@MainActivity,
                            "Скачивание начато. Файл будет в папке «Загрузки».",
                            Toast.LENGTH_LONG
                        ).show()
                    }
                }
                materialsList.addView(b)
            }
        }
    }

    private fun openLink(url: String) {
        runCatching {
            startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
        }.onFailure {
            Toast.makeText(this, "Не удалось открыть ссылку", Toast.LENGTH_SHORT).show()
        }
    }

    private fun addTeacherMessage(text: String) {
        val clean = text.trim()
        if (clean.isEmpty()) return
        val time = SimpleDateFormat("HH:mm", Locale.getDefault()).format(Date())
        teacherMessages.add(0, "$time — $clean")
        while (teacherMessages.size > 100) teacherMessages.removeLast()
        saveTeacherMessageHistory()
        if (messagesPanel.visibility == View.VISIBLE) renderTeacherMessages()
    }

    private fun loadTeacherMessageHistory() {
        val raw = getSharedPreferences("student", MODE_PRIVATE)
            .getString("teacher_messages", "[]") ?: "[]"
        runCatching {
            val arr = JSONArray(raw)
            teacherMessages.clear()
            for (i in 0 until arr.length()) teacherMessages += arr.optString(i)
        }
    }

    private fun saveTeacherMessageHistory() {
        val arr = JSONArray()
        teacherMessages.forEach { arr.put(it) }
        getSharedPreferences("student", MODE_PRIVATE)
            .edit().putString("teacher_messages", arr.toString()).apply()
    }

    private fun renderTeacherMessages() {
        messagesList.removeAllViews()
        addSectionTitle(messagesList, "Сообщения преподавателя")
        if (teacherMessages.isEmpty()) {
            addBodyText(messagesList, "Сообщений пока нет.")
        } else {
            teacherMessages.forEach { addBodyText(messagesList, it) }
        }
    }

    private fun addSectionTitle(parent: LinearLayout, text: String) {
        parent.addView(TextView(this).apply {
            this.text = text
            textSize = 22f
            setTextColor(getColor(R.color.text))
            setTypeface(typeface, Typeface.BOLD)
            setPadding(0, 0, 0, 10)
        })
    }

    private fun addBodyText(parent: LinearLayout, text: String) {
        parent.addView(TextView(this).apply {
            this.text = text
            textSize = 15f
            setTextColor(getColor(R.color.text))
            setPadding(0, 6, 0, 8)
        })
    }

    private fun closePanels() {
        materialsPanel.visibility = View.GONE
        messagesPanel.visibility = View.GONE
    }

    private fun toggleFullscreen() {
        fullscreen = !fullscreen
        closePanels()
        if (fullscreen) {
            window.decorView.systemUiVisibility =
                View.SYSTEM_UI_FLAG_FULLSCREEN or
                View.SYSTEM_UI_FLAG_HIDE_NAVIGATION or
                View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY or
                View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN or
                View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION or
                View.SYSTEM_UI_FLAG_LAYOUT_STABLE
            setViewerControlsVisible(true)
            scheduleFullscreenHide()
        } else {
            ui.removeCallbacks(hideFullscreenControls)
            window.decorView.systemUiVisibility = View.SYSTEM_UI_FLAG_VISIBLE
            setViewerControlsVisible(true)
        }
    }

    private fun setViewerControlsVisible(visible: Boolean) {
        val value = if (visible) View.VISIBLE else View.GONE
        topBar.visibility = value
        bottomBar.visibility = value
        volumeSeek.visibility = value
    }

    private fun scheduleFullscreenHide() {
        ui.removeCallbacks(hideFullscreenControls)
        ui.postDelayed(hideFullscreenControls, 3000)
    }

    private fun showAttention(message: JSONObject) {
        val active = message.optBoolean("active", false)
        if (!active) {
            attentionDialog?.dismiss()
            attentionDialog = null
            return
        }

        attentionDialog?.dismiss()
        val dialog = Dialog(this)
        val root = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            gravity = Gravity.CENTER
            setPadding(48, 48, 48, 48)
            setBackgroundColor(android.graphics.Color.rgb(7, 17, 31))
        }
        val title = TextView(this).apply {
            text = "⚠ ВНИМАНИЕ"
            textSize = 38f
            setTextColor(android.graphics.Color.rgb(251, 191, 36))
            gravity = Gravity.CENTER
            setTypeface(typeface, Typeface.BOLD)
        }
        val body = TextView(this).apply {
            text = message.optString("text").ifBlank { "Смотрим на экран преподавателя" }
            textSize = 30f
            setTextColor(android.graphics.Color.WHITE)
            gravity = Gravity.CENTER
            setPadding(0, 36, 0, 36)
        }
        val button = MaterialButton(this).apply {
            text = "Понятно"
            setOnClickListener { dialog.dismiss() }
        }
        root.addView(title)
        root.addView(body)
        root.addView(button)

        dialog.setContentView(root)
        dialog.setOnDismissListener {
            if (attentionDialog === dialog) attentionDialog = null
        }
        dialog.show()
        dialog.window?.setLayout(
            WindowManager.LayoutParams.MATCH_PARENT,
            WindowManager.LayoutParams.MATCH_PARENT
        )
        attentionDialog = dialog
    }

    override fun onDestroy() {
        ui.removeCallbacksAndMessages(null)
        signaling.close()
        rtc.release()
        httpClient.dispatcher.executorService.shutdown()
        super.onDestroy()
    }

    private fun JSONArray?.toStringList(): List<String> {
        if (this == null) return emptyList()
        return buildList {
            for (i in 0 until length()) {
                val value = optString(i)
                if (value.isNotBlank()) add(value)
            }
        }
    }
}
