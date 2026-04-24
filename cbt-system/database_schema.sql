-- --------------------------------------------------------
-- CBT SYSTEM - DATABASE SCHEMA (BLUEPRINT V1.1)
-- Target: MySQL 8.0+
-- --------------------------------------------------------

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- 1. TABEL USERS (SISWA & ADMIN)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100),
  role ENUM('admin', 'student') DEFAULT 'student',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. BANK SOAL (QUESTIONS)
-- Menyimpan stimulus, soal, dan opsi dalam format JSON
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_code VARCHAR(20) UNIQUE,
  difficulty TINYINT NOT NULL DEFAULT 2 COMMENT '1:Mudah, 2:Sedang, 3:Sukar',
  level_kognitif TINYINT NOT NULL COMMENT '1:LOTS, 2:MOTS, 3:HOTS',
  type ENUM('pg', 'mcma', 'kategori', 'menjodohkan') NOT NULL,
  stimulus TEXT COMMENT 'Teks atau URL Gambar untuk Stimulus',
  question_text TEXT NOT NULL,
  options JSON NOT NULL COMMENT 'Array of {key, value, is_correct}',
  answer_key JSON NOT NULL COMMENT 'Kunci jawaban resmi',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_difficulty (difficulty),
  INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. PAKET UJIAN (EXAMS)
-- Mengatur rasio kesulitan dan durasi
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS exams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100) NOT NULL,
  description TEXT,
  duration_minutes INT DEFAULT 75,
  difficulty_ratio JSON NOT NULL COMMENT '{"1": 0.6, "2": 0.3, "3": 0.1}',
  total_questions INT DEFAULT 30,
  passing_grade DECIMAL(5,2) DEFAULT 0.00,
  is_active TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. SESI UJIAN SISWA (EXAM SESSIONS)
-- Token unik untuk setiap sesi ujian
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  exam_id INT NOT NULL,
  token VARCHAR(64) UNIQUE NOT NULL,
  status ENUM('pending', 'active', 'finished', 'abandoned') DEFAULT 'pending',
  score DECIMAL(5,2) DEFAULT 0.00,
  started_at TIMESTAMP NULL,
  finished_at TIMESTAMP NULL,
  last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  INDEX idx_token (token),
  INDEX idx_student_status (student_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. ITEM SESI (SESSION ITEMS)
-- MENYIMPAN URUTAN SOAL YANG SUDAH DIACAK PER SISWA
-- Ini kunci agar saat refresh urutan soal tidak berubah
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS session_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  question_id INT NOT NULL,
  sort_order INT NOT NULL COMMENT 'Urutan tampilan 1..N',
  answer_given JSON COMMENT 'Jawaban siswa (cache)',
  time_spent INT DEFAULT 0 COMMENT 'Durasi pengerjaan dalam detik',
  is_flagged TINYINT DEFAULT 0 COMMENT 'Tanda ragu-ragu',
  is_correct TINYINT DEFAULT NULL COMMENT 'Status benar/salah (diisi saat submit)',
  FOREIGN KEY (session_id) REFERENCES exam_sessions(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE RESTRICT,
  UNIQUE KEY unique_session_question (session_id, question_id),
  INDEX idx_sort (session_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. LOG TIMER (AUDIT TRAIL)
-- Untuk mendeteksi kecurangan atau masalah koneksi
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS timer_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  action ENUM('start', 'pause', 'resume', 'finish', 'blur_detected', 'focus_regained') NOT NULL,
  ip_address VARCHAR(45),
  user_agent VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (session_id) REFERENCES exam_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
