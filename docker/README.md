# รันด้วย Docker (MySQL/MariaDB)

แอพนี้เป็น PHP ล้วน + PDO(MySQL) ใช้ 2 ฐานข้อมูล:
`student_manager` (ผู้ใช้/ตั้งค่า) และ `students_db` (ปีการศึกษา/นักเรียน)

Docker setup นี้รัน **2 container**:
- `web` — PHP 8.2 + Apache (โค้ดแอพ)
- `db` — MariaDB 10.11 (เก็บทั้งสองฐานข้อมูลในเซิร์ฟเวอร์เดียว)

ฐานข้อมูลจะถูก import อัตโนมัติจากไฟล์ `.sql` ในโฟลเดอร์ [`db-init/`](db-init)
**ครั้งแรก** ที่สร้าง container `db`

> ⚠️ ไฟล์ dump ข้อมูลจริง (`db-init/*.sql`) **ไม่ถูก commit ขึ้น git**
> เพราะมีข้อมูลนักเรียน (PII) / password hash / API key
> ใน repo จะมีแค่ไฟล์โครงสร้าง `*.sql.example` (schema เปล่า ไม่มีข้อมูล)

## เริ่มใช้งาน

ต้องเปิด **Docker Desktop** ให้ daemon ทำงานก่อน

**1. เตรียมไฟล์ SQL** — คัดลอก schema ตัวอย่างเป็น `.sql` (ถ้ายังไม่มีข้อมูลจริง):

```bash
cp docker/db-init/10-student_manager.sql.example docker/db-init/10-student_manager.sql
cp docker/db-init/20-students_db.sql.example     docker/db-init/20-students_db.sql
```

หรือถ้ามีข้อมูลจริงใน XAMPP ให้ดึง dump ด้วย `dump-from-xampp.sh` (ดูหัวข้อด้านล่าง)

**2. ตั้งรหัสผ่าน DB** — คัดลอก `.env.example` เป็น `.env` แล้วตั้ง `DB_ROOT_PASSWORD`

**3. รัน** ที่โฟลเดอร์ราก:

```bash
docker compose up -d --build
```

เปิดเบราว์เซอร์: <http://localhost:8080>

- ผู้ใช้/รหัสผ่านเป็นชุดเดียวกับที่มีใน XAMPP เดิม (ย้ายข้อมูลมาแล้ว)
- ถ้ายังไม่มีผู้ใช้เลย ระบบจะพาไปหน้า `setup.php` ให้สร้าง admin คนแรก

พอร์ตที่เปิด:
- เว็บ: `8080` -> คอนเทนเนอร์ 80
- ฐานข้อมูล: `3307` บน host -> คอนเทนเนอร์ 3306 (ตั้ง 3307 เพื่อไม่ชนกับ XAMPP 3306)

## คำสั่งที่ใช้บ่อย

```bash
docker compose logs -f web      # ดู log เว็บ
docker compose logs -f db       # ดู log ฐานข้อมูล
docker compose down             # หยุด (ข้อมูลใน volume ยังอยู่)
docker compose down -v          # หยุด + ลบข้อมูลฐานข้อมูล (เริ่ม import ใหม่)
```

## อัปเดตข้อมูลจาก XAMPP ใหม่

ข้อมูลจะถูก import **เฉพาะตอนสร้าง volume ครั้งแรก** เท่านั้น
ถ้าแก้ข้อมูลใน XAMPP แล้วอยากให้ Docker เห็นด้วย:

```bash
DB_PASS=yourpassword bash docker/dump-from-xampp.sh   # ดึง dump ใหม่
docker compose down -v                             # ล้าง volume เดิม
docker compose up -d --build                       # สร้างใหม่ + import
```

## หมายเหตุ

- ค่าเชื่อมต่อฐานข้อมูลใน `docker-compose.yml` (env `DB_HOST=db` ฯลฯ)
  จะ **override** ไฟล์ `.env` เดิมโดยอัตโนมัติ (ผ่าน `getenv()` ใน `config.php`)
  ดังนั้น `.env` ที่ชี้ไป XAMPP ยังใช้รันแบบ local ปกติได้ ไม่ต้องแก้
- รหัสผ่าน root ของฐานข้อมูลตั้งได้ด้วย env `DB_ROOT_PASSWORD` ใน `.env` (default `changeme`)
