# PMMS Student Version

مشروع بسيط لإدارة الخدمات والتسويق باستخدام PHP و MySQL.

## التشغيل

1. أنشئ قاعدة البيانات:
   - نفذ ملف `database/schema.sql`
   - ثم نفذ ملف `database/seed.sql`
2. شغل السيرفر المحلي على مجلد `public`:
   - `php -S localhost:8000 -t public`
3. افتح:
   - `http://localhost:8000`

## التشغيل عبر Docker

1. شغّل الحاويات:
   - `docker compose up --build -d`
2. افتح المشروع:
   - `http://localhost:8000`
3. لإيقاف الحاويات:
   - `docker compose down`
4. لإيقافها مع حذف بيانات قاعدة البيانات:
   - `docker compose down -v`

## حسابات تجريبية

- Admin: `admin@pmms.local`
- Client: `client@pmms.local`
- Provider: `provider@pmms.local`
- Password: `12345678`

## متغيرات قاعدة البيانات

يمكن تخصيص الاتصال عبر:

- `PMMS_DB_HOST`
- `PMMS_DB_PORT`
- `PMMS_DB_USER`
- `PMMS_DB_PASS`
- `PMMS_DB_NAME`
