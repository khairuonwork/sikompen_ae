from __future__ import annotations

import os
import re
import uuid
from contextlib import asynccontextmanager
from typing import AsyncIterator

import httpx
from fastapi import FastAPI, HTTPException, Request
from fastapi.exceptions import RequestValidationError
from fastapi.responses import HTMLResponse, JSONResponse, StreamingResponse
from pydantic import BaseModel, Field

PERIOD_PATTERN = re.compile(r"^\d{4}/\d{4} (Gasal|Genap)$")
CLASS_PATTERN = re.compile(r"^[1-4][A-Z]{2,8}[1-9][0-9]*$")
NIM_PATTERN = re.compile(r"^\d{6,32}$")


class Settings(BaseModel):
    base_url: str = Field(default_factory=lambda: os.getenv("SIKOMPEN_API_BASE_URL", "http://web").rstrip("/"))
    client_id: str = Field(default_factory=lambda: os.getenv("SIKOMPEN_API_CLIENT_ID", ""))
    bearer_token: str = Field(default_factory=lambda: os.getenv("SIKOMPEN_API_BEARER_TOKEN", ""))

    def is_configured(self) -> bool:
        return self.base_url != "" and self.client_id != "" and self.bearer_token != ""


class SingleStudentRequest(BaseModel):
    nim: str = Field(min_length=6, max_length=32)
    periode: str = Field(min_length=14, max_length=32)


class BulkSyncRequest(BaseModel):
    type: str
    periode: str = Field(min_length=14, max_length=32)
    kelas: str | None = Field(default=None, max_length=32)
    idempotency_key: str = Field(default_factory=lambda: str(uuid.uuid4()), min_length=1, max_length=128)


@asynccontextmanager
async def lifespan(app: FastAPI) -> AsyncIterator[None]:
    app.state.http = httpx.AsyncClient(timeout=httpx.Timeout(20.0), follow_redirects=False)
    yield
    await app.state.http.aclose()


app = FastAPI(
    title="Sikompen API Mini App",
    version="0.1.0",
    docs_url=None,
    redoc_url=None,
    openapi_url=None,
    lifespan=lifespan,
)


@app.exception_handler(RequestValidationError)
async def validation_error_handler(_: Request, __: RequestValidationError) -> JSONResponse:
    return JSONResponse(
        {
            "status": "error",
            "error": {
                "code": "invalid_request",
                "message": "Data permintaan tidak sesuai format.",
            },
        },
        status_code=422,
    )


@app.get("/health", response_class=JSONResponse)
async def health() -> dict[str, str]:
    settings = Settings()

    return {"status": "ok", "provider_configuration": "ready" if settings.is_configured() else "missing"}


@app.get("/", response_class=HTMLResponse)
async def index() -> HTMLResponse:
    settings = Settings()
    configuration = "Siap" if settings.is_configured() else "Belum diisi"

    return HTMLResponse(PAGE.replace("{{configuration}}", configuration))


@app.post("/tests/mahasiswa")
async def test_student(payload: SingleStudentRequest, request: Request) -> JSONResponse:
    if not NIM_PATTERN.fullmatch(payload.nim) or not PERIOD_PATTERN.fullmatch(payload.periode):
        raise HTTPException(status_code=422, detail="NIM atau periode tidak sesuai format.")

    return await provider_json(
        request,
        "GET",
        f"/api/v1/mahasiswa/{payload.nim}",
        params={"periode": payload.periode},
    )


@app.post("/tests/sync-runs")
async def create_sync_run(payload: BulkSyncRequest, request: Request) -> JSONResponse:
    if payload.type not in {"kelas", "periode"} or not PERIOD_PATTERN.fullmatch(payload.periode):
        raise HTTPException(status_code=422, detail="Jenis selector atau periode tidak sesuai format.")

    if payload.type == "kelas" and (payload.kelas is None or not CLASS_PATTERN.fullmatch(payload.kelas)):
        raise HTTPException(status_code=422, detail="Kode kelas tidak sesuai format.")

    selector: dict[str, str] = {"type": payload.type, "periode": payload.periode}
    if payload.type == "kelas" and payload.kelas is not None:
        selector["kelas"] = payload.kelas

    return await provider_json(
        request,
        "POST",
        "/api/v1/sync-runs",
        json={"selector": selector, "format": "ndjson", "compression": "gzip"},
        headers={"Idempotency-Key": payload.idempotency_key},
    )


