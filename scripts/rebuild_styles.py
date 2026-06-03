#!/usr/bin/env python3
"""Rebuild public/assets/styles.css from git HEAD + transcript patches + match block."""
import json
import re
import subprocess
from pathlib import Path

REPO = Path(__file__).resolve().parents[1]
TRANSCRIPT = Path(
    r"C:/Users/fedor/.cursor/projects/d-fedor-Documents-CHP2026/agent-transcripts"
    "/dea89287-aa2c-4375-8bb9-261dc07bf7bb/dea89287-aa2c-4375-8bb9-261dc07bf7bb.jsonl"
)
MATCH_ONLY = REPO / "public/assets/styles.css.match-only"
OUT = REPO / "public/assets/styles.css"


def git_head_css() -> str:
    return subprocess.check_output(
        ["git", "-C", str(REPO), "show", "HEAD:public/assets/styles.css"],
        text=True,
    )


def apply_patch_hunks(base: str, patch: str) -> tuple[str, int, int]:
    applied = failed = 0
    for hunk in re.split(r"\n(?=\*\*\* Update File:)", patch):
        if "styles.css" not in hunk:
            continue
        lines = hunk.splitlines()
        i = 0
        while i < len(lines):
            if not lines[i].startswith("@@"):
                i += 1
                continue
            i += 1
            old_lines: list[str] = []
            new_lines: list[str] = []
            while i < len(lines) and not lines[i].startswith("@@") and not lines[i].startswith("***"):
                line = lines[i]
                if line.startswith("-") and not line.startswith("---"):
                    old_lines.append(line[1:])
                elif line.startswith("+") and not line.startswith("+++"):
                    new_lines.append(line[1:])
                elif line.startswith(" "):
                    old_lines.append(line[1:])
                    new_lines.append(line[1:])
                i += 1
            old = "\n".join(old_lines)
            new = "\n".join(new_lines)
            if old and old in base:
                base = base.replace(old, new, 1)
                applied += 1
            elif old:
                failed += 1
    return base, applied, failed


def main() -> None:
    base = git_head_css()
    applied = failed = 0

    for line in TRANSCRIPT.read_text(encoding="utf-8").splitlines():
        try:
            obj = json.loads(line)
        except json.JSONDecodeError:
            continue
        for part in obj.get("message", {}).get("content", []):
            if part.get("type") != "tool_use":
                continue
            name = part.get("name")
            inp = part.get("input", {})
            if isinstance(inp, str):
                if name == "ApplyPatch" and "styles.css" in inp:
                    base, a, f = apply_patch_hunks(base, inp)
                    applied += a
                    failed += f
                continue
            path = inp.get("path", "").replace("\\", "/").lower()
            if not path.endswith("public/assets/styles.css"):
                continue
            if name == "StrReplace":
                old = inp.get("old_string", "")
                new = inp.get("new_string", "")
                if old and old in base:
                    base = base.replace(old, new, 1)
                    applied += 1
                elif old:
                    failed += 1
            elif name == "ApplyPatch":
                patch = inp if isinstance(inp, str) else ""
                if isinstance(inp, dict):
                    for v in inp.values():
                        if isinstance(v, str) and "*** Begin Patch" in v:
                            patch = v
                            break
                if patch:
                    base, a, f = apply_patch_hunks(base, patch)
                    applied += a
                    failed += f

    if MATCH_ONLY.exists() and "match-dashboard-card" not in base:
        base = base.rstrip() + "\n\n" + MATCH_ONLY.read_text(encoding="utf-8").lstrip() + "\n"

    if not base.lstrip().startswith(":root"):
        raise SystemExit("Rebuild failed: missing :root")

    OUT.write_text(base, encoding="utf-8")
    print(f"OK: applied={applied} failed={failed} lines={len(base.splitlines())}")


if __name__ == "__main__":
    main()
