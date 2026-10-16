package ru.specdpo.shkivr.student

import okhttp3.Call
import okhttp3.Callback
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.io.IOException

data class LoginSession(
    val lessonId: String,
    val httpUrl: String,
    val wsUrl: String,
    val accessToken: String
)

class ClassroomApi(private val client: OkHttpClient) {
    fun login(
        serverUrl: String,
        pin: String,
        lessonId: String = "101",
        callback: (Result<LoginSession>) -> Unit
    ) {
        val body = JSONObject()
            .put("role", "student")
            .put("pin", pin)
            .put("lesson_id", lessonId)
            .toString()
            .toRequestBody("application/json; charset=utf-8".toMediaType())

        val request = Request.Builder()
            .url(serverUrl.trimEnd('/') + "/api/client/login")
            .post(body)
            .build()

        client.newCall(request).enqueue(object : Callback {
            override fun onFailure(call: Call, e: IOException) {
                callback(Result.failure(e))
            }

            override fun onResponse(call: Call, response: okhttp3.Response) {
                response.use {
                    if (!it.isSuccessful) {
                        val message = when (it.code) {
                            401 -> "Неверный PIN"
                            429 -> "Слишком много попыток. Повторите через минуту."
                            else -> "Ошибка сервера: HTTP ${it.code}"
                        }
                        callback(Result.failure(IllegalStateException(message)))
                        return
                    }

                    runCatching {
                        val json = JSONObject(it.body?.string().orEmpty())
                        LoginSession(
                            lessonId = json.optString("lesson_id", lessonId),
                            httpUrl = json.getString("http_url"),
                            wsUrl = json.getString("ws_url"),
                            accessToken = json.getString("access_token")
                        )
                    }.also(callback)
                }
            }
        })
    }
}
