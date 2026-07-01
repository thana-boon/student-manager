#!/usr/bin/env bash
# ดึงข้อมูลล่าสุดจาก XAMPP MySQL มาไว้ใน docker/db-init/
# วิธีใช้ (Git Bash):  DB_PASS=yourpassword bash docker/dump-from-xampp.sh
set -euo pipefail

MYSQLDUMP="${MYSQLDUMP:-/c/xampp/mysql/bin/mysqldump.exe}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
OUT_DIR="$(cd "$(dirname "$0")" && pwd)/db-init"

mkdir -p "$OUT_DIR"

dump() {
  local db="$1" file="$2"
  echo ">> dumping $db -> $file"
  "$MYSQLDUMP" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} \
    --default-character-set=utf8mb4 --single-transaction --add-drop-database \
    --databases "$db" > "$OUT_DIR/$file"
}

dump student_manager 10-student_manager.sql
dump students_db      20-students_db.sql

echo "เสร็จแล้ว: $OUT_DIR"
echo "หมายเหตุ: ถ้า container db รันอยู่ก่อนแล้ว ต้องล้าง volume เพื่อ import ใหม่ ->"
echo "  docker compose down -v && docker compose up -d --build"
