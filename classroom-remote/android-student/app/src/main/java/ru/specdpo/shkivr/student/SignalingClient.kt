package ru.specdpo.shkivr.student

import android.os.Handler
import android.os.Looper
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.Response
import okhttp3.WebSocket
import okhttp3.WebSocketListener
import org.json.JSONObject
import java.net.URLEncoder
import java.nio.charset.StandardCharsets
import java.util.UUID
import kotlin.math.min

class SignalingClient(
    private val client: OkHttpClient,
    private val listener: Listener
) {
    interface Listener {
        fun onConnected()
        fun onDisconnected()
        fun onMessage(message: JSONObject)
        fun onError(message: String)
    }

    private val handler = Handler(Looper.getMainLooper())
    private var socket: WebSocket? = null
    private var session: LoginSession? = null
    private var displayName: String = "Ученик"
    private val clientId: String = "android-" + UUID.randomUUID().toString()
    private var reconnectAttempt = 0
    private var manualClose = false

    fun connect(session: LoginSession, displayName: String) {
        this.session = session
        this.displayName = displayName.ifBlank { "Ученик" }
        manualClose = false
        reconnectAttempt = 0
        open()
    }

    private fun open() {
        val s = session ?: return
        val enc = { value: String ->
            URLEncoder.encode(value, StandardCharsets.UTF_8.toString())
        }
        val url = buildString {
            append(s.wsUrl)
            append(if (s.wsUrl.contains("?")) "&" else "?")
            append("lesson=").append(enc(s.lessonId))
            append("&id=").append(enc(clientId))
            append("&role=student")
            append("&name=").append(enc(displayName))
        }

        val request = Request.Builder()
            .url(url)
            .header("Authorization", "Bearer ${s.accessToken}")
            .build()

        socket?.cancel()
        socket = client.newWebSocket(request, object : WebSocketListener() {
            override fun onOpen(webSocket: WebSocket, response: Response) {
                reconnectAttempt = 0
                listener.onConnected()
            }

            override fun onMessage(webSocket: WebSocket, text: String) {
                runCatching { JSONObject(text) }
                    .onSuccess(listener::onMessage)
            }

            override fun onClosed(webSocket: WebSocket, code: Int, reason: String) {
                listener.onDisconnected()
                scheduleReconnect()
            }

            override fun onFailure(webSocket: WebSocket, t: Throwable, response: Response?) {
                listener.onError(t.message ?: "Ошибка WebSocket")
                listener.onDisconnected()
                scheduleReconnect()
            }
        })
    }

    private fun scheduleReconnect() {
        if (manualClose || session == null) return
        val delay = min(10_000L, 1_000L shl min(reconnectAttempt, 3))
        reconnectAttempt++
        handler.removeCallbacksAndMessages(null)
        handler.postDelayed({ if (!manualClose) open() }, delay)
    }

    fun send(message: JSONObject): Boolean {
        return socket?.send(message.toString()) == true
    }

    fun close() {
        manualClose = true
        handler.removeCallbacksAndMessages(null)
        socket?.close(1000, "student close")
        socket = null
        session = null
    }
}
