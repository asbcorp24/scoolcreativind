package ru.specdpo.shkivr.student

import android.content.Context
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.graphics.RectF
import android.util.AttributeSet
import android.view.View
import org.json.JSONObject
import kotlin.math.atan2
import kotlin.math.cos
import kotlin.math.sin

class AnnotationView @JvmOverloads constructor(
    context: Context,
    attrs: AttributeSet? = null
) : View(context, attrs) {

    private val shapes = mutableListOf<JSONObject>()
    private val paint = Paint(Paint.ANTI_ALIAS_FLAG)

    init {
        setWillNotDraw(false)
        isClickable = false
        isFocusable = false
    }

    fun applyRemote(obj: JSONObject) {
        when (obj.optString("type")) {
            "annotation_clear" -> {
                shapes.clear()
                invalidate()
            }
            "annotation" -> {
                if (obj.optString("kind") == "laser") {
                    shapes.removeAll { it.optString("kind") == "laser" }
                }
                shapes.add(JSONObject(obj.toString()))
                if (shapes.size > 4000) {
                    repeat(shapes.size - 4000) { shapes.removeAt(0) }
                }
                invalidate()
            }
        }
    }

    override fun onDraw(canvas: Canvas) {
        super.onDraw(canvas)
        val w = width.toFloat().coerceAtLeast(1f)
        val h = height.toFloat().coerceAtLeast(1f)

        for (obj in shapes) {
            val x1 = obj.optDouble("x1", 0.0).toFloat() * w
            val y1 = obj.optDouble("y1", 0.0).toFloat() * h
            val x2 = obj.optDouble("x2", 0.0).toFloat() * w
            val y2 = obj.optDouble("y2", 0.0).toFloat() * h

            when (obj.optString("kind")) {
                "marker" -> {
                    paint.style = Paint.Style.STROKE
                    paint.strokeWidth = 6f * resources.displayMetrics.density
                    paint.strokeCap = Paint.Cap.ROUND
                    paint.strokeJoin = Paint.Join.ROUND
                    paint.color = Color.argb(220, 255, 220, 0)
                    canvas.drawLine(x1, y1, x2, y2, paint)
                }
                "rect" -> {
                    paint.style = Paint.Style.STROKE
                    paint.strokeWidth = 4f * resources.displayMetrics.density
                    paint.color = Color.rgb(0, 220, 255)
                    canvas.drawRect(
                        RectF(
                            minOf(x1, x2), minOf(y1, y2),
                            maxOf(x1, x2), maxOf(y1, y2)
                        ),
                        paint
                    )
                }
                "arrow" -> drawArrow(canvas, x1, y1, x2, y2)
                "text" -> drawTextAnnotation(canvas, x1, y1, obj.optString("text"))
                "laser" -> {
                    paint.style = Paint.Style.FILL
                    paint.color = Color.argb(235, 255, 30, 30)
                    canvas.drawCircle(x1, y1, 10f * resources.displayMetrics.density, paint)
                    paint.color = Color.argb(210, 255, 255, 255)
                    canvas.drawCircle(x1, y1, 3f * resources.displayMetrics.density, paint)
                }
            }
        }
    }

    private fun drawArrow(canvas: Canvas, x1: Float, y1: Float, x2: Float, y2: Float) {
        val density = resources.displayMetrics.density
        paint.style = Paint.Style.STROKE
        paint.strokeWidth = 4f * density
        paint.strokeCap = Paint.Cap.ROUND
        paint.color = Color.rgb(255, 70, 70)
        canvas.drawLine(x1, y1, x2, y2, paint)

        val angle = atan2((y2 - y1).toDouble(), (x2 - x1).toDouble())
        val len = 18f * density
        val p1x = x2 - (cos(angle - 0.55) * len).toFloat()
        val p1y = y2 - (sin(angle - 0.55) * len).toFloat()
        val p2x = x2 - (cos(angle + 0.55) * len).toFloat()
        val p2y = y2 - (sin(angle + 0.55) * len).toFloat()
        canvas.drawLine(x2, y2, p1x, p1y, paint)
        canvas.drawLine(x2, y2, p2x, p2y, paint)
    }

    private fun drawTextAnnotation(canvas: Canvas, x: Float, y: Float, text: String) {
        if (text.isBlank()) return
        val density = resources.displayMetrics.density
        paint.style = Paint.Style.FILL
        paint.textSize = 18f * resources.displayMetrics.scaledDensity
        paint.typeface = android.graphics.Typeface.DEFAULT_BOLD

        val padding = 8f * density
        val maxWidth = width * 0.55f
        val words = text.split(" ")
        val lines = mutableListOf<String>()
        var line = ""
        for (word in words) {
            val candidate = if (line.isEmpty()) word else "$line $word"
            if (paint.measureText(candidate) > maxWidth && line.isNotEmpty()) {
                lines += line
                line = word
            } else {
                line = candidate
            }
        }
        if (line.isNotEmpty()) lines += line

        val lineHeight = paint.textSize * 1.25f
        val boxWidth = (lines.maxOfOrNull { paint.measureText(it) } ?: 0f) + padding * 2
        val boxHeight = lineHeight * lines.size + padding * 2
        paint.color = Color.argb(185, 0, 0, 0)
        canvas.drawRoundRect(x, y, x + boxWidth, y + boxHeight, 8f * density, 8f * density, paint)

        paint.color = Color.WHITE
        lines.forEachIndexed { index, value ->
            canvas.drawText(value, x + padding, y + padding + paint.textSize + lineHeight * index, paint)
        }
    }
}
