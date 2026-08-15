FROM python:3.12-slim@sha256:229a2c5bfa27522db7815ea81f9bed70af17ccb9de9fc7ad142b1877b5830d36

ARG OMRCHECKER_COMMIT=de3f6982822895491be760325fd43e65e90fa293
ARG OMRCHECKER_ARCHIVE_SHA256=d1a988ee363784d5a51e2f12f3e10bd24c11ba73622a43b4dfbbdd88a197eac0
ARG OMRCHECKER_LICENSE_SHA256=3d72ab01aefdb28fcf2ec73f6a0564cd855bd880aa3a55ea37efa8c49d4fe94d
ARG NEXIAM_REQUIREMENTS_LOCK_SHA256=6f947e03bc841daf10c26698ab7c921114acf2680e33999fcc411cc86e0cb7c2
ARG OWNED_PATCH_SHA256=dacd18ef49de349699ca9dc5346106d6fc06adb0622dcb2ce8060f8f4442be71

ENV MPLBACKEND=Agg \
    PYTHONPATH=/opt/omrchecker \
    OMRCHECKER_COMMIT=${OMRCHECKER_COMMIT} \
    NEXIAM_PATCH_IDENTITY=nexiam-headless-overlay-v1

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl patch \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.lock ./requirements.lock
RUN echo "${NEXIAM_REQUIREMENTS_LOCK_SHA256}  requirements.lock" | sha256sum -c - \
    && python -m pip install --no-cache-dir --require-hashes --no-compile -r requirements.lock

RUN curl -fsSL --retry 3 --retry-all-errors \
        "https://github.com/Udayraj123/OMRChecker/archive/${OMRCHECKER_COMMIT}.tar.gz" \
        -o /tmp/omrchecker.tar.gz \
    && echo "${OMRCHECKER_ARCHIVE_SHA256}  /tmp/omrchecker.tar.gz" | sha256sum -c - \
    && mkdir -p /opt/omrchecker \
    && tar -xzf /tmp/omrchecker.tar.gz --strip-components=1 -C /opt/omrchecker \
    && echo "${OMRCHECKER_LICENSE_SHA256}  /opt/omrchecker/LICENSE" | sha256sum -c - \
    && rm /tmp/omrchecker.tar.gz

COPY patches/0001-headless-overlay.patch /tmp/0001-headless-overlay.patch
RUN echo "${OWNED_PATCH_SHA256}  /tmp/0001-headless-overlay.patch" | sha256sum -c - \
    && patch --batch --forward --fuzz=0 -d /opt/omrchecker -p1 < /tmp/0001-headless-overlay.patch \
    && rm /tmp/0001-headless-overlay.patch

RUN useradd --system --uid 10001 --no-create-home --shell /usr/sbin/nologin nexiam-omr

COPY --chown=10001:10001 server.py activate.py detect.py generate_fixture.py ./

USER 10001:10001

EXPOSE 8100

CMD ["python", "server.py"]
