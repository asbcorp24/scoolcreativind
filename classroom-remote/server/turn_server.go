package main

import (
	"fmt"
	"net"
	"strconv"

	"github.com/pion/turn/v5"
)

type EmbeddedTURN struct {
	server *turn.Server
	urls   []string
}

func StartEmbeddedTURN(cfg Config) (*EmbeddedTURN, error) {
	if !cfg.TURNEnabled || cfg.PublicIP == "" {
		return nil, nil
	}

	publicIP := net.ParseIP(cfg.PublicIP)
	if publicIP == nil {
		return nil, fmt.Errorf("invalid public_ip %q", cfg.PublicIP)
	}

	_, portText, err := net.SplitHostPort(normalizeListen(cfg.TURNListen))
	if err != nil {
		return nil, err
	}
	port, err := strconv.Atoi(portText)
	if err != nil {
		return nil, err
	}

	udpListener, err := net.ListenPacket("udp4", net.JoinHostPort("0.0.0.0", strconv.Itoa(port)))
	if err != nil {
		return nil, fmt.Errorf("TURN UDP listen: %w", err)
	}

	tcpListener, err := net.Listen("tcp4", net.JoinHostPort("0.0.0.0", strconv.Itoa(port)))
	if err != nil {
		_ = udpListener.Close()
		return nil, fmt.Errorf("TURN TCP listen: %w", err)
	}

	newGenerator := func() *turn.RelayAddressGeneratorPortRange {
		return &turn.RelayAddressGeneratorPortRange{
			RelayAddress: publicIP,
			Address:      "0.0.0.0",
			MinPort:      uint16(cfg.TURNMinPort),
			MaxPort:      uint16(cfg.TURNMaxPort),
		}
	}

	authKey := turn.GenerateAuthKey(cfg.TURNUsername, cfg.TURNRealm, cfg.TURNPassword)
	server, err := turn.NewServer(turn.ServerConfig{
		Realm: cfg.TURNRealm,
		AuthHandler: func(ra *turn.RequestAttributes) (string, []byte, bool) {
			if ra.Username != cfg.TURNUsername {
				return "", nil, false
			}
			return cfg.TURNUsername, authKey, true
		},
		PacketConnConfigs: []turn.PacketConnConfig{{
			PacketConn:             udpListener,
			RelayAddressGenerator: newGenerator(),
		}},
		ListenerConfigs: []turn.ListenerConfig{{
			Listener:              tcpListener,
			RelayAddressGenerator: newGenerator(),
		}},
	})
	if err != nil {
		_ = udpListener.Close()
		_ = tcpListener.Close()
		return nil, err
	}

	host := cfg.Domain
	if host == "" {
		host = cfg.PublicIP
	}
	credentialPrefix := "turn://" + cfg.TURNUsername + ":" + cfg.TURNPassword + "@"
	urls := []string{
		fmt.Sprintf("%s%s:%d?transport=udp", credentialPrefix, host, port),
		fmt.Sprintf("%s%s:%d?transport=tcp", credentialPrefix, host, port),
		fmt.Sprintf("stun://%s:%d", host, port),
	}
	return &EmbeddedTURN{server: server, urls: urls}, nil
}

func (t *EmbeddedTURN) Close() error {
	if t == nil || t.server == nil {
		return nil
	}
	return t.server.Close()
}

func normalizeListen(addr string) string {
	if addr == "" {
		return ":3478"
	}
	if _, _, err := net.SplitHostPort(addr); err == nil {
		return addr
	}
	if addr[0] == ':' {
		return "0.0.0.0" + addr
	}
	return addr
}
