FROM python:3.13-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1

WORKDIR /app

RUN groupadd --gid 10001 api \
    && useradd --uid 10001 --gid api --create-home --shell /usr/sbin/nologin api

COPY requirements.txt ./
RUN pip install --no-cache-dir --upgrade -r requirements.txt

COPY app ./app

RUN chown -R api:api /app

USER api

EXPOSE 8000

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000", "--no-access-log"]
