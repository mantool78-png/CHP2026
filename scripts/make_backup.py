#!/usr/bin/env python3
"""Create a zip backup of the local codebase, excluding temporary and version control files."""

import os
import zipfile
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BACKUPS_DIR = ROOT / "backups"

# Directories and files to exclude from the backup
EXCLUDE_DIRS = {
    ".git",
    ".idea",
    ".vscode",
    "__pycache__",
    "node_modules",
    "backups",
}

EXCLUDE_FILES = {
    ".beget-ftp.env",  # Contains sensitive FTP credentials
}


def make_backup():
    BACKUPS_DIR.mkdir(exist_ok=True)
    
    timestamp = datetime.now().strftime("%Y%m%d-%H%M%S")
    zip_filename = BACKUPS_DIR / f"backup-{timestamp}.zip"
    
    print(f"Starting backup of {ROOT.name}...")
    print(f"Target: {zip_filename}")
    
    count = 0
    with zipfile.ZipFile(zip_filename, "w", zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk(ROOT):
            # Modify dirs in-place to skip excluded directories
            dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS and not d.startswith("deploy-")]
            
            for file in files:
                if file in EXCLUDE_FILES or file.endswith(".zip") or file.endswith(".pyc"):
                    continue
                
                file_path = Path(root) / file
                # Calculate relative path for the zip archive
                rel_path = file_path.relative_to(ROOT)
                
                zipf.write(file_path, rel_path)
                count += 1
                
    print(f"Backup completed successfully!")
    print(f"Total files archived: {count}")
    print(f"File size: {zip_filename.stat().st_size / (1024 * 1024):.2f} MB")
    print(f"Location: {zip_filename}")


if __name__ == "__main__":
    make_backup()
