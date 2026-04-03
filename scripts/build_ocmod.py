#!/usr/bin/env python3
from __future__ import annotations

import json
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile


def main() -> None:
    repo_root = Path(__file__).resolve().parents[1]
    extension_root = repo_root / "upload" / "extension" / "nopayn"
    install_manifest = extension_root / "install.json"

    metadata = json.loads(install_manifest.read_text(encoding="utf-8"))
    version = metadata["version"]

    dist_dir = repo_root / "dist"
    dist_dir.mkdir(exist_ok=True)

    asset_path = dist_dir / f"nopayn-opencart-v{version}.ocmod.zip"

    if asset_path.exists():
        asset_path.unlink()

    with ZipFile(asset_path, "w", compression=ZIP_DEFLATED) as archive:
        for path in sorted(extension_root.rglob("*")):
            if path.is_file():
                archive.write(path, path.relative_to(extension_root).as_posix())

    with ZipFile(asset_path) as archive:
        names = archive.namelist()

    if "install.json" not in names:
        raise SystemExit("install.json must be at the root of the .ocmod.zip archive")

    if any(name.startswith("upload/") for name in names):
        raise SystemExit("The .ocmod.zip archive must contain admin/catalog files at the root, not under upload/")

    print(asset_path)


if __name__ == "__main__":
    main()
