#!/bin/bash
# ============================================================================
#  Deploy script — NeetHub (domain: neethub.kangmuslim.com)
#  Mengikuti pola deploy project "kangmuslim" / "galajuara".
#
#  Cara pakai di hosting cPanel:
#    1. Pastikan repo sudah di-clone ke $PROJECT (folder subdomain yang
#       sudah otomatis dibuat cPanel di dalam public_html — subdomain di
#       akun ini tidak bisa diarahkan keluar dari public_html, jadi
#       .htaccess di root project yang mengarahkan ke public/).
#    2. Jalankan manual lewat Terminal cPanel/SSH:
#         bash "$HOME/public_html/neethub.kangmuslim.com/deploy/deploy.sh"
#       ATAU pasang sebagai Cron Job di cPanel.
#
#  Catatan: path memakai $HOME agar otomatis mengikuti akun cPanel
#  (tidak perlu hardcode /home/<username>/).
# ============================================================================

LOGFILE="$HOME/deploy/deploy-neethub.log"
PROJECT="$HOME/public_html/neethub.kangmuslim.com"
GIT="/usr/local/cpanel/3rdparty/lib/path-bin/git"
PHP="/usr/local/bin/php"

mkdir -p "$HOME/deploy"

echo "======================================" >> "$LOGFILE"
echo "Deploy run: $(date)" >> "$LOGFILE"

cd "$PROJECT" || { echo "Folder project tidak ditemukan: $PROJECT" >> "$LOGFILE"; exit 1; }

echo "Pull repository..." >> "$LOGFILE"
$GIT fetch origin               >> "$LOGFILE" 2>&1
$GIT reset --hard origin/main   >> "$LOGFILE" 2>&1
# git clean menghapus file untracked, TAPI .env & public/uploads/logos/*
# di-ignore Git sehingga tetap aman. vendor/ ikut di-commit ke repo
# (hosting ini tidak punya composer), jadi ikut ter-reset ke versi repo,
# bukan file untracked yang bisa kehapus git clean.
$GIT clean -fd                  >> "$LOGFILE" 2>&1

echo "Fix writable permission..." >> "$LOGFILE"
chmod -R 775 writable           >> "$LOGFILE" 2>&1

echo "Run CI4 migration..." >> "$LOGFILE"
$PHP spark migrate --all        >> "$LOGFILE" 2>&1

echo "Clear cache..." >> "$LOGFILE"
$PHP spark cache:clear          >> "$LOGFILE" 2>&1

echo "Deploy finished." >> "$LOGFILE"
echo "" >> "$LOGFILE"
