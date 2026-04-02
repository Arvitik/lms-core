import json
import re
from pathlib import Path

import requests


BASE_URL = "http://localhost:8080"
ADMIN_EMAIL = "ssss4-281094@yandex.ru"
ADMIN_PASSWORD = "Admin123!2026"

SKIP_PATTERNS = [
    "logout",
    "delete",
    "remove",
    "drop",
    "finish",
    "archive",
    "bulk_action",
    "download",
    "gen-",
    "mark_present",
    "set_students_level",
    "check/ifExists",
]


def should_skip(uri: str) -> bool:
    if "{" in uri:
        return True
    return any(p in uri for p in SKIP_PATTERNS)


def extract_csrf(html: str) -> str:
    patterns = [
        r'name="_token"\s+value="([^"]+)"',
        r'meta name="csrf-token"\s+content="([^"]+)"',
        r'meta name="csrf_token"\s+content="([^"]+)"',
    ]
    for p in patterns:
        m = re.search(p, html, flags=re.IGNORECASE)
        if m:
            return m.group(1)
    return ""


def login(session: requests.Session) -> None:
    home = session.get(f"{BASE_URL}/home", timeout=20)
    token = extract_csrf(home.text)
    payload = {"email": ADMIN_EMAIL, "password": ADMIN_PASSWORD}
    if token:
        payload["_token"] = token
    session.post(
        f"{BASE_URL}/auth/login",
        data=payload,
        allow_redirects=True,
        timeout=20,
    )


def hit(session: requests.Session, uri: str) -> dict:
    url = f"{BASE_URL}/{uri.lstrip('/')}" if uri != "/" else BASE_URL
    try:
        r = session.get(url, allow_redirects=True, timeout=20)
        body = r.text[:4000]
        has_error_text = (
            "Whoops, looks like something went wrong." in body
            or "FatalThrowableError" in body
            or "ErrorException" in body
        )
        is_fail = r.status_code >= 500 or has_error_text
        return {
            "uri": uri,
            "status": r.status_code,
            "final_url": r.url,
            "failed": is_fail,
        }
    except Exception as e:
        return {"uri": uri, "status": 0, "final_url": "", "failed": True, "error": str(e)}


def run():
    routes_path = Path(__file__).with_name("routes.json")
    routes = json.loads(routes_path.read_text(encoding="utf-8"))
    get_routes = [r["uri"] for r in routes if "GET" in r["methods"]]
    targets = sorted(set([u for u in get_routes if not should_skip(u)]))

    guest = requests.Session()
    auth = requests.Session()
    login(auth)

    guest_results = [hit(guest, uri) for uri in targets]
    auth_results = [hit(auth, uri) for uri in targets]

    guest_fail = [r for r in guest_results if r["failed"]]
    auth_fail = [r for r in auth_results if r["failed"]]

    report = {
        "total_targets": len(targets),
        "guest_failed": guest_fail,
        "auth_failed": auth_fail,
    }
    out = Path(__file__).with_name("smoke_report.json")
    out.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"Targets: {len(targets)}")
    print(f"Guest failures: {len(guest_fail)}")
    print(f"Auth failures: {len(auth_fail)}")
    print(f"Report: {out}")


if __name__ == "__main__":
    run()
