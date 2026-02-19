<?php
session_start();
require_once 'config.php';
$auth = new StudentAuth();
if (!$auth->isLoggedIn()) { header('Location: login.php'); exit; }
$student = $auth->getCurrentStudent();
$db = getPadakDB();
$sid = (int)$student['id'];
$activePage = 'game';

// Create game_scores table if it doesn't exist (do this FIRST)
$db->query("CREATE TABLE IF NOT EXISTS game_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    game_type VARCHAR(50),
    score INT,
    level_reached INT,
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY(student_id)
)");

// Handle score submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_score'])) {
    $game_type = $db->real_escape_string($_POST['game_type']);
    $score = (int)$_POST['score'];
    $level = (int)$_POST['level'];
    
    $db->query("INSERT INTO game_scores (student_id, game_type, score, level_reached) 
                VALUES ($sid, '$game_type', $score, $level)");
}

// Get leaderboard - just show student IDs to avoid table issues
$leaderboard = [];
$res = $db->query("SELECT student_id, MAX(score) as best_score 
                   FROM game_scores 
                   WHERE game_type = 'points_quest'
                   GROUP BY student_id 
                   ORDER BY best_score DESC LIMIT 10");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        // Check if this is the current player
        if ($r['student_id'] == $sid) {
            $r['display_name'] = 'You';
        } else {
            $r['display_name'] = 'Student #' . $r['student_id'];
        }
        $r['best_score'] = (int)$r['best_score'];
        $leaderboard[] = $r;
    }
}

