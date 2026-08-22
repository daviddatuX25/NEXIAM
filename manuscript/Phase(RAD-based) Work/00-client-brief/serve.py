#!/usr/bin/env python3
"""Serve the NEXIAM client documentation as a local browser site."""

from __future__ import annotations

import argparse
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path


class QuietHandler(SimpleHTTPRequestHandler):
    """Keep the terminal output readable while serving static documents."""

    def log_message(self, format: str, *args: object) -> None:
        print(f"[{self.log_date_time_string()}] {format % args}")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    # Bind all interfaces so ZeroTier peers can reach the documentation site.
    parser.add_argument("--host", default="0.0.0.0")
    parser.add_argument("--port", type=int, default=8765)
    args = parser.parse_args()

    directory = Path(__file__).resolve().parent
    handler = lambda *handler_args: QuietHandler(*handler_args, directory=str(directory))
    server = ThreadingHTTPServer((args.host, args.port), handler)
    print(f"NEXIAM client docs: http://{args.host}:{args.port}/")
    print(f"Serving: {directory}")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nStopping documentation server.")
    finally:
        server.server_close()


if __name__ == "__main__":
    main()
