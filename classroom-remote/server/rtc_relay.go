package main

import (
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"strconv"
	"strings"
	"sync"

	"github.com/pion/interceptor"
	"github.com/pion/rtp"
	"github.com/pion/webrtc/v4"
)

const (
	trackLectureVideo = "lecture-video"
	trackLectureAudio = "lecture-audio"
	trackPrivateAudio = "private-audio"
	trackRemoteVideo  = "remote-video"
	trackRemoteAudio  = "remote-audio"
)

type rtcPeer struct {
	mu       sync.RWMutex
	client   *Client
	pc       *webrtc.PeerConnection
	dc       *webrtc.DataChannel
	outbound map[string]*webrtc.TrackLocalStaticRTP
}

func (p *rtcPeer) setDataChannel(dc *webrtc.DataChannel) {
	p.mu.Lock()
	p.dc = dc
	p.mu.Unlock()
}

func (p *rtcPeer) dataChannel() *webrtc.DataChannel {
	p.mu.RLock()
	defer p.mu.RUnlock()
	return p.dc
}

func (p *rtcPeer) setOutbound(id string, track *webrtc.TrackLocalStaticRTP) {
	p.mu.Lock()
	if p.outbound == nil {
		p.outbound = map[string]*webrtc.TrackLocalStaticRTP{}
	}
	p.outbound[id] = track
	p.mu.Unlock()
}

func (p *rtcPeer) outboundTrack(id string) *webrtc.TrackLocalStaticRTP {
	p.mu.RLock()
	defer p.mu.RUnlock()
	return p.outbound[id]
}

type RTCRelay struct {
	mu    sync.RWMutex
	peers map[string]*rtcPeer
	api   *webrtc.API

	targetMu      sync.RWMutex
	remoteTargets map[string]string
	audioTargets  map[string]string
}

func NewRTCRelay() (*RTCRelay, error) {
	se := webrtc.SettingEngine{}
	minPort := uint16(envInt("RTC_MIN_PORT", 50000))
	maxPort := uint16(envInt("RTC_MAX_PORT", 50100))
	if err := se.SetEphemeralUDPPortRange(minPort, maxPort); err != nil {
		return nil, err
	}

	if publicIP := strings.TrimSpace(os.Getenv("PUBLIC_IP")); publicIP != "" {
		se.SetNAT1To1IPs([]string{publicIP}, webrtc.ICECandidateTypeHost)
	}

	me := &webrtc.MediaEngine{}
	if err := me.RegisterDefaultCodecs(); err != nil {
		return nil, err
	}

	ir := &interceptor.Registry{}
	if err := webrtc.RegisterDefaultInterceptors(me, ir); err != nil {
		return nil, err
	}

	api := webrtc.NewAPI(
		webrtc.WithSettingEngine(se),
		webrtc.WithMediaEngine(me),
		webrtc.WithInterceptorRegistry(ir),
	)

	return &RTCRelay{
		peers:         map[string]*rtcPeer{},
		api:           api,
		remoteTargets: map[string]string{},
		audioTargets:  map[string]string{},
	}, nil
}

func (r *RTCRelay) CloseClient(id string) {
	r.mu.Lock()
	p := r.peers[id]
	delete(r.peers, id)
	r.mu.Unlock()

	r.targetMu.Lock()
	delete(r.remoteTargets, id)
	delete(r.audioTargets, id)
	for teacher, student := range r.remoteTargets {
		if student == id {
			delete(r.remoteTargets, teacher)
		}
	}
	for teacher, student := range r.audioTargets {
		if student == id {
			delete(r.audioTargets, teacher)
		}
	}
	r.targetMu.Unlock()

	if p != nil && p.pc != nil {
		_ = p.pc.Close()
	}
}

func (r *RTCRelay) SetRemoteTarget(teacherID, studentID string) {
	r.targetMu.Lock()
	r.remoteTargets[teacherID] = studentID
	r.targetMu.Unlock()
}

func (r *RTCRelay) ClearRemoteTarget(teacherID string) {
	r.targetMu.Lock()
	delete(r.remoteTargets, teacherID)
	r.targetMu.Unlock()
}

func (r *RTCRelay) SetAudioTarget(teacherID, studentID string) {
	r.targetMu.Lock()
	r.audioTargets[teacherID] = studentID
	r.targetMu.Unlock()
}

func (r *RTCRelay) ClearAudioTarget(teacherID string) {
	r.targetMu.Lock()
	delete(r.audioTargets, teacherID)
	r.targetMu.Unlock()
}

