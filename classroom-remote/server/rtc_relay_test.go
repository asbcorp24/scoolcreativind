package main

import (
	"fmt"
	"strings"
	"testing"

	"github.com/pion/webrtc/v4"
)

func testOffer(t *testing.T, role string) string {
	t.Helper()

	pc, err := webrtc.NewPeerConnection(webrtc.Configuration{})
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = pc.Close() })

	mids := []string{
		trackLectureVideo,
		trackLectureAudio,
		trackPrivateAudio,
		trackRemoteVideo,
		trackRemoteAudio,
	}

	teacher := role == "teacher"
	directions := []webrtc.RTPTransceiverDirection{
		webrtc.RTPTransceiverDirectionRecvonly,
		webrtc.RTPTransceiverDirectionRecvonly,
		webrtc.RTPTransceiverDirectionRecvonly,
		webrtc.RTPTransceiverDirectionSendonly,
		webrtc.RTPTransceiverDirectionSendonly,
	}
	if teacher {
		directions = []webrtc.RTPTransceiverDirection{
			webrtc.RTPTransceiverDirectionSendonly,
			webrtc.RTPTransceiverDirectionSendonly,
			webrtc.RTPTransceiverDirectionSendonly,
			webrtc.RTPTransceiverDirectionRecvonly,
			webrtc.RTPTransceiverDirectionRecvonly,
		}
	}

	kinds := []webrtc.RTPCodecType{
		webrtc.RTPCodecTypeVideo,
		webrtc.RTPCodecTypeAudio,
		webrtc.RTPCodecTypeAudio,
		webrtc.RTPCodecTypeVideo,
		webrtc.RTPCodecTypeAudio,
	}

	for i := range kinds {
		if _, err := pc.AddTransceiverFromKind(kinds[i], webrtc.RTPTransceiverInit{
			Direction: directions[i],
		}); err != nil {
			t.Fatal(err)
		}
	}

	offer, err := pc.CreateOffer(nil)
	if err != nil {
		t.Fatal(err)
	}
	gather := webrtc.GatheringCompletePromise(pc)
	if err := pc.SetLocalDescription(offer); err != nil {
		t.Fatal(err)
	}
	<-gather

	local := pc.LocalDescription()
	if local == nil {
		t.Fatal("missing test local description")
	}
	sdp := local.SDP

	oldBundle := make([]string, len(mids))
	for i := range mids {
		oldBundle[i] = fmt.Sprint(i)
		sdp = strings.ReplaceAll(
			sdp,
			fmt.Sprintf("a=mid:%d\r\n", i),
			fmt.Sprintf("a=mid:%s\r\n", mids[i]),
		)
	}
	sdp = strings.ReplaceAll(
		sdp,
		"a=group:BUNDLE "+strings.Join(oldBundle, " "),
		"a=group:BUNDLE "+strings.Join(mids, " "),
	)

	return sdp
}

func TestAcceptStudentMediaOffer(t *testing.T) {
	t.Setenv("RTC_MIN_PORT", "52000")
	t.Setenv("RTC_MAX_PORT", "52100")

	relay, err := NewRTCRelay()
	if err != nil {
		t.Fatal(err)
	}

	client := &Client{
		ID:       "student-test",
		Role:     "student",
		LessonID: "lesson-test",
		Name:     "Student",
	}
	defer relay.CloseClient(client.ID)

	answer, err := relay.AcceptOffer(client, testOffer(t, "student"))
	if err != nil {
		t.Fatal(err)
	}
	if !strings.Contains(answer, "a=mid:"+trackLectureVideo) ||
		!strings.Contains(answer, "a=mid:"+trackRemoteVideo) {
		t.Fatalf("answer does not preserve expected media mids:\n%s", answer)
	}

	peer := relay.peer(client.ID)
	if peer == nil {
		t.Fatal("student peer missing")
	}
	for _, id := range []string{trackLectureVideo, trackLectureAudio, trackPrivateAudio} {
		if peer.outboundTrack(id) == nil {
			t.Fatalf("outbound student track %q not attached", id)
		}
	}
}

func TestAcceptTeacherMediaOffer(t *testing.T) {
	t.Setenv("RTC_MIN_PORT", "52200")
	t.Setenv("RTC_MAX_PORT", "52300")

	relay, err := NewRTCRelay()
	if err != nil {
		t.Fatal(err)
	}

	client := &Client{
		ID:       "teacher-test",
		Role:     "teacher",
		LessonID: "lesson-test",
		Name:     "Teacher",
	}
	defer relay.CloseClient(client.ID)

	answer, err := relay.AcceptOffer(client, testOffer(t, "teacher"))
	if err != nil {
		t.Fatal(err)
	}
	if !strings.Contains(answer, "a=mid:"+trackRemoteVideo) ||
		!strings.Contains(answer, "a=mid:"+trackRemoteAudio) {
		t.Fatalf("answer does not preserve expected media mids:\n%s", answer)
	}

	peer := relay.peer(client.ID)
	if peer == nil {
		t.Fatal("teacher peer missing")
	}
	for _, id := range []string{trackRemoteVideo, trackRemoteAudio} {
		if peer.outboundTrack(id) == nil {
			t.Fatalf("outbound teacher track %q not attached", id)
		}
	}
}
