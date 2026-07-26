# stt_service.py
from flask import Flask, request, jsonify
import whisper
import tempfile
import os

app = Flask(__name__)

# Load model sekali saja (pilih base/small/medium/large)
print("🧠 Loading Whisper model... (base)")
model = whisper.load_model("base")

@app.route('/transcribe', methods=['POST'])
def transcribe():
    if 'file' not in request.files:
        return jsonify({"error": "No file uploaded"}), 400

    file = request.files['file']
    lang = request.form.get('language', 'Indonesian')

    # Simpan manual ke temp file agar tidak auto-lock di Windows
    tmp_path = tempfile.NamedTemporaryFile(delete=False, suffix=".mp3").name
    file.save(tmp_path)

    print(f"🎙️ Processing file: {file.filename} -> {tmp_path}")

    try:
        result = model.transcribe(tmp_path, language=lang)
    except Exception as e:
        return jsonify({"error": str(e)}), 500
    finally:
        try:
            os.remove(tmp_path)
        except PermissionError:
            # Kalau masih dikunci Windows, tunda penghapusan
            import time
            time.sleep(1)
            try:
                os.remove(tmp_path)
            except Exception as e:
                print(f"⚠️ Gagal menghapus temp file: {e}")

    return jsonify({
        "text": result.get("text", ""),
        "segments": result.get("segments", []),
        "language": result.get("language", lang)
    })

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5055)