@app.get("/tests/sync-runs/{run_id}")
async def get_sync_run(run_id: str, request: Request) -> JSONResponse:
    if not re.fullmatch(r"[0-9A-HJKMNP-TV-Z]{26}", run_id):
        raise HTTPException(status_code=422, detail="ID sync run tidak valid.")

    return await provider_json(request, "GET", f"/api/v1/sync-runs/{run_id}")


@app.get("/tests/sync-runs/{run_id}/download", response_model=None)
async def download_sync_run(run_id: str, request: Request) -> StreamingResponse | JSONResponse:
    if not re.fullmatch(r"[0-9A-HJKMNP-TV-Z]{26}", run_id):
        raise HTTPException(status_code=422, detail="ID sync run tidak valid.")

    settings = configured_settings()
    client: httpx.AsyncClient = request.app.state.http

    try:
        upstream = await client.send(
            client.build_request("GET", f"{settings.base_url}/api/v1/sync-runs/{run_id}/download", headers=provider_headers(settings)),
            stream=True,
        )
    except httpx.HTTPError:
        raise HTTPException(status_code=502, detail="Provider Sikompen tidak dapat dihubungi.") from None

    if upstream.status_code != 200:
        try:
            await upstream.aread()
            body = upstream.json()
        except ValueError:
            body = {"status": "error", "error": {"code": "upstream_error", "message": "Provider mengembalikan respons tidak valid."}}
        await upstream.aclose()
        return JSONResponse(body, status_code=upstream.status_code)

    async def content() -> AsyncIterator[bytes]:
        try:
            async for chunk in upstream.aiter_bytes():
                yield chunk
        finally:
            await upstream.aclose()

    return StreamingResponse(
        content(),
        media_type="application/gzip",
        headers={
            "Content-Disposition": upstream.headers.get("content-disposition", f'attachment; filename="{run_id}.ndjson.gz"'),
            "Cache-Control": "no-store",
            "X-Content-Type-Options": "nosniff",
        },
    )


def configured_settings() -> Settings:
    settings = Settings()
    if not settings.is_configured():
        raise HTTPException(status_code=503, detail="Konfigurasi client API belum lengkap.")

    return settings


def provider_headers(settings: Settings) -> dict[str, str]:
    return {
        "Authorization": f"Bearer {settings.bearer_token}",
        "X-Client-Id": settings.client_id,
        "Accept": "application/json",
    }


async def provider_json(
    request: Request,
    method: str,
    path: str,
    *,
    params: dict[str, str] | None = None,
    json: dict[str, object] | None = None,
    headers: dict[str, str] | None = None,
) -> JSONResponse:
    settings = configured_settings()
    merged_headers = {**provider_headers(settings), **(headers or {})}
    client: httpx.AsyncClient = request.app.state.http

    try:
        response = await client.request(
            method,
            f"{settings.base_url}{path}",
            params=params,
            json=json,
            headers=merged_headers,
        )
    except httpx.HTTPError:
        raise HTTPException(status_code=502, detail="Provider Sikompen tidak dapat dihubungi.") from None

    try:
        body = response.json()
    except ValueError:
        body = {"status": "error", "error": {"code": "invalid_upstream_response", "message": "Provider mengembalikan respons tidak valid."}}

    return JSONResponse(body, status_code=response.status_code, headers={"Cache-Control": "no-store"})


