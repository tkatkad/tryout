/**
 * EXAM ENGINE - VANILLA JS
 * Core logic untuk ujian siswa
 * 
 * Features:
 * - Render soal dinamis
 * - Timer dengan auto-pause on blur
 * - Auto-save jawaban (debounced)
 * - Navigasi soal
 * - Flag ragu-ragu
 * - Submit ujian
 */

// ========================================
// STATE MANAGEMENT
// ========================================
const state = {
    currentQuestionIndex: 0,
    questions: [],
    answers: {},        // { questionId: answer }
    flags: {},          // { questionId: true/false }
    timeSpent: {},      // { questionId: seconds }
    timerSeconds: 0,
    timerInterval: null,
    startTime: null,
    lastSaveTime: null,
    saveTimeout: null,
    isSubmitting: false
};

// ========================================
// INITIALIZATION
// ========================================
async function initExam() {
    try {
        console.log('🚀 Initializing exam...');
        
        // Load questions from API
        await loadQuestions();
        
        // Restore answers from localStorage if any
        restoreAnswers();
        
        // Initialize timer
        startTimer();
        
        // Render first question
        renderQuestion();
        
        // Update navigation grid
        updateNavigationGrid();
        
        // Setup event listeners
        setupEventListeners();
        
        // Log start
        logTimerAction('start');
        
        console.log('✅ Exam initialized successfully');
        
    } catch (error) {
        console.error('❌ Failed to initialize exam:', error);
        alert('Gagal memuat soal. Silakan refresh halaman atau hubungi administrator.');
    }
}

// ========================================
// DATA LOADING
// ========================================
async function loadQuestions() {
    const response = await fetch(`${EXAM_CONFIG.baseUrl}/api/exam/questions.php?session=${EXAM_CONFIG.sessionId}`);
    
    if (!response.ok) {
        throw new Error('Failed to load questions');
    }
    
    const result = await response.json();
    
    if (!result.success) {
        throw new Error(result.message || 'Failed to load questions');
    }
    
    state.questions = result.data || [];
    state.timerSeconds = EXAM_CONFIG.durationSeconds;
    
    console.log(`📚 Loaded ${state.questions.length} questions`);
}

