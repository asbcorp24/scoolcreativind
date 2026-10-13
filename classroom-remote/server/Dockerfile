FROM golang:1.24-alpine AS build
WORKDIR /src
COPY . .
RUN go mod tidy && CGO_ENABLED=0 GOOS=linux GOARCH=amd64 go build -trimpath -ldflags="-s -w" -o /out/classroom-server .

FROM alpine:3.21
RUN apk add --no-cache ca-certificates
WORKDIR /app
COPY --from=build /out/classroom-server /app/classroom-server
COPY config.example.json /app/config.example.json
VOLUME ["/app/data"]
EXPOSE 80 443 8080 3478/tcp 3478/udp 49160-49260/udp 50000-50100/udp
ENTRYPOINT ["/app/classroom-server"]
