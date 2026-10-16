package ru.specdpo.shkivr.student

import android.app.DownloadManager
import android.content.Context
import android.net.Uri
import android.os.Environment
import okhttp3.Call
import okhttp3.Callback
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONArray
import org.json.JSONObject
import java.io.IOException

data class LessonMaterial(
    val lessonId: Long,
    val fileId: Long,
    val name: String,
    val mimeType: String,
    val sizeBytes: Long
)

data class LessonInfo(
    val id: Long,
    val position: Int,
    val title: String,
    val description: String,
    val homework: String,
    val dueAt: Long?,
    val files: List<LessonMaterial>,
    val links: List<String>
)

class StudentLearningApi(
    private val context: Context,
    private val client: OkHttpClient
) {
    fun loadLessons(session: LoginSession, callback: (Result<List<LessonInfo>>) -> Unit) {
        val url = session.httpUrl.trimEnd('/') + "/api/v1/classrooms/" +
            Uri.encode(session.lessonId) + "/lessons"
        val request = Request.Builder()
            .url(url)
            .header("Authorization", "Bearer ${session.accessToken}")
            .build()

        client.newCall(request).enqueue(object : Callback {
            override fun onFailure(call: Call, e: IOException) {
                callback(Result.failure(e))
            }

            override fun onResponse(call: Call, response: okhttp3.Response) {
                response.use {
                    if (!it.isSuccessful) {
                        callback(Result.failure(IllegalStateException("HTTP ${it.code}")))
                        return
                    }
                    callback(runCatching {
                        val arr = JSONArray(it.body?.string().orEmpty())
                        buildList {
                            for (i in 0 until arr.length()) {
                                val obj = arr.getJSONObject(i)
                                val id = obj.getLong("id")
                                val files = buildList {
                                    val fa = obj.optJSONArray("files") ?: JSONArray()
                                    for (j in 0 until fa.length()) {
                                        val f = fa.getJSONObject(j)
                                        add(
                                            LessonMaterial(
                                                lessonId = id,
                                                fileId = f.getLong("id"),
                                                name = f.optString("original_name", "file"),
                                                mimeType = f.optString("mime_type", "application/octet-stream"),
                                                sizeBytes = f.optLong("size_bytes", 0L)
                                            )
                                        )
                                    }
                                }
                                val description = obj.optString("description")
                                val homework = obj.optString("homework_text")
                                add(
                                    LessonInfo(
                                        id = id,
                                        position = obj.optInt("position", i + 1),
                                        title = obj.optString("title", "Урок"),
                                        description = description,
                                        homework = homework,
                                        dueAt = if (obj.has("due_at")) obj.optLong("due_at") else null,
                                        files = files,
                                        links = extractLinks(description + "\n" + homework)
                                    )
                                )
                            }
                        }
                    })
                }
            }
        })
    }

    fun download(session: LoginSession, material: LessonMaterial): Long {
        val url = session.httpUrl.trimEnd('/') +
            "/api/v1/classrooms/" + Uri.encode(session.lessonId) +
            "/lessons/" + material.lessonId +
            "/materials/" + material.fileId

        val safeName = sanitizeFilename(material.name)
        val request = DownloadManager.Request(Uri.parse(url))
            .setTitle(safeName)
            .setDescription("Материал урока SHKIVR")
            .setMimeType(material.mimeType)
            .addRequestHeader("Authorization", "Bearer ${session.accessToken}")
            .setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
            .setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, safeName)

        val manager = context.getSystemService(Context.DOWNLOAD_SERVICE) as DownloadManager
        return manager.enqueue(request)
    }

    private fun extractLinks(text: String): List<String> {
        val regex = Regex("""https?://[^\s<>"]+""", RegexOption.IGNORE_CASE)
        return regex.findAll(text)
            .map { it.value.trimEnd('.', ',', ';', ')', ']') }
            .distinct()
            .toList()
    }

    private fun sanitizeFilename(name: String): String {
        val clean = name.substringAfterLast('/').substringAfterLast('\\')
            .replace(Regex("""[\\/:*?"<>|]"""), "_")
            .trim()
        return clean.ifBlank { "material" }
    }
}
