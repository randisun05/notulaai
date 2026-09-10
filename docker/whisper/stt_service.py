"""
Local Speech-to-Text sidecar for AI Notula App.

Wraps openai-whisper behind a tiny HTTP API that
App\\Services\\AI\\Providers\\WhisperLocalTranscriptionProvider calls.

Endpoints
---------
GET  /health      -> {"status": "ok", "model": "<name>"} once the model is loaded
POST /transcribe  -> multipart form: file=<audio>, language=<optional>
                     returns {"text": ..., "segments": [...], "language": ...}

Config (env)
------------
WHISPER_MODEL      whisper model name         (default: base)
WHISPER_DEVICE     "cpu" | "cuda"             (default: cpu)
WHISPER_LANGUAGE   default language           (default: Indonesian)
STT_MAX_UPLOAD_MB  reject bodies larger than  (default: 200)
STT_PORT           listen port               (default: 5055)
"""

import os
import tempfile
import time

from flask import Flask, jsonify, request
from waitress import serve

import whisper

MODEL_NAME = os.getenv("WHISPER_MODEL", "base")
DEVICE = os.getenv("WHISPER_DEVICE", "cpu")
DEFAULT_LANGUAGE = os.getenv("WHISPER_LANGUAGE", "Indonesian")
MAX_UPLOAD_MB = int(os.getenv("STT_MAX_UPLOAD_MB", "200"))
PORT = int(os.getenv("STT_PORT", "5055"))

app = Flask(__name__)
app.config["MAX_CONTENT_LENGTH"] = MAX_UPLOAD_MB * 1024 * 1024

print(f"Loading Whisper model '{MODEL_NAME}' on {DEVICE}...", flush=True)
_model = whisper.load_model(MODEL_NAME, device=DEVICE)
print("Whisper model ready.", flush=True)


@app.get("/health")
def health():
    return jsonify({"status": "ok", "model": MODEL_NAME, "device": DEVICE})


@app.post("/transcribe")
def transcribe():
    if "file" not in request.files:
        return jsonify({"error": "No file uploaded (expected multipart field 'file')."}), 400

    upload = request.files["file"]
    language = request.form.get("language") or DEFAULT_LANGUAGE

    # Write to a real temp file: whisper/ffmpeg read from a path, and on some
    # platforms an unclosed handle stays locked.
    suffix = os.path.splitext(upload.filename or "")[1] or ".tmp"
    fd, tmp_path = tempfile.mkstemp(suffix=suffix)
    os.close(fd)
    upload.save(tmp_path)

    print(f"Transcribing {upload.filename} ({tmp_path}), language={language}", flush=True)
    started = time.time()
    try:
        result = _model.transcribe(tmp_path, language=language)
    except Exception as exc:  # noqa: BLE001 - surface any decode/model error to the caller
        print(f"Transcription failed: {exc}", flush=True)
        return jsonify({"error": str(exc)}), 500
    finally:
        _safe_unlink(tmp_path)

    print(f"Done in {time.time() - started:.1f}s", flush=True)
    return jsonify(
        {
            "text": result.get("text", ""),
            "segments": result.get("segments", []),
            "language": result.get("language", language),
        }
    )


def _safe_unlink(path: str) -> None:
    for delay in (0, 1, 2):
        time.sleep(delay)
        try:
            os.remove(path)
            return
        except FileNotFoundError:
            return
        except OSError:
            continue
    print(f"Could not delete temp file: {path}", flush=True)


if __name__ == "__main__":
    # waitress: a real WSGI server. One thread is enough — the model is a single
    # in-memory instance and transcription is CPU-bound and serialized anyway.
    serve(app, host="0.0.0.0", port=PORT, threads=1, channel_timeout=1800)
