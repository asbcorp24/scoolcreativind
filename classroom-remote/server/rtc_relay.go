package main

import (
	"encoding/binary"
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"strconv"
	"strings"
	"sync"

	"github.com/pion/interceptor"
	"github.com/pion/webrtc/v4"
)

const (
	frameLectureVideo byte = 1
	frameLectureAudio byte = 2
	frameStudentVideo byte = 3
	frameStudentAudio byte = 4
	frameTeacherPrivateAudio byte = 5
)

type rtcPeer struct {
	mu     sync.RWMutex
	client *Client
	pc     *webrtc.PeerConnection
	dc     *webrtc.DataChannel
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

type RTCRelay struct {
	mu    sync.RWMutex
	peers map[string]*rtcPeer
	api   *webrtc.API
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
		peers: map[string]*rtcPeer{},
		api:   api,
	}, nil
}

func (r *RTCRelay) CloseClient(id string) {
	r.mu.Lock()
	p := r.peers[id]
	delete(r.peers, id)
	r.mu.Unlock()
	if p != nil && p.pc != nil {
		_ = p.pc.Close()
	}
}

func (r *RTCRelay) AcceptOffer(c *Client, sdp string) (string, error) {
	r.CloseClient(c.ID)

	pc, err := r.api.NewPeerConnection(webrtc.Configuration{})
	if err != nil {
		return "", err
	}

	p := &rtcPeer{client: c, pc: pc}
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
				return
			}
			if len(msg.Data) < 1 {
				return
			}
			kind := msg.Data[0]

			if c.Role == "teacher" && kind == frameTeacherPrivateAudio {
				// kind(1), targetLen(2), target, frame/chunk metadata(8), payload
				if len(msg.Data) < 11 {
					return
				}
				targetLen := int(binary.BigEndian.Uint16(msg.Data[1:3]))
				if targetLen == 0 || len(msg.Data) < 3+targetLen+8 {
					return
				}
				target := string(msg.Data[3 : 3+targetLen])
				payload := msg.Data[3+targetLen:]
				if peer := r.peer(target); peer != nil &&
					peer.client.LessonID == c.LessonID &&
					peer.client.Role == "student" {
					r.sendBinary(peer, makeRelayFrame(kind, c.ID, payload))
				}
				return
			}

			payload := msg.Data[1:]
			switch c.Role {
			case "teacher":
				if kind == frameLectureVideo || kind == frameLectureAudio {
					r.broadcast(c.LessonID, c.ID, "student", kind, payload)
				}
			case "student":
				if kind == frameStudentVideo || kind == frameStudentAudio {
					r.broadcast(c.LessonID, c.ID, "teacher", kind, payload)
				}
			}
		})
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
		if t := r.peer(target); t != nil && t.client.LessonID == from.LessonID && t.client.Role == "student" {
			r.sendText(t, data)
		}
		return
	}

	if from.Role == "student" {
		if t := r.peer(target); t != nil && t.client.LessonID == from.LessonID && t.client.Role == "teacher" {
			r.sendText(t, data)
		}
	}
}

func (r *RTCRelay) peer(id string) *rtcPeer {
	r.mu.RLock()
	defer r.mu.RUnlock()
	return r.peers[id]
}

func (r *RTCRelay) broadcast(lessonID, sourceID, role string, kind byte, payload []byte) {
	frame := makeRelayFrame(kind, sourceID, payload)

	r.mu.RLock()
	defer r.mu.RUnlock()
	for _, p := range r.peers {
		if p.client.LessonID != lessonID || p.client.Role != role {
			continue
		}
		dc := p.dataChannel()
		if dc == nil || dc.ReadyState() != webrtc.DataChannelStateOpen {
			continue
		}
		_ = dc.Send(frame)
	}
}

func (r *RTCRelay) sendBinary(p *rtcPeer, data []byte) {
	if p == nil {
		return
	}
	dc := p.dataChannel()
	if dc == nil || dc.ReadyState() != webrtc.DataChannelStateOpen {
		return
	}
	_ = dc.Send(data)
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

func makeRelayFrame(kind byte, sourceID string, payload []byte) []byte {
	id := []byte(sourceID)
	if len(id) > 65535 {
		id = id[:65535]
	}
	out := make([]byte, 3+len(id)+len(payload))
	out[0] = kind
	binary.BigEndian.PutUint16(out[1:3], uint16(len(id)))
	copy(out[3:3+len(id)], id)
	copy(out[3+len(id):], payload)
	return out
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
