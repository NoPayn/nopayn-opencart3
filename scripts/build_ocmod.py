#!/usr/bin/env python3
from __future__ import annotations

import json
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile


def main() -> None:
    repo_root = Path(__file__).resolve().parents[1]
    metadata_path = repo_root / "module.json"
    upload_root = repo_root / "upload"

    metadata = json.loads(metadata_path.read_text(encoding="utf-8"))
    version = metadata["version"]

    dist_dir = repo_root / "dist"
    dist_dir.mkdir(exist_ok=True)

    asset_path = dist_dir / f"nopayn-opencart3-v{version}.ocmod.zip"

    if asset_path.exists():
        asset_path.unlink()

    with ZipFile(asset_path, "w", compression=ZIP_DEFLATED) as archive:
        for path in sorted(upload_root.rglob("*")):
            if path.is_file():
                archive.write(path, path.relative_to(repo_root).as_posix())

    with ZipFile(asset_path) as archive:
        names = archive.namelist()

    required_entries = {
        "upload/admin/controller/extension/payment/nopayn.php",
        "upload/admin/model/extension/payment/nopayn.php",
        "upload/catalog/controller/extension/payment/nopayn.php",
        "upload/catalog/model/extension/payment/nopayn.php",
    }

    missing_entries = sorted(required_entries.difference(names))

    if missing_entries:
        raise SystemExit("Missing required archive entries: " + ", ".join(missing_entries))

    if any(name.startswith("admin/") or name.startswith("catalog/") for name in names):
        raise SystemExit("OpenCart 3 installer archives must keep files under the upload/ directory")

    print(asset_path)


if __name__ == "__main__":
    main()
