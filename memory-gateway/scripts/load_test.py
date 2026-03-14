#!/usr/bin/env python3
"""Simple load test for Memory Gateway — Phase 7 benchmark helper."""
import os
import sys
import time

try:
    import httpx
except ImportError:
    print("Install httpx: pip install httpx")
    sys.exit(1)

GATEWAY_URL = os.getenv("GATEWAY_URL", "http://127.0.0.1:8010")
TOKEN = os.getenv("TRUAI_TOKEN", "")
N = int(os.getenv("LOAD_TEST_N", "50"))


def main():
    if not TOKEN:
        print("Set TRUAI_TOKEN environment variable")
        sys.exit(1)

    ok = 0
    err = 0
    latencies = []
    start = time.perf_counter()

    for i in range(N):
        t0 = time.perf_counter()
        try:
            r = httpx.post(
                f"{GATEWAY_URL}/memory/query",
                headers={"Authorization": f"Bearer {TOKEN}", "Content-Type": "application/json"},
                json={"collection": "truai_episodes", "text": "test", "top_k": 5},
                timeout=10,
            )
            if r.status_code == 200:
                ok += 1
            else:
                err += 1
            trace = r.headers.get("X-Trace-ID", "")
            if i < 5:
                print(f"  {i}: {r.status_code} trace_id={trace[:8]}...")
        except Exception as e:
            err += 1
            if i < 5:
                print(f"  {i}: error {e}")
        latencies.append((time.perf_counter() - t0) * 1000)

    elapsed = time.perf_counter() - start
    print(f"\nDone: {ok} ok, {err} err, {elapsed:.2f}s total")
    if latencies:
        latencies.sort()
        p50 = latencies[len(latencies) // 2]
        p95 = latencies[int(len(latencies) * 0.95)] if len(latencies) > 1 else latencies[-1]
        print(f"Latency: p50={p50:.0f}ms p95={p95:.0f}ms")


if __name__ == "__main__":
    main()
