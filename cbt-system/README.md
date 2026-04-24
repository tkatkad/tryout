# 📘 CBT SYSTEM - BLUEPRINT V1.1 IMPLEMENTATION

Sistem Ujian Online (Computer Based Test) berbasis **Native PHP** untuk BIMBEL Kementerian Pendidikan Dasar dan Menengah.

---

## 🏗️ Arsitektur

| Layer | Teknologi |
|-------|-----------|
| Backend | Native PHP 8.2 (PDO, Procedural/OOP Hybrid) |
| Frontend Style | Tailwind CSS (CDN) |
| Frontend Logic | Vanilla JS (ES6) |
| Database | MySQL 8 |
| Caching | Redis (optional) |

---

## 📁 Struktur Folder

```
/cbt-system
├── /config
│   ├── database.php      # Koneksi PDO Singleton
│   ├── redis.php         # Redis Cache Handler
│   └── constants.php     # Konstanta & Helper Functions
├── /core
│   ├── Auth.php          # Authentication Handler
│   └── Router.php        # Simple Routing (TODO)
├── /modules
│   ├── /exam
│   │   ├── Controller.php    # Exam logic (TODO)
│   │   ├── Model.php         # Database operations
│   │   └── Randomizer.php    # Stratified Shuffle Algorithm
│   ├── /student
│   │   └── Dashboard.php     # Student dashboard (TODO)
│   └── /admin
│       └── QuestionCRUD.php  # Admin CRUD (TODO)
├── /public
│   ├── index.php         # Entry Point (TODO)
│   └── /assets
│       ├── /css          # Custom CSS (if needed)
│       └── /js
│           └── exam-engine.js  # Exam UI Logic
├── /views
│   ├── /layouts
│   │   ├── header.php
│   │   └── footer.php
│   ├── /admin
│   └── /student
│       ├── login.php
│       └── exam-interface.php
└── /uploads            # Gambar stimulus
```

---

## 🗄️ Database Setup

Jalankan file `database_schema.sql` di MySQL:

```bash
mysql -u root -p cbt_system < database_schema.sql
```

### Tabel Utama:

1. **users** - Data siswa & admin
2. **questions** - Bank soal dengan JSON options
3. **exams** - Paket ujian dengan rasio kesulitan
4. **exam_sessions** - Sesi ujian per siswa
5. **session_items** - Urutan soal yang sudah diacak (PENTING!)
6. **timer_logs** - Audit trail timer

---

## ⚙️ Konfigurasi

### 1. Database (`config/database.php`)

Edit kredensial sesuai server Anda:

```php
private $host = 'localhost';
private $db_name = 'cbt_system';
private $username = 'root';
private $password = '';
```

### 2. Base URL (`config/constants.php`)

```php
define('BASE_URL', '/cbt-system/public');
```

---

## 🎯 Fitur Utama

### 1. Stratified Randomization

Soal diacak dengan kontrol rasio kesulitan:
- 60% Mudah (Difficulty 1)
- 30% Sedang (Difficulty 2)
- 10% Sukar (Difficulty 3)

Urutan soal **disimpan per sesi** sehingga saat refresh tidak berubah.

### 2. Multi-Tipe Soal

- ✅ **PG (Pilihan Ganda)** - Satu jawaban benar
- ✅ **MCMA (Multiple Choice Multiple Answer)** - Beberapa jawaban benar
- ✅ **Kategori/Menjodohkan** - Matching pairs

### 3. Exam Engine Features

- ⏱️ Timer countdown dengan warning (5 menit terakhir)
- 💾 Auto-save jawaban (debounced 1 detik)
- 🔄 Restore jawaban dari localStorage jika koneksi putus
- 🚩 Flag ragu-ragu
- ⌨️ Keyboard shortcuts (Arrow Left/Right, F=Flag, N=Nav)
- 👁️ Pause timer saat tab tidak aktif (anti-curang)
- 📊 Progress bar real-time
- 📱 Responsive design (Mobile-first)

### 4. Security

- Session-based authentication
- Password hashing (bcrypt)
- SQL Injection prevention (Prepared Statements)
- XSS prevention (htmlspecialchars sanitization)
- CSRF protection (TODO)
- Timer blur detection

---

## 🚀 Cara Menggunakan

### A. Untuk Developer