// Get player's best score
$myBest = 0;
$res = $db->query("SELECT MAX(score) as best FROM game_scores WHERE student_id=$sid AND game_type='points_quest'");
if ($res && $row = $res->fetch_assoc()) $myBest = (int)$row['best'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Points Quest - Padak Games</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{--sbw:258px;--o5:#f97316;--o4:#fb923c;--o6:#ea580c;--bg:#f8fafc;--card:#fff;--text:#0f172a;--text2:#475569;--text3:#94a3b8;--border:#e2e8f0;--red:#ef4444;--green:#22c55e;--blue:#3b82f6;--yellow:#eab308;--purple:#8b5cf6;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);overflow-x:hidden;}
.page-wrap{margin-left:var(--sbw);min-height:100vh;}
.topbar{position:sticky;top:0;z-index:100;background:rgba(248,250,252,0.92);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);padding:12px 28px;display:flex;align-items:center;gap:12px;}
.topbar-hamburger{display:none;background:none;border:none;cursor:pointer;color:var(--text2);padding:6px;border-radius:7px;}
.topbar-title{font-size:1rem;font-weight:600;color:var(--text);flex:1;}
.main-content{padding:24px 28px;max-width:1400px;margin:0 auto;}

/* Game Header */
.game-header{text-align:center;margin-bottom:30px;}
.game-header h1{font-size:2rem;font-weight:800;background:linear-gradient(135deg,var(--o5),var(--o4));-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:8px;}
.game-header p{color:var(--text2);font-size:.95rem;}

/* Game Container */
.game-container{display:grid;grid-template-columns:1fr 320px;gap:20px;margin-bottom:24px;}
.game-main{background:var(--card);border-radius:16px;border:1px solid var(--border);box-shadow:0 4px 15px rgba(0,0,0,0.08);overflow:hidden;}
.game-sidebar{display:flex;flex-direction:column;gap:16px;}

/* Game Canvas Area */
.game-canvas-wrap{padding:24px;display:flex;flex-direction:column;align-items:center;gap:20px;}
.game-stats{display:flex;gap:16px;width:100%;max-width:600px;justify-content:center;}
.stat-box{flex:1;background:linear-gradient(135deg,rgba(249,115,22,0.08),rgba(251,146,60,0.05));border-radius:12px;padding:14px;text-align:center;border:1.5px solid rgba(249,115,22,0.15);}
.stat-label{font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:4px;}
.stat-value{font-size:1.6rem;font-weight:800;color:var(--o5);}
.stat-box.level{background:linear-gradient(135deg,rgba(139,92,246,0.08),rgba(167,139,250,0.05));border-color:rgba(139,92,246,0.15);}
.stat-box.level .stat-value{color:var(--purple);}
.stat-box.lives{background:linear-gradient(135deg,rgba(239,68,68,0.08),rgba(248,113,113,0.05));border-color:rgba(239,68,68,0.15);}
.stat-box.lives .stat-value{color:var(--red);}

/* Game Board */
.game-board{width:100%;max-width:600px;aspect-ratio:1;background:linear-gradient(145deg,#f8fafc,#f1f5f9);border-radius:16px;border:2px solid var(--border);position:relative;display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:20px;box-shadow:inset 0 2px 8px rgba(0,0,0,0.05);}
.game-tile{background:var(--card);border-radius:12px;border:2px solid var(--border);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:2.5rem;transition:all .3s cubic-bezier(.4,0,.2,1);box-shadow:0 2px 8px rgba(0,0,0,0.1);position:relative;overflow:hidden;}
.game-tile:hover{transform:translateY(-4px) scale(1.05);box-shadow:0 8px 20px rgba(249,115,22,0.3);border-color:var(--o5);}
.game-tile.flipped{background:linear-gradient(135deg,var(--o5),var(--o4));border-color:var(--o5);transform:rotateY(180deg) scale(1.05);box-shadow:0 8px 25px rgba(249,115,22,0.4);}
.game-tile.matched{background:linear-gradient(135deg,var(--green),#16a34a);border-color:var(--green);animation:matchPulse .5s ease;pointer-events:none;opacity:.7;}
.game-tile.wrong{background:linear-gradient(135deg,var(--red),#dc2626);border-color:var(--red);animation:shake .5s ease;}
.game-tile i{opacity:0;transition:opacity .2s;}
.game-tile.flipped i,.game-tile.matched i{opacity:1;}
.game-tile::before{content:'?';position:absolute;font-size:2rem;font-weight:700;color:var(--o5);opacity:.3;}
.game-tile.flipped::before,.game-tile.matched::before{opacity:0;}

/* Game Controls */
.game-controls{display:flex;gap:12px;width:100%;max-width:600px;}
.game-btn{flex:1;padding:14px 24px;border-radius:10px;font-size:.9rem;font-weight:700;cursor:pointer;border:none;transition:all .3s;display:flex;align-items:center;justify-content:center;gap:8px;text-transform:uppercase;letter-spacing:.5px;}
.game-btn.primary{background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;box-shadow:0 4px 15px rgba(249,115,22,0.4);}
.game-btn.primary:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(249,115,22,0.5);}
.game-btn.primary:active{transform:translateY(0);}
.game-btn.secondary{background:var(--bg);border:2px solid var(--border);color:var(--text2);}
.game-btn.secondary:hover{border-color:var(--o5);color:var(--o5);}
.game-btn:disabled{opacity:.5;cursor:not-allowed;}

/* Sidebar Cards */
.side-card{background:var(--card);border-radius:14px;border:1px solid var(--border);padding:18px;box-shadow:0 2px 8px rgba(0,0,0,0.06);}
.side-card-title{font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:14px;display:flex;align-items:center;gap:8px;}
.side-card-title i{color:var(--o5);}

/* How to Play */
.how-item{display:flex;align-items:flex-start;gap:10px;margin-bottom:10px;font-size:.8rem;color:var(--text2);line-height:1.5;}
.how-item:last-child{margin-bottom:0;}
.how-num{width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;flex-shrink:0;}

/* Leaderboard */
.leader-list{display:flex;flex-direction:column;gap:8px;}
.leader-item{display:flex;align-items:center;gap:10px;padding:10px;background:var(--bg);border-radius:8px;border:1px solid var(--border);}
.leader-rank{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;}
.leader-item:nth-child(1) .leader-rank{background:linear-gradient(135deg,#fbbf24,#f59e0b);box-shadow:0 2px 8px rgba(251,191,36,0.4);}
.leader-item:nth-child(2) .leader-rank{background:linear-gradient(135deg,#94a3b8,#64748b);}
.leader-item:nth-child(3) .leader-rank{background:linear-gradient(135deg,#fb923c,#ea580c);}
.leader-name{flex:1;font-size:.8rem;font-weight:600;color:var(--text);}
.leader-score{font-size:.8rem;font-weight:700;color:var(--o5);}

/* Modal */
.modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.7);backdrop-filter:blur(4px);z-index:1000;display:none;align-items:center;justify-content:center;padding:20px;}
.modal-overlay.active{display:flex;}
.modal{background:var(--card);border-radius:20px;padding:32px;max-width:400px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.3);border:1px solid var(--border);text-align:center;animation:modalSlideUp .3s ease;}
.modal-icon{font-size:4rem;margin-bottom:16px;}
.modal-icon.success{color:var(--green);}
.modal-icon.fail{color:var(--red);}
.modal h2{font-size:1.6rem;font-weight:800;margin-bottom:8px;}
.modal p{color:var(--text2);margin-bottom:20px;font-size:.9rem;}
.modal-score{font-size:2.5rem;font-weight:800;background:linear-gradient(135deg,var(--o5),var(--o4));-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin:16px 0;}
.modal-stats{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;}
.modal-stat{padding:12px;background:var(--bg);border-radius:10px;border:1px solid var(--border);}
.modal-stat-label{font-size:.7rem;color:var(--text3);font-weight:600;margin-bottom:4px;}
.modal-stat-value{font-size:1.3rem;font-weight:700;color:var(--o5);}

@keyframes matchPulse{0%,100%{transform:scale(1);}50%{transform:scale(1.1);}}
@keyframes shake{0%,100%{transform:translateX(0);}25%{transform:translateX(-10px);}75%{transform:translateX(10px);}}
@keyframes modalSlideUp{from{transform:translateY(30px);opacity:0;}to{transform:translateY(0);opacity:1;}}

@media(max-width:1024px){
    .game-container{grid-template-columns:1fr;}
    .game-sidebar{grid-template-columns:1fr 1fr;gap:16px;}
}
@media(max-width:768px){
    .page-wrap{margin-left:0;}
    .topbar-hamburger{display:flex;}
    .main-content{padding:16px;}
    .game-header h1{font-size:1.5rem;}
    .game-board{padding:12px;gap:8px;}
    .game-tile{font-size:1.8rem;}
    .game-sidebar{grid-template-columns:1fr;}
}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="page-wrap">
    <div class="topbar">
        <button class="topbar-hamburger" onclick="toggleSidebar()"><i class="fas fa-bars fa-sm"></i></button>
        <div class="topbar-title"><i class="fas fa-gamepad"></i> Points Quest</div>
    </div>
    
    <div class="main-content">
        <div class="game-header">
            <h1><i class="fas fa-trophy"></i> Padak Points Quest</h1>
            <p>Match the icons and collect points! Train your memory and reflexes.</p>
        </div>

        <div class="game-container">
            <div class="game-main">
                <div class="game-canvas-wrap">
                    <div class="game-stats">
                        <div class="stat-box">
                            <div class="stat-label">Score</div>
                            <div class="stat-value" id="scoreDisplay">0</div>
                        </div>
                        <div class="stat-box level">
                            <div class="stat-label">Level</div>
                            <div class="stat-value" id="levelDisplay">1</div>
                        </div>
                        <div class="stat-box lives">
                            <div class="stat-label">Lives</div>
                            <div class="stat-value" id="livesDisplay">❤️❤️❤️</div>
                        </div>
                    </div>

                    <div class="game-board" id="gameBoard"></div>

                    <div class="game-controls">
                        <button class="game-btn primary" id="startBtn">
                            <i class="fas fa-play"></i> Start Game
                        </button>
                        <button class="game-btn secondary" id="resetBtn">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>

            <div class="game-sidebar">
                <div class="side-card">
                    <div class="side-card-title">
                        <i class="fas fa-question-circle"></i> How to Play
                    </div>
                    <div class="how-item">
                        <div class="how-num">1</div>
                        <div>Click <strong>Start Game</strong> to begin</div>
                    </div>
                    <div class="how-item">
                        <div class="how-num">2</div>
                        <div>Click tiles to reveal icons</div>
                    </div>
                    <div class="how-item">
                        <div class="how-num">3</div>
                        <div>Match pairs of identical icons</div>
                    </div>
                    <div class="how-item">
                        <div class="how-num">4</div>
                        <div>Complete all pairs to advance levels</div>
                    </div>
                    <div class="how-item">
                        <div class="how-num">5</div>
                        <div>You have 3 lives - don't waste them!</div>
                    </div>
                </div>

                <div class="side-card">
                    <div class="side-card-title">
                        <i class="fas fa-crown"></i> Leaderboard
                    </div>
                    <?php if (empty($leaderboard)): ?>
                        <p style="font-size:.8rem;color:var(--text3);text-align:center;padding:10px 0;">Be the first to play!</p>
                    <?php else: ?>
                        <div class="leader-list">
                            <?php foreach ($leaderboard as $i => $leader): ?>
                            <div class="leader-item">
                                <div class="leader-rank"><?php echo $i+1; ?></div>
                                <div class="leader-name"><?php echo htmlspecialchars($leader['display_name']); ?></div>
                                <div class="leader-score"><?php echo $leader['best_score']; ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($myBest > 0): ?>
                    <div style="margin-top:12px;padding:10px;background:rgba(249,115,22,0.08);border-radius:8px;text-align:center;">
                        <span style="font-size:.75rem;color:var(--text3);font-weight:600;">YOUR BEST</span>
                        <div style="font-size:1.3rem;font-weight:800;color:var(--o5);"><?php echo $myBest; ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Game Over Modal -->
<div class="modal-overlay" id="gameOverModal">
    <div class="modal">
        <div class="modal-icon" id="modalIcon"></div>
        <h2 id="modalTitle"></h2>
        <p id="modalMessage"></p>
        <div class="modal-score" id="modalScore"></div>
        <div class="modal-stats">
            <div class="modal-stat">
                <div class="modal-stat-label">Level Reached</div>
                <div class="modal-stat-value" id="modalLevel"></div>
            </div>
            <div class="modal-stat">
                <div class="modal-stat-label">Best Score</div>
                <div class="modal-stat-value"><?php echo $myBest; ?></div>
            </div>
        </div>
        <form method="POST" style="display:none;" id="scoreForm">
            <input type="hidden" name="submit_score" value="1">
            <input type="hidden" name="game_type" value="points_quest">
            <input type="hidden" name="score" id="finalScore">
            <input type="hidden" name="level" id="finalLevel">
        </form>
        <button class="game-btn primary" onclick="location.reload()" style="width:100%;">
            <i class="fas fa-play"></i> Play Again
        </button>
    </div>
</div>

<script>
class PointsQuest {
    constructor() {
        this.icons = ['💼','📊','✅','🎯','🏆','📝','💡','⭐','🚀','📈','🎓','💻'];
        this.score = 0;
        this.level = 1;
        this.lives = 3;
        this.flippedTiles = [];
        this.matchedPairs = 0;
        this.totalPairs = 0;
        this.canFlip = false;
        this.gameBoard = document.getElementById('gameBoard');
        this.startBtn = document.getElementById('startBtn');
        this.resetBtn = document.getElementById('resetBtn');
        
        this.startBtn.addEventListener('click', () => this.startGame());
        this.resetBtn.addEventListener('click', () => this.resetGame());
    }

    startGame() {
        this.canFlip = true;
        this.startBtn.disabled = true;
        this.initBoard();
        this.updateDisplay();
    }

    initBoard() {
        this.gameBoard.innerHTML = '';
        const pairsCount = Math.min(8, 4 + this.level);
        this.totalPairs = pairsCount;
        this.matchedPairs = 0;
        
        const selectedIcons = this.icons.slice(0, pairsCount);
        const tiles = [...selectedIcons, ...selectedIcons];
        tiles.sort(() => Math.random() - 0.5);
        
        tiles.forEach((icon, index) => {
            const tile = document.createElement('div');
            tile.className = 'game-tile';
            tile.dataset.icon = icon;
            tile.dataset.index = index;
            tile.innerHTML = `<i>${icon}</i>`;
            tile.addEventListener('click', () => this.flipTile(tile));
            this.gameBoard.appendChild(tile);
        });
    }

    flipTile(tile) {
        if (!this.canFlip || tile.classList.contains('flipped') || 
            tile.classList.contains('matched') || this.flippedTiles.length >= 2) return;
        
        tile.classList.add('flipped');
        this.flippedTiles.push(tile);
        
        if (this.flippedTiles.length === 2) {
            this.canFlip = false;
            setTimeout(() => this.checkMatch(), 800);
        }
    }

    checkMatch() {
        const [tile1, tile2] = this.flippedTiles;
        const icon1 = tile1.dataset.icon;
        const icon2 = tile2.dataset.icon;
        
        if (icon1 === icon2) {
            tile1.classList.add('matched');
            tile2.classList.add('matched');
            this.matchedPairs++;
            this.score += 100 * this.level;
            this.playSound('match');
            
            if (this.matchedPairs === this.totalPairs) {
                setTimeout(() => this.levelUp(), 500);
            }
        } else {
            tile1.classList.add('wrong');
            tile2.classList.add('wrong');
            setTimeout(() => {
                tile1.classList.remove('flipped', 'wrong');
                tile2.classList.remove('flipped', 'wrong');
            }, 600);
            this.lives--;
            this.playSound('wrong');
            
            if (this.lives <= 0) {
                setTimeout(() => this.gameOver(false), 600);
                return;
            }
        }
        
        this.flippedTiles = [];
        this.canFlip = true;
        this.updateDisplay();
    }

    levelUp() {
        this.level++;
        this.score += 500;
        this.playSound('levelup');
        this.updateDisplay();
        
        if (this.level > 5) {
            this.gameOver(true);
        } else {
            setTimeout(() => this.initBoard(), 1000);
        }
    }

    gameOver(won) {
        this.canFlip = false;
        this.submitScore();
        
        const modal = document.getElementById('gameOverModal');
        const icon = document.getElementById('modalIcon');
        const title = document.getElementById('modalTitle');
        const message = document.getElementById('modalMessage');
        const scoreDisplay = document.getElementById('modalScore');
        const levelDisplay = document.getElementById('modalLevel');
        
        if (won) {
            icon.innerHTML = '<i class="fas fa-trophy"></i>';
            icon.className = 'modal-icon success';
            title.textContent = 'Champion! 🎉';
            message.textContent = 'You completed all levels!';
        } else {
            icon.innerHTML = '<i class="fas fa-heart-broken"></i>';
            icon.className = 'modal-icon fail';
            title.textContent = 'Game Over';
            message.textContent = 'Better luck next time!';
        }
        
        scoreDisplay.textContent = this.score;
        levelDisplay.textContent = this.level;
        modal.classList.add('active');
    }

    submitScore() {
        document.getElementById('finalScore').value = this.score;
        document.getElementById('finalLevel').value = this.level;
        document.getElementById('scoreForm').submit();
    }

    resetGame() {
        this.score = 0;
        this.level = 1;
        this.lives = 3;
        this.flippedTiles = [];
        this.matchedPairs = 0;
        this.canFlip = false;
        this.startBtn.disabled = false;
        this.gameBoard.innerHTML = '';
        this.updateDisplay();
    }

    updateDisplay() {
        document.getElementById('scoreDisplay').textContent = this.score;
        document.getElementById('levelDisplay').textContent = this.level;
        const hearts = '❤️'.repeat(this.lives) + '🖤'.repeat(3 - this.lives);
        document.getElementById('livesDisplay').textContent = hearts;
    }

    playSound(type) {
        // Audio feedback (optional - can add actual sounds)
        if (type === 'match') console.log('✅ Match!');
        if (type === 'wrong') console.log('❌ Wrong!');
        if (type === 'levelup') console.log('🎉 Level Up!');
    }
}

// Initialize game
const game = new PointsQuest();
</script>
</body>
</html>