// ========================================
// RENDERING
// ========================================
function renderQuestion() {
    if (state.questions.length === 0) return;
    
    const q = state.questions[state.currentQuestionIndex];
    const qNumber = state.currentQuestionIndex + 1;
    
    // Update question number
    document.getElementById('q-number').textContent = qNumber;
    
    // Update difficulty badge
    const diffInfo = getDifficultyInfo(q.difficulty);
    const diffBadge = document.getElementById('q-difficulty');
    diffBadge.textContent = diffInfo.label;
    diffBadge.className = `text-xs px-3 py-1 rounded-full font-medium ${diffInfo.class}`;
    
    // Update cognitive level
    const cogBadge = document.getElementById('q-cognitive');
    cogBadge.textContent = getCognitiveLabel(q.level_kognitif);
    
    // Render stimulus (if exists)
    const stimulusPanel = document.getElementById('stimulus-panel');
    const stimulusContent = document.getElementById('stimulus-content');
    
    if (q.stimulus && q.stimulus.trim() !== '') {
        stimulusPanel.classList.remove('hidden');
        stimulusContent.innerHTML = parseStimulus(q.stimulus);
    } else {
        stimulusPanel.classList.add('hidden');
    }
    
    // Render question text
    document.getElementById('question-text').innerHTML = q.question_text;
    
    // Render options based on question type
    const container = document.getElementById('options-container');
    container.innerHTML = '';
    
    // Hide MCMA specific actions by default
    document.getElementById('mcma-actions').classList.add('hidden');
    
    switch (q.type) {
        case 'pg':
            renderMultipleChoice(q, container);
            break;
        case 'mcma':
            renderMultipleComplex(q, container);
            document.getElementById('mcma-actions').classList.remove('hidden');
            break;
        case 'kategori':
        case 'menjodohkan':
            renderMatching(q, container);
            break;
        default:
            container.innerHTML = '<p class="text-red-500">Tipe soal tidak dikenali</p>';
    }
    
    // Update button states
    updateButtonStates();
    
    // Update progress
    updateProgress();
    
    // Highlight current in nav grid
    updateNavigationGrid();
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function renderMultipleChoice(q, container) {
    const currentAnswer = state.answers[q.id];
    
    q.options.forEach((opt, idx) => {
        const isChecked = currentAnswer == opt.value ? 'checked' : '';
        const html = `
            <label class="flex items-start space-x-3 p-4 border-2 rounded-lg hover:bg-blue-50 cursor-pointer transition-all ${isChecked ? 'border-blue-500 bg-blue-50' : 'border-gray-200'}">
                <input type="radio" 
                       name="q_${q.id}" 
                       value="${opt.value}" 
                       ${isChecked}
                       onchange="saveAnswer(${q.id}, this.value)"
                       class="mt-1 w-4 h-4 text-blue-600 focus:ring-blue-500">
                <span class="flex-1">
                    <strong>${String.fromCharCode(65 + idx)}.</strong> 
                    ${opt.text}
                </span>
            </label>
        `;
        container.innerHTML += html;
    });
}

function renderMultipleComplex(q, container) {
    const currentAnswer = state.answers[q.id] || [];
    
    q.options.forEach((opt, idx) => {
        const isChecked = Array.isArray(currentAnswer) && currentAnswer.includes(opt.value) ? 'checked' : '';
        const html = `
            <label class="flex items-start space-x-3 p-4 border-2 rounded-lg hover:bg-blue-50 cursor-pointer transition-all ${isChecked ? 'border-blue-500 bg-blue-50' : 'border-gray-200'}">
                <input type="checkbox" 
                       name="q_${q.id}[]" 
                       value="${opt.value}" 
                       ${isChecked}
                       onchange="saveMCMAAnswer(${q.id})"
                       class="mt-1 w-4 h-4 text-blue-600 focus:ring-blue-500">
                <span class="flex-1">
                    <strong>${String.fromCharCode(65 + idx)}.</strong> 
                    ${opt.text}
                </span>
            </label>
        `;
        container.innerHTML += html;
    });
}

function renderMatching(q, container) {
    const currentAnswer = state.answers[q.id] || {};
    
    // Group matching pairs
    const leftColumn = q.options.filter(o => o.side === 'left');
    const rightColumn = q.options.filter(o => o.side === 'right');
    
    let html = '<div class="grid grid-cols-2 gap-4">';
    
    leftColumn.forEach(left => {
        html += `
            <div class="p-3 bg-gray-50 rounded-lg">
                <p class="font-medium mb-2">${left.text}</p>
                <select onchange="saveMatchingAnswer(${q.id}, ${left.id}, this.value)" 
                        class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">-- Pilih --</option>
                    ${rightColumn.map(right => {
                        const isSelected = currentAnswer[left.id] == right.id ? 'selected' : '';
                        return `<option value="${right.id}" ${isSelected}>${right.text}</option>`;
                    }).join('')}
                </select>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

// ========================================
// ANSWER SAVING
// ========================================
function saveAnswer(questionId, value) {
    state.answers[questionId] = value;
    
    // Save to localStorage immediately (backup)
    localStorage.setItem(`exam_answers_${EXAM_CONFIG.sessionId}`, JSON.stringify(state.answers));
    
    // Debounce API call
    clearTimeout(state.saveTimeout);
    state.saveTimeout = setTimeout(() => {
        sendAnswerToServer(questionId, value);
    }, 1000);
    
    // Update UI
    renderQuestion(); // Re-render to show selected state
    updateNavigationGrid();
}

function saveMCMAAnswer(questionId) {
    const checkboxes = document.querySelectorAll(`input[name="q_${questionId}[]"]:checked`);
    const values = Array.from(checkboxes).map(cb => cb.value);
    
    saveAnswer(questionId, values);
}

function saveMatchingAnswer(questionId, leftId, rightId) {
    if (!state.answers[questionId]) {
        state.answers[questionId] = {};
    }
    
    if (rightId === '') {
        delete state.answers[questionId][leftId];
    } else {
        state.answers[questionId][leftId] = parseInt(rightId);
    }
    
    localStorage.setItem(`exam_answers_${EXAM_CONFIG.sessionId}`, JSON.stringify(state.answers));
    
    clearTimeout(state.saveTimeout);
    state.saveTimeout = setTimeout(() => {
        sendAnswerToServer(questionId, state.answers[questionId]);
    }, 1000);
}

async function sendAnswerToServer(questionId, value) {
    try {
        const response = await fetch(`${EXAM_CONFIG.baseUrl}/api/exam/save-answer.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                session_id: EXAM_CONFIG.sessionId,
                question_id: questionId,
                answer: value
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log(`✅ Answer saved for Q${questionId}`);
            state.lastSaveTime = Date.now();
        }
    } catch (error) {
        console.error('Failed to save answer:', error);
        // Keep in localStorage, will retry later
    }
}

function restoreAnswers() {
    const saved = localStorage.getItem(`exam_answers_${EXAM_CONFIG.sessionId}`);
    if (saved) {
        try {
            state.answers = JSON.parse(saved);
            console.log(`♻️ Restored ${Object.keys(state.answers).length} answers from localStorage`);
        } catch (e) {
            console.error('Failed to restore answers:', e);
        }
    }
}

// ========================================
// TIMER
// ========================================
function startTimer() {
    const timerEl = document.getElementById('timer');
    
    state.timerInterval = setInterval(() => {
        // Pause timer if tab is not visible
        if (document.hidden) {
            return;
        }
        
        state.timerSeconds--;
        
        // Update display
        const hours = Math.floor(state.timerSeconds / 3600);
        const minutes = Math.floor((state.timerSeconds % 3600) / 60);
        const seconds = state.timerSeconds % 60;
        
        timerEl.textContent = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        
        // Warning colors
        timerEl.classList.remove('timer-warning');
        if (state.timerSeconds <= 300) { // Less than 5 minutes
            timerEl.classList.add('timer-warning');
        }
        
        // Auto-submit when time's up
        if (state.timerSeconds <= 0) {
            clearInterval(state.timerInterval);
            submitExam();
        }
        
    }, 1000);
    
    // Track time spent per question
    state.startTime = Date.now();
}

function stopTimer() {
    if (state.timerInterval) {
        clearInterval(state.timerInterval);
        state.timerInterval = null;
    }
}

// ========================================
// NAVIGATION
// ========================================
function goToQuestion(index) {
    if (index < 0 || index >= state.questions.length) return;
    
    // Save time spent on current question
    const now = Date.now();
    const timeSpent = Math.floor((now - state.startTime) / 1000);
    const currentQ = state.questions[state.currentQuestionIndex];
    
    if (!state.timeSpent[currentQ.id]) {
        state.timeSpent[currentQ.id] = 0;
    }
    state.timeSpent[currentQ.id] += timeSpent;
    
    // Move to new question
    state.currentQuestionIndex = index;
    state.startTime = now;
    
    renderQuestion();
}

function nextQuestion() {
    goToQuestion(state.currentQuestionIndex + 1);
}

function prevQuestion() {
    goToQuestion(state.currentQuestionIndex - 1);
}

function toggleFlag() {
    const q = state.questions[state.currentQuestionIndex];
    
    state.flags[q.id] = !state.flags[q.id];
    
    // Update server
    fetch(`${EXAM_CONFIG.baseUrl}/api/exam/toggle-flag.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            session_id: EXAM_CONFIG.sessionId,
            question_id: q.id
        })
    });
    
    renderQuestion();
    updateNavigationGrid();
}

function clearAnswer() {
    const q = state.questions[state.currentQuestionIndex];
    delete state.answers[q.id];
    
    localStorage.setItem(`exam_answers_${EXAM_CONFIG.sessionId}`, JSON.stringify(state.answers));
    
    renderQuestion();
    updateNavigationGrid();
}

function updateButtonStates() {
    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');
    
    btnPrev.disabled = state.currentQuestionIndex === 0;
    
    // Change "Next" to "Finish" on last question
    if (state.currentQuestionIndex === state.questions.length - 1) {
        btnNext.innerHTML = '✓ Selesai';
        btnNext.onclick = confirmFinishExam;
    } else {
        btnNext.innerHTML = 'Selanjutnya →';
        btnNext.onclick = nextQuestion;
    }
}

function updateProgress() {
    const answered = Object.keys(state.answers).length;
    const flagged = Object.values(state.flags).filter(f => f).length;
    const total = state.questions.length;
    const percent = (answered / total) * 100;
    
    document.getElementById('progress-text').textContent = `${answered}/${total}`;
    document.getElementById('answered-count').textContent = answered;
    document.getElementById('flagged-count').textContent = flagged;
    document.getElementById('progress-bar').style.width = `${percent}%`;
}

function updateNavigationGrid() {
    const grid = document.getElementById('nav-grid');
    grid.innerHTML = '';
    
    let answered = 0;
    let flagged = 0;
    
    state.questions.forEach((q, idx) => {
        const isAnswered = state.answers[q.id] ? true : false;
        const isFlagged = state.flags[q.id];
        const isCurrent = idx === state.currentQuestionIndex;
        
        let className = 'w-full aspect-square flex items-center justify-center rounded-lg font-semibold text-sm transition-all cursor-pointer ';
        
        if (isCurrent) {
            className += 'bg-green-500 text-white ring-4 ring-green-300';
        } else if (isFlagged) {
            className += 'bg-yellow-400 text-white hover:bg-yellow-500';
        } else if (isAnswered) {
            className += 'bg-blue-600 text-white hover:bg-blue-700';
        } else {
            className += 'bg-white border-2 border-gray-300 text-gray-700 hover:bg-gray-50';
        }
        
        const btn = document.createElement('button');
        btn.className = className;
        btn.textContent = idx + 1;
        btn.onclick = () => {
            goToQuestion(idx);
            toggleNavModal();
        };
        
        grid.appendChild(btn);
        
        if (isAnswered) answered++;
        if (isFlagged) flagged++;
    });
    
    // Update stats
    document.getElementById('nav-total').textContent = state.questions.length;
    document.getElementById('nav-answered').textContent = answered;
    document.getElementById('nav-remaining').textContent = state.questions.length - answered;
}

function toggleNavModal() {
    const modal = document.getElementById('nav-modal');
    modal.classList.toggle('hidden');
}

// ========================================
// SUBMIT EXAM
// ========================================
function confirmFinishExam() {
    const answered = Object.keys(state.answers).length;
    const total = state.questions.length;
    
    document.getElementById('finish-answered').textContent = answered;
    document.getElementById('finish-total').textContent = total;
    
    document.getElementById('finish-modal').classList.remove('hidden');
}

async function submitExam() {
    if (state.isSubmitting) return;
    
    state.isSubmitting = true;
    stopTimer();
    
    try {
        // Show loading
        document.getElementById('finish-modal').innerHTML = `
            <div class="text-center p-8">
                <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <h3 class="text-xl font-bold mb-2">Mengirim Jawaban...</h3>
                <p class="text-gray-600">Mohon tunggu, jangan tutup halaman ini.</p>
            </div>
        `;
        
        // Send all answers to server
        const response = await fetch(`${EXAM_CONFIG.baseUrl}/api/exam/submit.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                session_id: EXAM_CONFIG.sessionId,
                answers: state.answers,
                time_spent: state.timeSpent
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Clear localStorage
            localStorage.removeItem(`exam_answers_${EXAM_CONFIG.sessionId}`);
            
            // Redirect to result page
            window.location.href = `${EXAM_CONFIG.baseUrl}/views/student/result.php?session=${EXAM_CONFIG.sessionId}`;
        } else {
            throw new Error(result.message || 'Submit failed');
        }
        
    } catch (error) {
        console.error('Submit error:', error);
        alert('Gagal mengirim jawaban. Pastikan koneksi internet stabil. ' + error.message);
        state.isSubmitting = false;
        
        // Restore modal
        location.reload();
    }
}

