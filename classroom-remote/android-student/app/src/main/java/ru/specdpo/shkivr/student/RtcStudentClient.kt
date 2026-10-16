package ru.specdpo.shkivr.student

import android.content.Context
import org.webrtc.AudioTrack
import org.webrtc.DataChannel
import org.webrtc.DefaultVideoDecoderFactory
import org.webrtc.EglBase
import org.webrtc.IceCandidate
import org.webrtc.audio.JavaAudioDeviceModule
import org.webrtc.MediaConstraints
import org.webrtc.MediaStream
import org.webrtc.MediaStreamTrack
import org.webrtc.PeerConnection
import org.webrtc.PeerConnectionFactory
import org.webrtc.RtpReceiver
import org.webrtc.RtpTransceiver
import org.webrtc.SdpObserver
import org.webrtc.SessionDescription
import org.webrtc.SurfaceViewRenderer
import org.webrtc.VideoTrack
import java.net.URI
import java.util.concurrent.atomic.AtomicBoolean

class RtcStudentClient(
    context: Context,
    private val renderer: SurfaceViewRenderer,
    private val listener: Listener
) {
    interface Listener {
        fun onOfferReady(sdp: String)
        fun onState(text: String)
        fun onVideoAvailable()
        fun onConnectedChanged(connected: Boolean)
    }

    private val appContext = context.applicationContext
    private val eglBase = EglBase.create()
    private val audioDeviceModule = JavaAudioDeviceModule.builder(appContext).createAudioDeviceModule()
    private val factory: PeerConnectionFactory
    private var peerConnection: PeerConnection? = null
    private var dataChannel: DataChannel? = null
    private var remoteVideoTrack: VideoTrack? = null
    private val offerSent = AtomicBoolean(false)

    init {
        PeerConnectionFactory.initialize(
            PeerConnectionFactory.InitializationOptions.builder(appContext)
                .setEnableInternalTracer(false)
                .createInitializationOptions()
        )

        factory = PeerConnectionFactory.builder()
            .setAudioDeviceModule(audioDeviceModule)
            .setVideoDecoderFactory(DefaultVideoDecoderFactory(eglBase.eglBaseContext))
            .createPeerConnectionFactory()

        renderer.init(eglBase.eglBaseContext, null)
        renderer.setMirror(false)
        renderer.setEnableHardwareScaler(true)
        renderer.setScalingType(org.webrtc.RendererCommon.ScalingType.SCALE_ASPECT_FIT)
    }

    fun start(rawIceServers: List<String>) {
        stopPeer()

        val iceServers = if (rawIceServers.isEmpty()) {
            listOf(PeerConnection.IceServer.builder("stun:stun.l.google.com:19302").createIceServer())
        } else {
            rawIceServers.mapNotNull(::parseIceServer)
        }

        val config = PeerConnection.RTCConfiguration(iceServers).apply {
            sdpSemantics = PeerConnection.SdpSemantics.UNIFIED_PLAN
            continualGatheringPolicy = PeerConnection.ContinualGatheringPolicy.GATHER_ONCE
        }

        val pc = factory.createPeerConnection(config, observer)
        if (pc == null) {
            listener.onState("Не удалось создать WebRTC соединение")
            return
        }
        peerConnection = pc
        offerSent.set(false)

        pc.addTransceiver(
            MediaStreamTrack.MediaType.MEDIA_TYPE_VIDEO,
            RtpTransceiver.RtpTransceiverInit(RtpTransceiver.RtpTransceiverDirection.RECV_ONLY)
        )
        pc.addTransceiver(
            MediaStreamTrack.MediaType.MEDIA_TYPE_AUDIO,
            RtpTransceiver.RtpTransceiverInit(RtpTransceiver.RtpTransceiverDirection.RECV_ONLY)
        )
        pc.addTransceiver(
            MediaStreamTrack.MediaType.MEDIA_TYPE_AUDIO,
            RtpTransceiver.RtpTransceiverInit(RtpTransceiver.RtpTransceiverDirection.RECV_ONLY)
        )
        pc.addTransceiver(
            MediaStreamTrack.MediaType.MEDIA_TYPE_VIDEO,
            RtpTransceiver.RtpTransceiverInit(RtpTransceiver.RtpTransceiverDirection.INACTIVE)
        )
        pc.addTransceiver(
            MediaStreamTrack.MediaType.MEDIA_TYPE_AUDIO,
            RtpTransceiver.RtpTransceiverInit(RtpTransceiver.RtpTransceiverDirection.INACTIVE)
        )

        dataChannel = pc.createDataChannel("classroom", DataChannel.Init())

        pc.createOffer(object : SimpleSdpObserver() {
            override fun onCreateSuccess(description: SessionDescription) {
                pc.setLocalDescription(object : SimpleSdpObserver() {
                    override fun onSetFailure(error: String?) {
                        listener.onState("Ошибка local SDP: ${error.orEmpty()}")
                    }
                }, description)
            }

            override fun onCreateFailure(error: String?) {
                listener.onState("Ошибка создания WebRTC offer: ${error.orEmpty()}")
            }
        }, MediaConstraints())
    }

    fun handleAnswer(sdp: String) {
        val pc = peerConnection ?: return
        pc.setRemoteDescription(
            object : SimpleSdpObserver() {
                override fun onSetSuccess() {
                    listener.onState("WebRTC: ответ сервера принят")
                }

                override fun onSetFailure(error: String?) {
                    listener.onState("Ошибка WebRTC answer: ${error.orEmpty()}")
                }
            },
            SessionDescription(SessionDescription.Type.ANSWER, sdp)
        )
    }

    fun stopPeer() {
        remoteVideoTrack?.removeSink(renderer)
        remoteVideoTrack = null
        dataChannel?.close()
        dataChannel?.dispose()
        dataChannel = null
        peerConnection?.close()
        peerConnection?.dispose()
        peerConnection = null
        offerSent.set(false)
        renderer.clearImage()
        listener.onConnectedChanged(false)
    }

    fun release() {
        stopPeer()
        renderer.release()
        factory.dispose()
        audioDeviceModule.release()
        eglBase.release()
    }

    private val observer = object : PeerConnection.Observer {
        override fun onSignalingChange(newState: PeerConnection.SignalingState) {
            listener.onState("WebRTC signaling: $newState")
        }

        override fun onIceConnectionChange(newState: PeerConnection.IceConnectionState) {
            listener.onState("WebRTC ICE: $newState")
            listener.onConnectedChanged(
                newState == PeerConnection.IceConnectionState.CONNECTED ||
                    newState == PeerConnection.IceConnectionState.COMPLETED
            )
        }

        override fun onIceConnectionReceivingChange(receiving: Boolean) = Unit

        override fun onIceGatheringChange(newState: PeerConnection.IceGatheringState) {
            if (newState != PeerConnection.IceGatheringState.COMPLETE) return
            val pc = peerConnection ?: return
            val local = pc.localDescription ?: return
            if (offerSent.compareAndSet(false, true)) {
                listener.onOfferReady(local.description)
            }
        }

        override fun onIceCandidate(candidate: IceCandidate) = Unit
        override fun onIceCandidatesRemoved(candidates: Array<out IceCandidate>) = Unit
        override fun onAddStream(stream: MediaStream) = Unit
        override fun onRemoveStream(stream: MediaStream) = Unit
        override fun onDataChannel(channel: DataChannel) { dataChannel = channel }
        override fun onRenegotiationNeeded() = Unit

        override fun onAddTrack(receiver: RtpReceiver, mediaStreams: Array<out MediaStream>) {
            attachTrack(receiver.track())
        }

        override fun onTrack(transceiver: RtpTransceiver) {
            attachTrack(transceiver.receiver.track())
        }

        override fun onConnectionChange(newState: PeerConnection.PeerConnectionState) {
            listener.onState("WebRTC: $newState")
            listener.onConnectedChanged(newState == PeerConnection.PeerConnectionState.CONNECTED)
        }
    }

    private fun attachTrack(track: MediaStreamTrack?) {
        when (track) {
            is VideoTrack -> {
                remoteVideoTrack?.removeSink(renderer)
                remoteVideoTrack = track
                track.setEnabled(true)
                track.addSink(renderer)
                listener.onVideoAvailable()
            }
            is AudioTrack -> track.setEnabled(true)
        }
    }

    private fun parseIceServer(raw: String): PeerConnection.IceServer? {
        return runCatching {
            when {
                raw.startsWith("turn://", ignoreCase = true) ||
                    raw.startsWith("turns://", ignoreCase = true) -> {
                    val uri = URI(raw)
                    val userInfo = uri.userInfo.orEmpty()
                    val user = userInfo.substringBefore(':', "")
                    val password = userInfo.substringAfter(':', "")
                    val scheme = if (raw.startsWith("turns://", true)) "turns" else "turn"
                    val host = uri.host ?: return null
                    val port = if (uri.port > 0) uri.port else if (scheme == "turns") 5349 else 3478
                    val transport = uri.query?.takeIf { it.isNotBlank() }
                    val url = buildString {
                        append(scheme).append(':').append(host).append(':').append(port)
                        if (transport != null) append('?').append(transport)
                    }
                    PeerConnection.IceServer.builder(url)
                        .setUsername(user)
                        .setPassword(password)
                        .createIceServer()
                }
                raw.startsWith("stun://", ignoreCase = true) -> {
                    val uri = URI(raw)
                    val host = uri.host ?: return null
                    val port = if (uri.port > 0) uri.port else 3478
                    PeerConnection.IceServer.builder("stun:$host:$port").createIceServer()
                }
                else -> PeerConnection.IceServer.builder(raw).createIceServer()
            }
        }.getOrNull()
    }

    private open class SimpleSdpObserver : SdpObserver {
        override fun onCreateSuccess(description: SessionDescription) = Unit
        override fun onSetSuccess() = Unit
        override fun onCreateFailure(error: String?) = Unit
        override fun onSetFailure(error: String?) = Unit
    }
}
