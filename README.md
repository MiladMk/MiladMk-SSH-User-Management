<p align="center">
<img width="160" height="160" alt="MiladMk Panel" src="https://raw.githubusercontent.com/MiladMk/MiladMk-SSH-User-Management/main/xlogo.png">
</p>
<h1 align="center">MiladMk Panel</h1>
<h6 align="center">SSH + Sing-box User Management Panel</h6>

<p align="center">
<img alt="GitHub release (latest by date)" src="https://img.shields.io/github/v/release/MiladMk/MiladMk-SSH-User-Management">
<img alt="Platform" src="https://img.shields.io/badge/platform-Ubuntu%2024%20%7C%2026-blue">
</p>

---

## معرفی
**MiladMk Panel** یک وب‌اپلیکیشن سبک برای مدیریت اکانت‌های SSH و Sing-box است. با آن می‌توانید کاربران را بسازید، محدودیت حجم و تاریخ انقضا اعمال کنید، مصرف را ببینید و لینک اتصال ارائه دهید.

## پروتکل‌ها
✅ `SSH-DIRECT` ✅ `SSH-TLS` ✅ `SSH-DROPBEAR` ✅ `SSH-DROPBEAR-TLS` ✅ `SSH-WEBSOCKET` ✅ `SSH-WEBSOCKET-TLS`
✅ `VMess ws` ✅ `VLess Reality` ✅ `Hysteria2` ✅ `Tuic` ✅ `Shadowsocks`

پورت‌های ۴۴۳، ۸۰ و ۸۸۸۰ به‌صورت پیش‌فرض برای وب‌سرور رزرو شده‌اند.

## امکانات
🟢 ساخت کاربر نامحدود
🟢 محدودیت حجم مصرفی و تاریخ انقضا
🟢 محاسبهٔ تاریخ انقضا از اولین اتصال
🟢 محدودیت تعداد اتصال همزمان
🟢 مشاهدهٔ کاربران آنلاین
🟢 بکاپ‌گیری و ریستور
🟢 ربات تلگرام
🟢 تنظیم پورت اختصاصی برای ورود پنل
🟢 فیک‌آدرس (دور زدن فیلترینگ)
🟢 بلک‌لیست IP
🟢 اتصال API
🟢 چرخش IP با Cloudflare (مستقل، روی سرور خودت)
🟢 هاست سفارشی برای لینک‌های اتصال
🟢 NPV Tunnel
🟢 هستهٔ Sing-box

## نصب
سیستم‌عامل پیشنهادی: **Ubuntu 24.04 یا 26.04** (Debian هم پشتیبانی می‌شود).

```
bash <(curl -Ls https://raw.githubusercontent.com/MiladMk/MiladMk-SSH-User-Management/main/install.sh --ipv4)
```

## مدیریت پنل
```
miladmk
```

## بروزرسانی
```
miladmk
```
هنگام بروزرسانی، تنظیمات و دیتابیس حفظ می‌شوند.

## چرخش IP (Cloudflare)
در «تنظیمات ← IP Rotate» توکن API و Zone ID کلودفلر خودت، لیست IP، نام رکورد و بازهٔ زمانی را وارد کن. حالت ترتیبی یا تصادفی قابل انتخاب است. کاملاً روی سرور خودت اجرا می‌شود.

## لایسنس
این پروژه تحت [Unlicense](./LICENSE) منتشر شده و آزاد است.