// ========================================
// UTILITY FUNCTIONS
// ========================================
function getDifficultyInfo(level) {
    const map = {
        1: { label: 'Mudah', class: 'bg-green-100 text-green-800' },
        2: { label: 'Sedang', class: 'bg-yellow-100 text-yellow-800' },
        3: { label: 'Sukar', class: 'bg-red-100 text-red-800' }
    };
    return map[level] || map[2];
}

function getCognitiveLabel(level) {
    const map = {
        1: 'LOTS',
        2: 'MOTS',
        3: 'HOTS'
    };
    return map[level] || '-';
}

function parseStimulus(content) {
    // Check if it's an image URL
    if (content.match(/\.(jpg|jpeg|png|gif)$/i)) {
        return `<img src="${content}" alt="Stimulus" class="max-w-full h-auto rounded-lg">`;
    }
    // Otherwise treat as HTML/text
    return content;
}

function logTimerAction(action) {
    navigator.sendBeacon(
        `${EXAM_CONFIG.baseUrl}/api/exam/log-timer.php`,
        JSON.stringify({
            session_id: EXAM_CONFIG.sessionId,
            action: action
        })
    );
}

function setupEventListeners() {
    // Navigation buttons
    document.getElementById('btn-prev').addEventListener('click', prevQuestion);
    document.getElementById('btn-next').addEventListener('click', nextQuestion);
    document.getElementById('btn-flag').addEventListener('click', toggleFlag);
    document.getElementById('btn-clear').addEventListener('click', clearAnswer);
    
    // MCMA save button
    document.getElementById('btn-save-mcma').addEventListener('click', () => {
        const q = state.questions[state.currentQuestionIndex];
        if (q && q.type === 'mcma') {
            saveMCMAAnswer(q.id);
            alert('Jawaban tersimpan!');
        }
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        
        switch(e.key) {
            case 'ArrowLeft':
                prevQuestion();
                break;
            case 'ArrowRight':
                nextQuestion();
                break;
            case 'f':
            case 'F':
                toggleFlag();
                break;
            case 'n':
            case 'N':
                toggleNavModal();
                break;
        }
    });
    
    // Visibility change (pause timer on blur)
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            logTimerAction('blur_detected');
        } else {
            logTimerAction('focus_regained');
        }
    });
    
    // Warn before leaving
    window.addEventListener('beforeunload', (e) => {
        if (!state.isSubmitting) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
}

// Export for global access
window.initExam = initExam;
window.renderQuestion = renderQuestion;
window.saveAnswer = saveAnswer;
window.saveMCMAAnswer = saveMCMAAnswer;
window.saveMatchingAnswer = saveMatchingAnswer;
window.nextQuestion = nextQuestion;
window.prevQuestion = prevQuestion;
window.toggleFlag = toggleFlag;
window.clearAnswer = clearAnswer;
window.toggleNavModal = toggleNavModal;
window.confirmFinishExam = confirmFinishExam;
window.submitExam = submitExam;
