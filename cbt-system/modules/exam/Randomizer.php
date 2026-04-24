<?php
/**
 * STRATIFIED RANDOMIZER
 * Core logic untuk mengacak soal dengan kontrol rasio kesulitan
 * 
 * Algoritma:
 * 1. Ambil rasio dari konfigurasi ujian
 * 2. Group soal berdasarkan difficulty (Mudah, Sedang, Sukar)
 * 3. Shuffle dalam setiap group
 * 4. Slice sesuai kuota
 * 5. Merge semua group
 * 6. Final shuffle dengan seed agar konsisten per siswa
 */

class Randomizer {
    
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDB();
    }
    
    /**
     * Generate urutan soal terkontrol untuk sesi ujian
     * 
     * @param int $examId ID ujian
     * @param int $studentId ID siswa
     * @param int $totalSoal Total soal yang diinginkan
     * @return array Array of question IDs dalam urutan acak terkontrol
     */
    public function generateControlledSet($examId, $studentId, $totalSoal = 30) {
        try {
            // 1. Ambil konfigurasi rasio dari database
            $ratio = $this->getExamDifficultyRatio($examId);
            
            if (empty($ratio)) {
                // Default ratio jika tidak ada konfigurasi
                $ratio = ['1' => 0.6, '2' => 0.3, '3' => 0.1];
            }
            
            $resultOrder = [];
            
            // 2. Loop per tingkat kesulitan
            foreach ($ratio as $difficulty => $percentage) {
                $count = (int)round($totalSoal * $percentage);
                
                if ($count === 0) continue;
                
                // Ambil pool soal (ambil lebih banyak untuk buffer)
                $pool = $this->getQuestionIdsByDifficulty($difficulty, $count * 2);
                
                if (empty($pool)) {
                    // Jika tidak ada soal dengan difficulty ini, skip
                    continue;
                }
                
                // Shuffle pool menggunakan Fisher-Yates
                $this->fisherYatesShuffle($pool);
                
                // Slice sesuai kuota
                $selected = array_slice($pool, 0, $count);
                
                // Merge ke hasil
                $resultOrder = array_merge($resultOrder, $selected);
            }
            
            // 3. Pastikan kita punya cukup soal
            if (count($resultOrder) < $totalSoal) {
                // Fallback: ambil soal tambahan dari pool umum
                $remaining = $totalSoal - count($resultOrder);
                $additional = $this->getAdditionalQuestions($resultOrder, $remaining);
                $resultOrder = array_merge($resultOrder, $additional);
            }
            
            // 4. Final shuffle dengan seed agar konsisten untuk siswa ini
            // Jika siswa refresh halaman, urutan soal TETAP SAMA
            $seed = crc32($studentId . '_' . $examId . '_' . date('Y-m-d'));
            srand($seed);
            shuffle($resultOrder);
            srand(); // Reset seed global
            
            return array_values($resultOrder); // Re-index array
            
        } catch (PDOException $e) {
            error_log("Randomizer Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Ambil rasio kesulitan dari konfigurasi ujian
     */
    private function getExamDifficultyRatio($examId) {
        $stmt = $this->pdo->prepare(
            "SELECT difficulty_ratio FROM exams WHERE id = ?"
        );
        $stmt->execute([$examId]);
        $result = $stmt->fetch();
        
        if ($result && $result['difficulty_ratio']) {
            return json_decode($result['difficulty_ratio'], true);
        }
        
        return null;
    }
    
    /**
     * Ambil ID soal berdasarkan tingkat kesulitan
     */
    private function getQuestionIdsByDifficulty($difficulty, $limit) {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM questions 
             WHERE difficulty = ? 
             ORDER BY RAND() 
             LIMIT ?"
        );
        $stmt->execute([$difficulty, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Ambil soal tambahan jika pool tidak mencukupi
     */
    private function getAdditionalQuestions($excludeIds, $limit) {
        if (empty($excludeIds)) {
            $placeholders = 'NULL';
            $params = [$limit];
        } else {
            $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
            $params = array_merge($excludeIds, [$limit]);
        }
        
        $sql = "SELECT id FROM questions 
                WHERE id NOT IN ($placeholders)
                ORDER BY RAND() 
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Fisher-Yates Shuffle Algorithm
     * Lebih efisien dan uniform dibanding shuffle() bawaan PHP
     */
    private function fisherYatesShuffle(&$array) {
        $n = count($array);
        
        for ($i = $n - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            $temp = $array[$i];
            $array[$i] = $array[$j];
            $array[$j] = $temp;
        }
    }
    
    /**
     * Simpan urutan soal ke session_items
     * Dipanggil setelah generateControlledSet
     */
    public function saveSessionItems($sessionId, $questionIds) {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO session_items (session_id, question_id, sort_order) 
                 VALUES (?, ?, ?)"
            );
            
            foreach ($questionIds as $index => $questionId) {
                $sortOrder = $index + 1;
                $stmt->execute([$sessionId, $questionId, $sortOrder]);
            }
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Save Session Items Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Dapatkan urutan soal untuk sesi yang sudah ada
     * Digunakan saat siswa refresh halaman
     */
    public function getSessionItems($sessionId) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT question_id FROM session_items 
                 WHERE session_id = ? 
                 ORDER BY sort_order ASC"
            );
            $stmt->execute([$sessionId]);
            
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
            
        } catch (PDOException $e) {
            error_log("Get Session Items Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Cek apakah session items sudah ada untuk sesi ini
     */
    public function hasSessionItems($sessionId) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) as count FROM session_items WHERE session_id = ?"
            );
            $stmt->execute([$sessionId]);
            $result = $stmt->fetch();
            
            return $result['count'] > 0;
            
        } catch (PDOException $e) {
            return false;
        }
    }
}