PAGE = """<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sikompen API Mini App</title>
  <style>
    :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #163969; background: #edf3ff; }
    body { margin: 0; min-height: 100vh; background: linear-gradient(135deg, #dbe9ff, #f8fbff 45%, #e9f0fc); }
    main { width: min(920px, calc(100% - 32px)); margin: 48px auto; }
    header, section { background: rgba(255,255,255,.94); border: 1px solid #c9d9f3; border-radius: 20px; box-shadow: 0 16px 40px rgba(35, 72, 125, .10); }
    header { padding: 28px 32px; margin-bottom: 20px; } section { padding: 24px; margin-top: 16px; }
    h1, h2, p { margin-top: 0; } h1 { margin-bottom: 8px; } h2 { font-size: 1.05rem; margin-bottom: 16px; }
    .status { display: inline-block; padding: 5px 10px; border-radius: 999px; background: #e3f8ed; color: #047857; font-weight: 700; font-size: .85rem; }
    .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; } label { display: grid; gap: 6px; font-weight: 650; font-size: .9rem; }
    input, select, button { font: inherit; border-radius: 10px; padding: 10px 12px; } input, select { border: 1px solid #9dbdf0; color: #173d73; background: white; }
    button { border: 0; background: #31588f; color: white; cursor: pointer; font-weight: 700; margin-top: 16px; } button:hover { background: #254878; }
    pre { min-height: 90px; max-height: 400px; overflow: auto; margin: 0; padding: 16px; border-radius: 12px; background: #12243e; color: #d8e9ff; font-size: .85rem; white-space: pre-wrap; word-break: break-word; }
    .hint { color: #657da5; font-size: .9rem; } a { color: #24518d; font-weight: 700; }
    @media (max-width: 640px) { main { margin: 20px auto; } header, section { border-radius: 14px; padding: 20px; } .grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
<main>
  <header><span class="status">Konfigurasi: {{configuration}}</span><h1>Sikompen API Mini App</h1><p class="hint">Panel internal untuk menguji API provider Laravel. Token tidak pernah dikirim ke browser.</p></header>
  <section>
    <h2>Uji mahasiswa tunggal</h2>
    <form id="student-form" class="grid"><label>NIM<input name="nim" pattern="[0-9]{6,32}" required></label><label>Periode<input name="periode" placeholder="2026/2027 Gasal" required></label><button>Ambil data</button></form>
  </section>
  <section>
    <h2>Uji sync kelas/periode</h2>
    <form id="sync-form" class="grid"><label>Jenis<select name="type"><option value="periode">Satu periode</option><option value="kelas">Satu kelas</option></select></label><label>Periode<input name="periode" placeholder="2026/2027 Gasal" required></label><label>Kelas (wajib bila jenis kelas)<input name="kelas" placeholder="1AEA1"></label><label>Idempotency key<input name="idempotency_key" id="idempotency-key" required></label><button>Buat sync run</button></form>
    <p class="hint" id="download"></p>
  </section>
  <section><h2>Hasil respons</h2><pre id="result">Belum ada permintaan.</pre></section>
</main>
<script>
  const result = document.getElementById('result');
  const key = document.getElementById('idempotency-key'); key.value = crypto.randomUUID();
  async function submit(form, url) {
    const payload = Object.fromEntries(new FormData(form)); result.textContent = 'Meminta provider…';
    const response = await fetch(url, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
    const data = await response.json(); result.textContent = JSON.stringify(data, null, 2);
    const runId = data?.data?.sync_run_id; if (runId) document.getElementById('download').innerHTML = '';
    if (runId) { const link = document.createElement('a'); link.href = `/tests/sync-runs/${runId}/download`; link.textContent = 'Unduh artifact saat status ready'; document.getElementById('download').append(link); }
  }
  document.getElementById('student-form').addEventListener('submit', event => { event.preventDefault(); submit(event.currentTarget, '/tests/mahasiswa'); });
  document.getElementById('sync-form').addEventListener('submit', event => { event.preventDefault(); submit(event.currentTarget, '/tests/sync-runs'); });
</script>
</body>
</html>"""