1. **Clone/Setup Project**
   ```bash
   cd /workspace/cbt-system
   ```

2. **Import Database**
   ```bash
   mysql -u root -p
   CREATE DATABASE cbt_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   exit;
   
   mysql -u root -p cbt_system < ../database_schema.sql
   ```

3. **Create Default Admin** (via MySQL)
   ```sql
   INSERT INTO users (username, password_hash, full_name, role) 
   VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');
   -- Password: password
   ```

4. **Create Sample Student**
   ```sql
   INSERT INTO users (username, password_hash, full_name, role) 
   VALUES ('siswa001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test Student', 'student');
   ```

5. **Insert Sample Exam**
   ```sql
   INSERT INTO exams (title, duration_minutes, difficulty_ratio, total_questions)
   VALUES ('Ujian Matematika IPA', 90, '{"1":0.6,"2":0.3,"3":0.1}', 30);
   ```

6. **Insert Sample Questions**
   ```sql
   INSERT INTO questions (question_code, difficulty, level_kognitif, type, question_text, options, answer_key)
   VALUES 
   ('MATH-001', 1, 1, 'pg', 
    'Berapa hasil dari 5 + 7?', 
    '[{"value":"10","text":"10"},{"value":"11","text":"11"},{"value":"12","text":"12"},{"value":"13","text":"13"}]',
    '"12"'),
   ('MATH-002', 2, 2, 'pg',
    'Jika 2x + 3 = 11, maka x = ?',
    '[{"value":"3","text":"3"},{"value":"4","text":"4"},{"value":"5","text":"5"},{"value":"6","text":"6"}]',
    '"4"');
   ```

7. **Access Login Page**
   ```
   http://localhost/cbt-system/views/student/login.php
   ```

### B. Untuk Siswa

1. Buka halaman login
2. Masukkan username (NISN) dan password
3. Pilih ujian yang tersedia
4. Kerjakan soal dengan navigasi:
   - Panah Kiri/Kanan untuk pindah soal
   - Tombol "Ragu-ragu" untuk tandai soal
   - Tombol "Daftar Soal" untuk lihat overview
5. Klik "Selesai Ujian" ketika selesai

---

## 📝 API Endpoints (TODO)

File-file API yang perlu dibuat:

| Endpoint | Method | Deskripsi |
|----------|--------|-----------|
| `/api/exam/questions.php` | GET | Ambil soal untuk sesi |
| `/api/exam/save-answer.php` | POST | Simpan jawaban |
| `/api/exam/toggle-flag.php` | POST | Toggle flag ragu-ragu |
| `/api/exam/submit.php` | POST | Submit ujian & scoring |
| `/api/exam/log-timer.php` | POST | Log timer activity |

---

## 🔧 TODO List

### Minggu 1: Pondasi ✅
- [x] Database schema
- [x] Config files (DB, Redis, Constants)
- [x] Auth handler
- [x] Randomizer module
- [x] Exam Model
- [x] Login UI
- [x] Exam Interface UI
- [x] Exam Engine JS

### Minggu 2: Core Features
- [ ] API endpoints
- [ ] Exam Controller
- [ ] Start exam flow
- [ ] Result calculation
- [ ] Result display page

### Minggu 3: Admin Panel
- [ ] Admin dashboard
- [ ] Question CRUD
- [ ] Exam creation
- [ ] Student management
- [ ] Reports & exports

### Minggu 4: Polish & Testing
- [ ] Load testing (Redis integration)
- [ ] Security audit
- [ ] Mobile responsiveness test
- [ ] Browser compatibility
- [ ] Documentation

---

## 🛠️ Troubleshooting

### Error: "Database connection failed"
- Cek kredensial di `config/database.php`
- Pastikan MySQL running
- Pastikan database `cbt_system` sudah dibuat

### Error: "Failed to load questions"
- Cek session items sudah ter-generate
- Pastikan session_id valid
- Lihat browser console untuk detail error

### Timer tidak berjalan
- Cek JavaScript console untuk errors
- Pastikan browser support `setInterval`
- Disable browser extensions yang mungkin conflict

---

## 📞 Support

Untuk pertanyaan atau bug report, hubungi tim development.

---

**Version:** 1.1.0  
**Last Updated:** April 2025  
**License:** Proprietary - Kementerian Pendidikan Dasar dan Menengah
