FROM python:3.12-slim

ENV PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1 \
    HOME=/tmp/nexiam-tabfm \
    XDG_CACHE_HOME=/tmp/nexiam-tabfm/.cache \
    XDG_CONFIG_HOME=/tmp/nexiam-tabfm/.config \
    SKB_DATA_DIRECTORY=/tmp/nexiam-tabfm/skrub_data \
    MPLCONFIGDIR=/tmp/nexiam-tabfm/.config/matplotlib \
    TABPFN_MODEL_CACHE_DIR=/tmp/nexiam-tabfm/.cache/tabpfn \
    TRANSFORMERS_OFFLINE=1 \
    HF_HUB_OFFLINE=1 \
    TABPFN_DISABLE_TELEMETRY=1 \
    TABPFN_DEVICE=cpu \
    TABPFN_CHECKPOINT_PATH=/app/model/tabpfn-v3-classifier-v3_20260417_binary.ckpt
WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt ./
RUN pip install --no-cache-dir --upgrade pip \
    && pip install --no-cache-dir -r requirements.txt

RUN useradd --system --uid 10002 --no-create-home --shell /usr/sbin/nologin nexiam-tabfm \
    && install -d -m 0755 -o 10002 -g 10002 /app/model \
    && chown -R 10002:10002 /app

COPY --chown=10002:10002 worker.py prove_checkpoint.py synthetic_model_probe.json ./

USER 10002:10002

EXPOSE 8101

HEALTHCHECK --interval=10s --timeout=30s --start-period=120s --retries=5 CMD python -c 'import json,os,urllib.request; r=urllib.request.urlopen("http://127.0.0.1:8101/health",timeout=25); d=json.load(r); assert r.status==200 and d.get("ready") is True and d.get("backend")=="tabpfn_pytorch"; p=d["provenance"]; assert p["package"]=="tabpfn" and p["package_version"]=="9.1.0" and p["checkpoint_identity"]==os.environ["TABPFN_CHECKPOINT_ID"] and p["checkpoint_sha256"]==os.environ["TABPFN_CHECKPOINT_SHA256"].lower()'

CMD ["python", "worker.py"]
