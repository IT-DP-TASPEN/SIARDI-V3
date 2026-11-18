#!/usr/bin/env bash
# Mirror archive files to the NAS. Cron example (daily 02:00):
#   0 2 * * * /path/to/SIARDI-V2/scripts/backup-archives.sh >> /var/log/siardi-backup.log 2>&1
set -euo pipefail

APP="$(cd "$(dirname "$0")/.." && pwd)"
# cron doesn't load .env, so read ARCHIVE_STORAGE_ROOT from it (same fallback as config/filesystems.php)
: "${ARCHIVE_STORAGE_ROOT:=$(grep -s '^ARCHIVE_STORAGE_ROOT=' "$APP/.env" | cut -d= -f2- | tr -d '"')}"
SRC="${ARCHIVE_STORAGE_ROOT:-$APP/storage/app/private/archives}/"
MOUNT=/mnt/siardi-backup
DEST="$MOUNT/archives/"

# NAS not mounted = rsync would silently fill the local disk instead.
mountpoint -q "$MOUNT" || { echo "$(date -Is) $MOUNT not mounted, abort"; exit 1; }
[ -d "$SRC" ] || { echo "$(date -Is) $SRC missing, abort"; exit 1; }

# flock: skip if previous run still going. No --delete: files removed in the app stay in backup.
# CIFS can't keep owner/perms, so only times are preserved.
flock -n /tmp/siardi-backup-archives.lock \
  rsync -rt --partial "$SRC" "$DEST" && echo "$(date -Is) backup ok"