func (r *RTCRelay) AcceptOffer(c *Client, sdp string) (string, error) {
	r.CloseClient(c.ID)

	pc, err := r.api.NewPeerConnection(webrtc.Configuration{})
	if err != nil {
		return "", err
	}

	p := &rtcPeer{
		client:   c,
		pc:       pc,
		outbound: map[string]*webrtc.TrackLocalStaticRTP{},
	}
	if err := r.prepareMediaTransceivers(p); err != nil {
		_ = pc.Close()
		return "", err
	}

	r.mu.Lock()
	r.peers[c.ID] = p
	r.mu.Unlock()

	pc.OnConnectionStateChange(func(state webrtc.PeerConnectionState) {
		if state == webrtc.PeerConnectionStateFailed ||
			state == webrtc.PeerConnectionStateClosed ||
			state == webrtc.PeerConnectionStateDisconnected {
			r.CloseClient(c.ID)
		}
	})

	pc.OnDataChannel(func(dc *webrtc.DataChannel) {
		if dc.Label() != "classroom" {
			return
		}
		p.setDataChannel(dc)
		dc.OnMessage(func(msg webrtc.DataChannelMessage) {
			if msg.IsString {
				r.routeText(c, msg.Data)
			}
		})
	})

	pc.OnTrack(func(track *webrtc.TrackRemote, receiver *webrtc.RTPReceiver) {
		trackID := track.ID()
		if trackID == "" {
			return
		}

		go func() {
			for {
				packet, _, readErr := track.ReadRTP()
				if readErr != nil {
					return
				}
				r.routeRTP(c, trackID, packet)
			}
		}()

		// Drain RTCP generated for the remote sender.
		go func() {
			buf := make([]byte, 1500)
			for {
				if _, _, readErr := receiver.Read(buf); readErr != nil {
					return
				}
			}
		}()
	})

	if err := pc.SetRemoteDescription(webrtc.SessionDescription{
		Type: webrtc.SDPTypeOffer,
		SDP:  sdp,
	}); err != nil {
		_ = pc.Close()
		return "", err
	}

	answer, err := pc.CreateAnswer(nil)
	if err != nil {
		_ = pc.Close()
		return "", err
	}

	gatherComplete := webrtc.GatheringCompletePromise(pc)
	if err := pc.SetLocalDescription(answer); err != nil {
		_ = pc.Close()
		return "", err
	}
	<-gatherComplete

	local := pc.LocalDescription()
	if local == nil {
		_ = pc.Close()
		return "", errors.New("missing local WebRTC description")
	}
	return local.SDP, nil
}

func (r *RTCRelay) prepareMediaTransceivers(p *rtcPeer) error {
	type mediaSpec struct {
		id   string
		kind webrtc.RTPCodecType
	}
	specs := []mediaSpec{
		{trackLectureVideo, webrtc.RTPCodecTypeVideo},
		{trackLectureAudio, webrtc.RTPCodecTypeAudio},
		{trackPrivateAudio, webrtc.RTPCodecTypeAudio},
		{trackRemoteVideo, webrtc.RTPCodecTypeVideo},
		{trackRemoteAudio, webrtc.RTPCodecTypeAudio},
	}

	for _, spec := range specs {
		if r.serverSendsTrack(p.client.Role, spec.id) {
			capability, ok := codecForTrack(spec.id)
			if !ok {
				return fmt.Errorf("missing codec for track %s", spec.id)
			}

			local, err := webrtc.NewTrackLocalStaticRTP(
				capability,
				spec.id,
				"classroom",
			)
			if err != nil {
				return fmt.Errorf("create outbound track %s: %w", spec.id, err)
			}

			transceiver, err := p.pc.AddTransceiverFromTrack(
				local,
				webrtc.RTPTransceiverInit{
					Direction: webrtc.RTPTransceiverDirectionSendonly,
				},
			)
			if err != nil {
				return fmt.Errorf("add outbound transceiver %s: %w", spec.id, err)
			}

			p.setOutbound(spec.id, local)
			go func(sender *webrtc.RTPSender) {
				buf := make([]byte, 1500)
				for {
					if _, _, readErr := sender.Read(buf); readErr != nil {
						return
					}
				}
			}(transceiver.Sender())
			continue
		}

		if _, err := p.pc.AddTransceiverFromKind(
			spec.kind,
			webrtc.RTPTransceiverInit{
				Direction: webrtc.RTPTransceiverDirectionRecvonly,
			},
		); err != nil {
			return fmt.Errorf("add inbound transceiver %s: %w", spec.id, err)
		}
	}

	return nil
}

func (r *RTCRelay) serverSendsTrack(role, mid string) bool {
	if role == "student" {
		return mid == trackLectureVideo ||
			mid == trackLectureAudio ||
			mid == trackPrivateAudio
	}
	if role == "teacher" {
		return mid == trackRemoteVideo ||
			mid == trackRemoteAudio
	}
	return false
}

