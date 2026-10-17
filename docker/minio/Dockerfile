# Update these two official source release tags explicitly when upgrading MinIO.
FROM golang:1.24.8-alpine3.22 AS build

ARG MINIO_VERSION=RELEASE.2025-10-15T17-29-55Z
ARG MC_VERSION=RELEASE.2025-08-13T08-35-41Z
ENV CGO_ENABLED=0 GOBIN=/out

RUN go install "github.com/minio/minio@${MINIO_VERSION}" \
    && go install "github.com/minio/mc@${MC_VERSION}"


FROM alpine:3.22 AS server

RUN apk add --no-cache ca-certificates
COPY --from=build /out/minio /usr/local/bin/minio
# The existing Compose health check runs `mc ready local` inside the server.
COPY --from=build /out/mc /usr/local/bin/mc
EXPOSE 9000 9001
ENTRYPOINT ["minio"]


FROM alpine:3.22 AS client

RUN apk add --no-cache ca-certificates
COPY --from=build /out/mc /usr/local/bin/mc
ENTRYPOINT ["mc"]
