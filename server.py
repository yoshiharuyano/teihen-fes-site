#!/usr/bin/env python3
"""
サマテイ 2026 ローカルプレビューサーバー
.htaccess の301リダイレクトを再現します。

使い方:
  cd ~/summatei-site
  python3 server.py

ブラウザで http://localhost:8080 を開く
"""

import http.server
import os
import sys

PORT = 8080
SITE_DIR = os.path.dirname(os.path.abspath(__file__))

REDIRECTS_TO_VOL1 = [
    "/artists.html",
    "/timetable.html",
    "/archive.html",
    "/cm.html",
    "/goods.html",
    "/vote.html",
    "/interview.html",
    "/interview-b.html",
    "/teihendon.html",
    "/bottomdon-fc.html",
    "/shindan.html",
    "/gotochi-map.html",
    "/logo-guideline.html",
    "/utsu_lofi_hell_trip.html",
    "/testartists.html",
]

REDIRECTS_CUSTOM = {
    "/summatei.html": "/",
}


class SummateiHandler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=SITE_DIR, **kwargs)

    def do_GET(self):
        path = self.path.split("?")[0].split("#")[0]

        if path in REDIRECTS_CUSTOM:
            self.send_response(301)
            self.send_header("Location", REDIRECTS_CUSTOM[path])
            self.end_headers()
            print(f"  301 {path} -> {REDIRECTS_CUSTOM[path]}")
            return

        if path in REDIRECTS_TO_VOL1:
            dest = f"/vol1{path}"
            self.send_response(301)
            self.send_header("Location", dest)
            self.end_headers()
            print(f"  301 {path} -> {dest}")
            return

        if path.endswith("/") and path != "/":
            index = os.path.join(SITE_DIR, path.lstrip("/"), "index.html")
            if os.path.isfile(index):
                self.path = path + "index.html"

        super().do_GET()

    def log_message(self, format, *args):
        status = args[1] if len(args) > 1 else ""
        path = args[0].split(" ")[1] if args else ""
        if "301" not in str(status):
            print(f"  {status} {path}")


def main():
    os.chdir(SITE_DIR)

    checks = {
        "index.html": os.path.isfile(os.path.join(SITE_DIR, "index.html")),
        "vol1/": os.path.isdir(os.path.join(SITE_DIR, "vol1")),
        "vol2/img/": os.path.isdir(os.path.join(SITE_DIR, "vol2", "img")),
        "llms.txt": os.path.isfile(os.path.join(SITE_DIR, "llms.txt")),
    }

    print()
    print("  ╔══════════════════════════════════════════╗")
    print("  ║  サマテイ 2026 ローカルプレビューサーバー  ║")
    print("  ╚══════════════════════════════════════════╝")
    print()
    print("  File check:")
    all_ok = True
    for name, ok in checks.items():
        mark = "OK" if ok else "MISSING"
        if not ok:
            all_ok = False
        print(f"    {'✓' if ok else '✗'} {name:20s} {mark}")

    if not all_ok:
        print()
        print("  ⚠ Some files missing. Make sure you extracted the ZIP here.")
        print(f"  Current dir: {SITE_DIR}")
        print()

    print()
    print(f"  Server: http://localhost:{PORT}")
    print(f"  Root:   {SITE_DIR}")
    print()
    print("  Routes:")
    print(f"    http://localhost:{PORT}/              → index.html (サマテイ TOP)")
    print(f"    http://localhost:{PORT}/vol1/          → vol1/index.html (第一回)")
    print(f"    http://localhost:{PORT}/vol1/artists.html")
    print(f"    http://localhost:{PORT}/artists.html   → 301 → /vol1/artists.html")
    print(f"    http://localhost:{PORT}/summatei.html  → 301 → /")
    print()
    print("  Ctrl+C to stop")
    print("  " + "─" * 44)

    with http.server.HTTPServer(("", PORT), SummateiHandler) as httpd:
        try:
            httpd.serve_forever()
        except KeyboardInterrupt:
            print("\n  Server stopped.")
            sys.exit(0)


if __name__ == "__main__":
    main()
