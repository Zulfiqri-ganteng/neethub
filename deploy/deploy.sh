#!/bin/bash
# ============================================================================
#  Deploy script — NeetHub (domain: neethub.kangmuslim.com)
#  Mengikuti pola deploy project "kangmuslim" / "galajuara".
#
#  Cara pakai di hosting cPanel:
#    1. Pastikan repo sudah di-clone ke $PROJECT (lihat PANDUAN.md bagian Deploy).
#    2. Jalankan manual lewat Terminal cPanel/SSH:
#         bash "$HOME/neethub/deploy/deploy.sh"
#       ATAU pasang sebagai Cron Job di cPanel.
#
#  Catatan: path memakai $HOME agar otomatis mengikuti akun cPanel
#  (tidak perlu hardcode /home/<username>/).
# ============================================================================

LOGFILE="$HOME/deploy/deploy-neethub.log"
PROJECT="$HOME/neethub"
GIT="/usr/local/cpanel/3rdparty/lib/path-bin/git"
PHP="/usr/local/bin/php"
COMPOSER="composer"

mkdir -p "$HOME/deploy"

echo "======================================" >> "$LOGFILE"
echo "Deploy run: $(date)" >> "$LOGFILE"

cd "$PROJECT" || { echo "Folder project tidak ditemukan: $PROJECT" >> "$LOGFILE"; exit 1; }

echo "Pull repository..." >> "$LOGFILE"
$GIT fetch origin               >> "$LOGFILE" 2>&1
$GIT reset --hard origin/main   >> "$LOGFILE" 2>&1
# git clean menghapus file untracked, TAPI .env, public/uploads/logos/*,
# dan vendor/ (kalau sudah ada) di-ignore Git sehingga tetap aman.
$GIT clean -fd                  >> "$LOGFILE" 2>&1

echo "Install/update dependency..." >> "$LOGFILE"
$COMPOSER install --no-dev --optimize-autoloader >> "$LOGFILE" 2>&1

echo "Run CI4 migration..." >> "$LOGFILE"
$PHP spark migrate --all        >> "$LOGFILE" 2>&1

echo "Clear cache..." >> "$LOGFILE"
$PHP spark cache:clear          >> "$LOGFILE" 2>&1

echo "Fix writable permission..." >> "$LOGFILE"
chmod -R 775 writable           >> "$LOGFILE" 2>&1

echo "Deploy finished." >> "$LOGFILE"
echo "" >> "$LOGFILE"
