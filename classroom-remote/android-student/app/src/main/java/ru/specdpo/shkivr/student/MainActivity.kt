package ru.specdpo.shkivr.student

import android.app.Dialog
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.View
import android.view.WindowManager
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.TextView
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import com.google.android.material.button.MaterialButton
import okhttp3.OkHttpClient
import org.json.JSONArray
import org.json.JSONObject
import org.webrtc.SurfaceViewRenderer
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
    private lateinit var signaling: SignalingClient
    private lateinit var rtc: RtcStudentClient

    private lateinit var loginPanel: LinearLayout
    private lateinit var sessionPanel: LinearLayout
    private lateinit var nameInput: EditText
    private lateinit var pinInput: EditText
    private lateinit var connectButton: MaterialButton
    private lateinit var disconnectButton: MaterialButton
    private lateinit var handButton: MaterialButton
    private lateinit var serverText: TextView
    private lateinit var statusText: TextView
    private lateinit var lessonText: TextView
    private lateinit var waitingText: TextView
    private lateinit var annotationView: AnnotationView
    private lateinit var remoteVideo: SurfaceViewRenderer

    private var activeSession: LoginSession? = null
    private var displayName = "Ученик"
    private var handRaised = false
    private var rtcReady = false
    private var attentionDialog: Dialog? = null
    private var pollDialog: AlertDialog? = null

    private val statusHeartbeat = object : Runnable {
        override fun run() {
            signaling.send(
                JSONObject()
                    .put("type", "student_status")
                    .put("status", if (hasWindowFocus()) "online" else "background")
                    .put("watching", true)
                    .put("homework_busy", false)
                    .put("rtc_ready", rtcReady)
            )
            ui.postDelayed(this, 5_000)
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        loginPanel = findViewById(R.id.loginPanel)
        sessionPanel = findViewById(R.id.sessionPanel)
        nameInput = findViewById(R.id.nameInput)
        pinInput = findViewById(R.id.pinInput)
        connectButton = findViewById(R.id.connectButton)
        disconnectButton = findViewById(R.id.disconnectButton)
        handButton = findViewById(R.id.handButton)
        serverText = findViewById(R.id.serverText)
        statusText = findViewById(R.id.statusText)
        lessonText = findViewById(R.id.lessonText)
        waitingText = findViewById(R.id.waitingText)
        annotationView = findViewById(R.id.annotationView)
        remoteVideo = findViewById(R.id.remoteVideo)

        serverText.text = "Сервер: ${BuildConfig.CLASSROOM_SERVER}"
        val prefs = getSharedPreferences("student", MODE_PRIVATE)
        nameInput.setText(prefs.getString("name", ""))

        api = ClassroomApi(httpClient)
        signaling = SignalingClient(httpClient, this)
        rtc = RtcStudentClient(this, remoteVideo, this)

        connectButton.setOnClickListener { login() }
        disconnectButton.setOnClickListener { disconnectToLogin() }
        handButton.setOnClickListener {
            handRaised = !handRaised
            handButton.text = if (handRaised) "✋ Рука поднята" else "✋ Поднять руку"
            signaling.send(JSONObject().put("type", if (handRaised) "hand_raise" else "hand_down"))
        }
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
                    lessonText.text = "Занятие: ${session.lessonId}"
                    statusText.text = "Подключаюсь к занятию…"
                    waitingText.visibility = View.VISIBLE
                    signaling.connect(session, displayName)
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
        signaling.close()
        rtc.stopPeer()
        activeSession = null
        rtcReady = false
        handRaised = false
        handButton.text = "✋ Поднять руку"
        attentionDialog?.dismiss()
        attentionDialog = null
        pollDialog?.dismiss()
        pollDialog = null
        annotationView.applyRemote(JSONObject().put("type", "annotation_clear"))
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
            statusText.text = "○ Связь потеряна — переподключаюсь…"
            rtc.stopPeer()
        }
    }

    override fun onError(message: String) {
        runOnUiThread {
            statusText.text = "Ошибка сети: $message"
        }
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
                    waitingText.visibility = if (active) View.GONE else View.VISIBLE
                    if (!active) waitingText.text = "Ожидание трансляции преподавателя…"
                }
                "poll_start" -> showPoll(message)
                "attention" -> showAttention(message)
                "chat_message" -> Unit
                "audio_request" -> Unit
            }
        }
    }

    override fun onOfferReady(sdp: String) {
        signaling.send(JSONObject().put("type", "rtc_offer").put("sdp", sdp))
    }

    override fun onState(text: String) {
        runOnUiThread {
            if (!text.contains("signaling", ignoreCase = true)) {
                statusText.text = text
            }
        }
    }

    override fun onVideoAvailable() {
        runOnUiThread {
            waitingText.visibility = View.GONE
            statusText.text = "● Трансляция идёт"
        }
    }

    override fun onConnectedChanged(connected: Boolean) {
        runOnUiThread {
            rtcReady = connected
            if (connected) statusText.text = "● WebRTC подключён"
        }
    }

    private fun showPoll(message: JSONObject) {
        val pollId = message.optString("poll_id")
        val question = message.optString("question", "Опрос")
        val options = message.optJSONArray("options").toStringList()
        if (pollId.isBlank() || options.size < 2) return

        pollDialog?.dismiss()
        var selected = -1
        pollDialog = AlertDialog.Builder(this)
            .setTitle(question)
            .setSingleChoiceItems(options.toTypedArray(), -1) { _, which -> selected = which }
            .setPositiveButton("Ответить") { _, _ ->
                if (selected >= 0) {
                    signaling.send(
                        JSONObject()
                            .put("type", "poll_answer")
                            .put("poll_id", pollId)
                            .put("answer", selected)
                    )
                }
            }
            .setNegativeButton("Позже", null)
            .create()
        pollDialog?.show()
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
            gravity = android.view.Gravity.CENTER
            setPadding(48, 48, 48, 48)
            setBackgroundColor(android.graphics.Color.rgb(7, 17, 31))
        }
        val title = TextView(this).apply {
            text = "⚠ ВНИМАНИЕ"
            textSize = 38f
            setTextColor(android.graphics.Color.rgb(251, 191, 36))
            gravity = android.view.Gravity.CENTER
            setTypeface(typeface, android.graphics.Typeface.BOLD)
        }
        val body = TextView(this).apply {
            text = message.optString("text").ifBlank { "Смотрим на экран преподавателя" }
            textSize = 30f
            setTextColor(android.graphics.Color.WHITE)
            gravity = android.view.Gravity.CENTER
            setPadding(0, 36, 0, 36)
        }
        val button = MaterialButton(this).apply {
            text = "Понятно"
            setOnClickListener { dialog.dismiss() }
        }
        root.addView(title)
        root.addView(body, LinearLayout.LayoutParams(
            LinearLayout.LayoutParams.MATCH_PARENT,
            LinearLayout.LayoutParams.WRAP_CONTENT
        ))
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
