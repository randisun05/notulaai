# syntax=docker/dockerfile:1

# Local Whisper speech-to-text sidecar. Built from its own context
# (docker/whisper/), independent of the PHP app image.
FROM python:3.11-slim AS whisper

# ffmpeg is required by whisper to decode audio; curl is for the healthcheck.
RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY stt_service.py .

# WHISPER_MODEL is read at runtime; the model file downloads on first boot into
# this dir, which docker-compose mounts as a named volume so it survives rebuilds.
ENV WHISPER_MODEL=base \
    WHISPER_DEVICE=cpu \
    XDG_CACHE_HOME=/cache

EXPOSE 5055

HEALTHCHECK --interval=15s --timeout=5s --start-period=180s --retries=5 \
    CMD curl -fsS http://localhost:5055/health || exit 1

CMD ["python", "stt_service.py"]
