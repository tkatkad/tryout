<?php
/**
 * EXAM MODEL
 * Handle database operations untuk ujian siswa
 */

class ExamModel {
    
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDB();
    }
    
    /**
     * Buat sesi ujian baru untuk siswa
     */
    public function createSession($studentId, $examId) {
        try {
            $token = generateToken(64);
            
            $stmt = $this->pdo->prepare(
                "INSERT INTO exam_sessions (student_id, exam_id, token, status, started_at) 
                 VALUES (?, ?, ?, 'active', NOW())"
            );
            $stmt->execute([$studentId, $examId, $token]);
            
            return $this->pdo->lastInsertId();
            
        } catch (PDOException $e) {
            error_log("Create Session Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Dapatkan sesi ujian berdasarkan token
     */
    public function getSessionByToken($token) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT es.*, e.title as exam_title, e.duration_minutes,
                        u.full_name as student_name
                 FROM exam_sessions es
                 JOIN exams e ON es.exam_id = e.id
                 JOIN users u ON es.student_id = u.id
                 WHERE es.token = ?"
            );
            $stmt->execute([$token]);
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Get Session Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Dapatkan sesi ujian yang sedang aktif untuk siswa
     */
    public function getActiveSession($studentId, $examId = null) {
        try {
            if ($examId) {
                $stmt = $this->pdo->prepare(
                    "SELECT * FROM exam_sessions 
                     WHERE student_id = ? AND exam_id = ? AND status = 'active'"
                );
                $stmt->execute([$studentId, $examId]);
            } else {
                $stmt = $this->pdo->prepare(
                    "SELECT * FROM exam_sessions 
                     WHERE student_id = ? AND status = 'active'
                     ORDER BY started_at DESC
                     LIMIT 1"
                );
                $stmt->execute([$studentId]);
            }
            
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Get Active Session Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Dapatkan soal-soal untuk sesi ujian
     * Join dengan session_items untuk mendapatkan urutan yang benar
     */
    public function getQuestionsForSession($sessionId) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT q.*, si.sort_order, si.answer_given, si.is_flagged, si.time_spent
                 FROM session_items si
                 JOIN questions q ON si.question_id = q.id
                 WHERE si.session_id = ?
                 ORDER BY si.sort_order ASC"
            );
            $stmt->execute([$sessionId]);
            
            $questions = $stmt->fetchAll();
            
            // Parse JSON fields
            foreach ($questions as &$q) {
                $q['options'] = json_decode($q['options'], true);
                $q['answer_key'] = json_decode($q['answer_key'], true);
                $q['answer_given'] = $q['answer_given'] ? json_decode($q['answer_given'], true) : null;
            }
            
            return $questions;
            
        } catch (PDOException $e) {
            error_log("Get Questions Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Simpan jawaban siswa
     */
    public function saveAnswer($sessionId, $questionId, $answer) {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE session_items 
                 SET answer_given = ?, last_updated = NOW()
                 WHERE session_id = ? AND question_id = ?"
            );
            
            $answerJson = json_encode($answer);
            $stmt->execute([$answerJson, $sessionId, $questionId]);
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log("Save Answer Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Toggle flag ragu-ragu
     */
    public function toggleFlag($sessionId, $questionId) {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE session_items 
                 SET is_flagged = NOT is_flagged
                 WHERE session_id = ? AND question_id = ?"
            );
            $stmt->execute([$sessionId, $questionId]);
            
            // Get new flag status
            $stmt = $this->pdo->prepare(
                "SELECT is_flagged FROM session_items 
                 WHERE session_id = ? AND question_id = ?"
            );
            $stmt->execute([$sessionId, $questionId]);
            $result = $stmt->fetch();
            
            return $result['is_flagged'] == 1;
            
        } catch (PDOException $e) {
            error_log("Toggle Flag Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update waktu pengerjaan per soal
     */
    public function updateTimeSpent($sessionId, $questionId, $seconds) {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE session_items 
                 SET time_spent = time_spent + ?
                 WHERE session_id = ? AND question_id = ?"
            );
            $stmt->execute([$seconds, $sessionId, $questionId]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Update Time Spent Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Selesaikan sesi ujian
     */
    public function finishSession($sessionId, $score = 0) {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE exam_sessions 
                 SET status = 'finished', finished_at = NOW(), score = ?
                 WHERE id = ?"
            );
            $stmt->execute([$score, $sessionId]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Finish Session Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log timer activity
     */
    public function logTimerAction($sessionId, $action) {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO timer_logs (session_id, action, ip_address, user_agent) 
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([
                $sessionId,
                $action,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Log Timer Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Hitung skor otomatis
     */
    public function calculateScore($sessionId) {
        try {
            // Ambil semua jawaban siswa
            $stmt = $this->pdo->prepare(
                "SELECT si.question_id, si.answer_given, q.answer_key, q.type
                 FROM session_items si
                 JOIN questions q ON si.question_id = q.id
                 WHERE si.session_id = ?"
            );
            $stmt->execute([$sessionId]);
            $items = $stmt->fetchAll();
            
            $totalQuestions = count($items);
            $correctCount = 0;
            
            foreach ($items as $item) {
                $given = json_decode($item['answer_given'], true);
                $key = json_decode($item['answer_key'], true);
                
                if ($this->checkAnswer($given, $key, $item['type'])) {
                    $correctCount++;
                    
                    // Update is_correct di session_items
                    $updateStmt = $this->pdo->prepare(
                        "UPDATE session_items SET is_correct = 1 
                         WHERE session_id = ? AND question_id = ?"
                    );
                    $updateStmt->execute([$sessionId, $item['question_id']]);
                }
            }
            
            $score = $totalQuestions > 0 ? ($correctCount / $totalQuestions) * 100 : 0;
            
            return round($score, 2);
            
        } catch (PDOException $e) {
            error_log("Calculate Score Error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Cek kebenaran jawaban berdasarkan tipe soal
     */
    private function checkAnswer($given, $key, $type) {
        if ($given === null) return false;
        
        switch ($type) {
            case 'pg': // Pilihan Ganda Biasa
                return $given == $key;
                
            case 'mcma': // Pilihan Ganda Kompleks (Multiple Answer)
                if (!is_array($given) || !is_array($key)) return false;
                sort($given);
                sort($key);
                return $given == $key;
                
            case 'kategori': // Menjodohkan
                if (!is_array($given) || !is_array($key)) return false;
                return $given == $key;
                
            default:
                return false;
        }
    }
    
    /**
     * Dapatkan statistik pengerjaan
     */
    public function getSessionStats($sessionId) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT 
                    COUNT(*) as total_questions,
                    SUM(CASE WHEN answer_given IS NOT NULL THEN 1 ELSE 0 END) as answered,
                    SUM(is_flagged) as flagged,
                    SUM(time_spent) as total_time_spent
                 FROM session_items
                 WHERE session_id = ?"
            );
            $stmt->execute([$sessionId]);
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Get Stats Error: " . $e->getMessage());
            return null;
        }
    }
}
