<!-- Veteran Trader Readiness Checklist Modal -->
<div class="modal fade" id="checklistModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="checklistModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="checklistModalLabel">
                    <i class="bi bi-shield-check text-gold me-2"></i>Veteran Readiness Check
                </h5>
            </div>
            <div class="modal-body pt-3">
                <p class="text-muted-custom small mb-4">Elite traders only trade when they are 90% ready. Complete your daily checklist to unlock trading.</p>
                
                <div id="checklistItems" class="mb-4">
                    <!-- Items will be loaded via AJAX -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-gold" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>

                <div class="readiness-progress mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted-custom">Readiness Score</small>
                        <small id="readinessScore" class="fw-bold">0%</small>
                    </div>
                    <div class="progress" style="height: 8px; background: rgba(255,255,255,0.05);">
                        <div id="readinessProgressBar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <div id="checklistStatusAlert" class="alert alert-soft-danger small d-none" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Minimum 90% readiness required to trade today.
                </div>

                <div id="tradeLimitAlert" class="alert alert-soft-warning border-warning small d-none" role="alert">
                    <i class="bi bi-hand-index-fill me-2"></i>
                    <strong>Daily Trade Limit Reached!</strong><br>
                    You have already taken your <span id="limitCountText">2</span> allowed trades for today. Trading is locked to prevent over-trading.
                    <div class="mt-2 pt-2 border-top border-warning border-opacity-10 d-flex align-items-center justify-content-between">
                        <span>Resets in:</span>
                        <span id="limitResetTimer" class="font-monospace fw-bold text-gold">--:--:--</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" id="submitChecklist" class="btn btn-gold w-100 py-2 border-0 shadow-sm disabled">
                    <i class="bi bi-lightning-charge-fill me-1"></i>Start Trading Session
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .text-gold { color: #FFD700 !important; }
    .btn-gold { background: linear-gradient(135deg, #FFD700 0%, #B8860B 100%); color: #000; font-weight: 600; }
    .btn-gold:hover { background: linear-gradient(135deg, #FFC700 0%, #A8760B 100%); color: #000; transform: translateY(-1px); }
    .btn-gold.disabled { opacity: 0.5; cursor: not-allowed; }
    
    .checklist-item {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        padding: 12px 15px;
        margin-bottom: 10px;
        transition: all 0.2s ease;
        cursor: pointer;
        display: flex;
        align-items: center;
    }
    .checklist-item:hover { background: rgba(255, 255, 255, 0.06); }
    .checklist-item.checked { background: rgba(212, 175, 55, 0.1); border-color: rgba(212, 175, 55, 0.3); }
    
    .checklist-item input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: #FFD700;
        cursor: pointer;
    }
    
    .checklist-item label {
        margin-left: 12px;
        cursor: pointer;
        font-size: 0.9rem;
        flex-grow: 1;
        margin-bottom: 0;
    }
    
    .alert-soft-danger {
        background: rgba(255, 99, 132, 0.1);
        color: #ff6384;
        border: 1px solid rgba(255, 99, 132, 0.2);
    }
    
    .progress-bar {
        background: linear-gradient(to right, #FFD700, #B8860B);
        transition: width 0.4s ease;
    }
    
    .progress-bar-glow {
        box-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checklistModal = new bootstrap.Modal(document.getElementById('checklistModal'));
    const submitBtn = document.getElementById('submitChecklist');
    const itemsContainer = document.getElementById('checklistItems');
    const scoreText = document.getElementById('readinessScore');
    const progressBar = document.getElementById('readinessProgressBar');
    const statusAlert = document.getElementById('checklistStatusAlert');
    const tradeLimitAlert = document.getElementById('tradeLimitAlert');
    const limitCountSpan = document.getElementById('limitCountText');
    const resetTimerSpan = document.getElementById('limitResetTimer');
    
    let checklistData = {
        totalItems: 0,
        responses: {},
        isLimitReached: false
    };

    function startResetTimer() {
        function updateTimer() {
            const now = new Date();
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setHours(0, 0, 0, 0);
            
            const diff = tomorrow - now;
            
            if (diff <= 0) {
                location.reload();
                return;
            }
            
            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);
            
            resetTimerSpan.innerText = 
                String(hours).padStart(2, '0') + ':' + 
                String(minutes).padStart(2, '0') + ':' + 
                String(seconds).padStart(2, '0');
        }
        
        updateTimer();
        setInterval(updateTimer, 1000);
    }

    function updateScore() {
        if (checklistData.isLimitReached) {
            submitBtn.classList.add('disabled');
            submitBtn.innerHTML = '<i class="bi bi-lock-fill me-1"></i>Limit Reached';
            return;
        }

        const checkedCount = Object.values(checklistData.responses).filter(v => v === true).length;
        const score = checklistData.totalItems > 0 ? (checkedCount / checklistData.totalItems) * 100 : 0;
        
        scoreText.innerText = Math.round(score) + '%';
        progressBar.style.width = score + '%';
        
        if (score >= 90) {
            submitBtn.classList.remove('disabled');
            progressBar.classList.add('progress-bar-glow');
            statusAlert.classList.add('d-none');
        } else {
            submitBtn.classList.add('disabled');
            progressBar.classList.remove('progress-bar-glow');
            statusAlert.classList.remove('d-none');
        }
    }

    // Load checklist status
    fetch('api/user/get_checklist_status.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Check trade limit first
                if (data.today_trade_count >= data.daily_trade_limit) {
                    checklistData.isLimitReached = true;
                    limitCountSpan.innerText = data.daily_trade_limit;
                    tradeLimitAlert.classList.remove('d-none');
                    statusAlert.classList.add('d-none');
                    window.isChecklistPassed = false; // Block trading even if checklist was done
                    disableTradeButtons();
                    renderChecklist(data.rules);
                    checklistModal.show();
                    startResetTimer();
                    return;
                }

                if (data.completed && data.passed) {
                    // Already passed today, do nothing
                    window.isChecklistPassed = true;
                } else {
                    window.isChecklistPassed = false;
                    renderChecklist(data.rules);
                    checklistModal.show();
                    
                    // Disable trade buttons globally if not passed
                    disableTradeButtons();
                }
            }
        });

    function renderChecklist(rules) {
        checklistData.totalItems = rules.length;
        itemsContainer.innerHTML = '';
        
        rules.forEach(rule => {
            checklistData.responses[rule.id] = false;
            
            const div = document.createElement('div');
            div.className = 'checklist-item';
            div.innerHTML = `
                <input type="checkbox" id="rule_${rule.id}" data-id="${rule.id}">
                <label for="rule_${rule.id}">${rule.rule_text}</label>
            `;
            
            div.addEventListener('click', (e) => {
                if(e.target.tagName !== 'INPUT') {
                    const checkbox = div.querySelector('input');
                    checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change'));
                }
            });
            
            const checkbox = div.querySelector('input');
            checkbox.addEventListener('change', (e) => {
                checklistData.responses[rule.id] = e.target.checked;
                div.classList.toggle('checked', e.target.checked);
                updateScore();
            });
            
            itemsContainer.appendChild(div);
        });
        
        updateScore();
    }

    submitBtn.addEventListener('click', function() {
        if (submitBtn.classList.contains('disabled')) return;
        
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Calibrating Setup...';
        
        fetch('api/user/save_checklist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ responses: checklistData.responses })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.passed) {
                window.isChecklistPassed = true;
                enableTradeButtons();
                checklistModal.hide();
                
                // Optional success toast
                alert(data.message);
            } else {
                alert(data.message);
                submitBtn.innerHTML = '<i class="bi bi-lightning-charge-fill me-1"></i>Start Trading Session';
            }
        });
    });

    function disableTradeButtons() {
        const tradeBtns = document.querySelectorAll('[data-bs-target="#addTradeModal"], [data-bs-target="#newTradeModal"]');
        tradeBtns.forEach(btn => {
            btn.setAttribute('data-old-target', btn.getAttribute('data-bs-target'));
            btn.removeAttribute('data-bs-target');
            btn.addEventListener('click', handleBlockedTrade);
        });
    }

    function enableTradeButtons() {
        const tradeBtns = document.querySelectorAll('[data-old-target]');
        tradeBtns.forEach(btn => {
            btn.setAttribute('data-bs-target', btn.getAttribute('data-old-target'));
            btn.removeEventListener('click', handleBlockedTrade);
        });
    }

    function handleBlockedTrade(e) {
        if (!window.isChecklistPassed) {
            e.preventDefault();
            checklistModal.show();
        }
    }
});
</script>