func codecForTrack(mid string) (webrtc.RTPCodecCapability, bool) {
	switch mid {
	case trackLectureVideo, trackRemoteVideo:
		return webrtc.RTPCodecCapability{
			MimeType:    webrtc.MimeTypeH264,
			ClockRate:   90000,
			SDPFmtpLine: "profile-level-id=42e01f;packetization-mode=1;level-asymmetry-allowed=1",
		}, true
	case trackLectureAudio, trackPrivateAudio, trackRemoteAudio:
		return webrtc.RTPCodecCapability{
			MimeType:  webrtc.MimeTypeOpus,
			ClockRate: 48000,
			Channels:  2,
		}, true
	default:
		return webrtc.RTPCodecCapability{}, false
	}
}

func (r *RTCRelay) routeRTP(from *Client, trackID string, packet *rtp.Packet) {
	if packet == nil {
		return
	}

	switch from.Role {
	case "teacher":
		switch trackID {
		case trackLectureVideo, trackLectureAudio:
			r.writeToRole(from.LessonID, "student", trackID, packet)
		case trackPrivateAudio:
			target := r.audioTarget(from.ID)
			if target != "" {
				r.writeToPeer(from.LessonID, target, trackPrivateAudio, packet)
			}
		}

	case "student":
		switch trackID {
		case trackRemoteVideo:
			for _, teacher := range r.teachersTargeting(from.LessonID, from.ID, false) {
				r.writeToPeer(from.LessonID, teacher, trackRemoteVideo, packet)
			}
		case trackRemoteAudio:
			for _, teacher := range r.teachersTargeting(from.LessonID, from.ID, true) {
				r.writeToPeer(from.LessonID, teacher, trackRemoteAudio, packet)
			}
		}
	}
}

func (r *RTCRelay) writeToRole(lessonID, role, trackID string, packet *rtp.Packet) {
	r.mu.RLock()
	targets := make([]*webrtc.TrackLocalStaticRTP, 0)
	for _, p := range r.peers {
		if p.client.LessonID == lessonID && p.client.Role == role {
			if track := p.outboundTrack(trackID); track != nil {
				targets = append(targets, track)
			}
		}
	}
	r.mu.RUnlock()

	for _, track := range targets {
		_ = track.WriteRTP(packet)
	}
}

func (r *RTCRelay) writeToPeer(lessonID, clientID, trackID string, packet *rtp.Packet) {
	p := r.peer(clientID)
	if p == nil || p.client.LessonID != lessonID {
		return
	}
	if track := p.outboundTrack(trackID); track != nil {
		_ = track.WriteRTP(packet)
	}
}

func (r *RTCRelay) teachersTargeting(lessonID, studentID string, audio bool) []string {
	r.targetMu.RLock()
	var source map[string]string
	if audio {
		source = r.audioTargets
	} else {
		source = r.remoteTargets
	}

	candidates := make([]string, 0)
	for teacher, student := range source {
		if student == studentID {
			candidates = append(candidates, teacher)
		}
	}
	r.targetMu.RUnlock()

	out := make([]string, 0, len(candidates))
	for _, teacher := range candidates {
		if p := r.peer(teacher); p != nil &&
			p.client.LessonID == lessonID &&
			p.client.Role == "teacher" {
			out = append(out, teacher)
		}
	}
	return out
}

func (r *RTCRelay) audioTarget(teacherID string) string {
	r.targetMu.RLock()
	defer r.targetMu.RUnlock()
	return r.audioTargets[teacherID]
}

func (r *RTCRelay) routeText(from *Client, data []byte) {
	var msg map[string]any
	if json.Unmarshal(data, &msg) != nil {
		return
	}
	target, _ := msg["target"].(string)
	if target == "" {
		return
	}

	if from.Role == "teacher" {
		if t := r.peer(target); t != nil &&
			t.client.LessonID == from.LessonID &&
			t.client.Role == "student" {
			r.sendText(t, data)
		}
		return
	}

	if from.Role == "student" {
		if t := r.peer(target); t != nil &&
			t.client.LessonID == from.LessonID &&
			t.client.Role == "teacher" {
			r.sendText(t, data)
		}
	}
}

func (r *RTCRelay) peer(id string) *rtcPeer {
	r.mu.RLock()
	defer r.mu.RUnlock()
	return r.peers[id]
}

func (r *RTCRelay) sendText(p *rtcPeer, data []byte) {
	if p == nil {
		return
	}
	dc := p.dataChannel()
	if dc == nil || dc.ReadyState() != webrtc.DataChannelStateOpen {
		return
	}
	_ = dc.SendText(string(data))
}

func envInt(name string, fallback int) int {
	v := strings.TrimSpace(os.Getenv(name))
	if v == "" {
		return fallback
	}
	n, err := strconv.Atoi(v)
	if err != nil {
		fmt.Printf("invalid %s=%q, using %d\n", name, v, fallback)
		return fallback
	}
	return n
}
