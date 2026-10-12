FROM golang:1.24-alpine AS build
WORKDIR /src
COPY go.mod ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -o /out/classroom-server .

FROM alpine:3.21
RUN apk add --no-cache ffmpeg
RUN adduser -D -H app
USER app
WORKDIR /app
COPY --from=build /out/classroom-server /app/classroom-server
ENV ADDR=:8080 STORAGE_DIR=/data
VOLUME ["/data"]
EXPOSE 8080
ENTRYPOINT ["/app/classroom-server"]
