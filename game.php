<?php
session_start();
require_once 'config.php';
$auth = new StudentAuth();
if (!$auth->isLoggedIn()) { header('Location: login.php'); exit; }
$student = $auth->getCurrentStudent();
$db = getPadakDB();
$sid = (int)$student['id'];
$activePage = 'game';

// Create game_scores table if it doesn't exist
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
    $level = (int)($_POST['level'] ?? 1);
    
    $db->query("INSERT INTO game_scores (student_id, game_type, score, level_reached) 
                VALUES ($sid, '$game_type', $score, $level)");
}

// Get stats for all games
$gameStats = [];
$gameTypes = ['memory_match', 'reflex_runner', 'word_builder', 'number_crush', 'pattern_master'];
foreach ($gameTypes as $type) {
    $res = $db->query("SELECT MAX(score) as best, COUNT(*) as plays 
                       FROM game_scores 
                       WHERE student_id=$sid AND game_type='$type'");
    if ($res && $row = $res->fetch_assoc()) {
        $gameStats[$type] = ['best' => (int)$row['best'], 'plays' => (int)$row['plays']];
    } else {
        $gameStats[$type] = ['best' => 0, 'plays' => 0];
    }
}

// Get overall leaderboard
function getLeaderboard($db, $gameType, $sid) {
    $leaderboard = [];
    $res = $db->query("SELECT student_id, MAX(score) as best_score 
                       FROM game_scores 
                       WHERE game_type = '$gameType'
                       GROUP BY student_id 
                       ORDER BY best_score DESC LIMIT 5");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $r['display_name'] = ($r['student_id'] == $sid) ? 'You' : 'Student #' . $r['student_id'];
            $r['best_score'] = (int)$r['best_score'];
            $leaderboard[] = $r;
        }
    }
    return $leaderboard;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Game Hub - Padak</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{--sbw:258px;--o5:#f97316;--o4:#fb923c;--o6:#ea580c;--bg:#f8fafc;--card:#fff;--text:#0f172a;--text2:#475569;--text3:#94a3b8;--border:#e2e8f0;--red:#ef4444;--green:#22c55e;--blue:#3b82f6;--yellow:#eab308;--purple:#8b5cf6;--pink:#ec4899;--cyan:#06b6d4;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);overflow-x:hidden;}
.page-wrap{margin-left:var(--sbw);min-height:100vh;}
.topbar{position:sticky;top:0;z-index:100;background:rgba(248,250,252,0.92);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);padding:12px 28px;display:flex;align-items:center;gap:12px;}
.topbar-hamburger{display:none;background:none;border:none;cursor:pointer;color:var(--text2);padding:6px;border-radius:7px;}
.topbar-title{font-size:1rem;font-weight:600;color:var(--text);flex:1;}
.main-content{padding:24px 28px;max-width:1400px;margin:0 auto;}

/* Game Selection View */
.game-hub-header{text-align:center;margin-bottom:30px;}
.game-hub-header h1{font-size:2.2rem;font-weight:800;background:linear-gradient(135deg,var(--o5),var(--o4));-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:8px;}
.game-hub-header p{color:var(--text2);font-size:1rem;}

.games-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;margin-bottom:30px;}
.game-card{background:var(--card);border-radius:16px;border:1px solid var(--border);padding:24px;cursor:pointer;transition:all .3s cubic-bezier(.4,0,.2,1);position:relative;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06);}
.game-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:var(--gradient);}
.game-card:hover{transform:translateY(-4px);box-shadow:0 8px 25px rgba(0,0,0,0.12);border-color:var(--color);}
.game-card.orange{--gradient:linear-gradient(135deg,var(--o5),var(--o4));--color:var(--o5);}
.game-card.blue{--gradient:linear-gradient(135deg,var(--blue),#60a5fa);--color:var(--blue);}
.game-card.green{--gradient:linear-gradient(135deg,var(--green),#4ade80);--color:var(--green);}
.game-card.purple{--gradient:linear-gradient(135deg,var(--purple),#a78bfa);--color:var(--purple);}
.game-card.pink{--gradient:linear-gradient(135deg,var(--pink),#f472b6);--color:var(--pink);}
.game-icon{width:60px;height:60px;border-radius:14px;background:var(--gradient);display:flex;align-items:center;justify-content:center;margin-bottom:16px;box-shadow:0 4px 12px rgba(0,0,0,0.15);}
.game-icon i{font-size:1.8rem;color:#fff;}
.game-title{font-size:1.2rem;font-weight:700;color:var(--text);margin-bottom:6px;}
.game-desc{font-size:.85rem;color:var(--text2);line-height:1.5;margin-bottom:14px;}
.game-stats-row{display:flex;gap:12px;margin-top:14px;padding-top:14px;border-top:1px solid var(--border);}
.game-stat{flex:1;text-align:center;}
.game-stat-label{font-size:.7rem;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;}
.game-stat-value{font-size:1.1rem;font-weight:800;color:var(--color);}

/* Game Play View */
.game-view{display:none;}
.game-view.active{display:block;}
.back-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:var(--card);border:1.5px solid var(--border);border-radius:10px;color:var(--text2);font-size:.85rem;font-weight:600;cursor:pointer;transition:all .2s;margin-bottom:20px;text-decoration:none;}
.back-btn:hover{border-color:var(--o5);color:var(--o5);text-decoration:none;}
.game-container{display:grid;grid-template-columns:1fr 320px;gap:20px;}
.game-main{background:var(--card);border-radius:16px;border:1px solid var(--border);box-shadow:0 4px 15px rgba(0,0,0,0.08);overflow:hidden;}
.game-sidebar{display:flex;flex-direction:column;gap:16px;}
.game-canvas-wrap{padding:24px;display:flex;flex-direction:column;align-items:center;gap:20px;}
.game-stats{display:flex;gap:16px;width:100%;max-width:600px;justify-content:center;flex-wrap:wrap;}
.stat-box{flex:1;min-width:100px;background:linear-gradient(135deg,rgba(249,115,22,0.08),rgba(251,146,60,0.05));border-radius:12px;padding:14px;text-align:center;border:1.5px solid rgba(249,115,22,0.15);}
.stat-label{font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:4px;}
.stat-value{font-size:1.6rem;font-weight:800;color:var(--o5);}

/* Memory Match Game */
.memory-board{width:100%;max-width:600px;aspect-ratio:1;background:linear-gradient(145deg,#f8fafc,#f1f5f9);border-radius:16px;border:2px solid var(--border);display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:20px;box-shadow:inset 0 2px 8px rgba(0,0,0,0.05);}
.memory-tile{background:var(--card);border-radius:12px;border:2px solid var(--border);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:2.5rem;transition:all .3s;box-shadow:0 2px 8px rgba(0,0,0,0.1);position:relative;}
.memory-tile::before{content:'?';position:absolute;font-size:2rem;font-weight:700;color:var(--o5);opacity:.3;}
.memory-tile:hover{transform:translateY(-4px) scale(1.05);box-shadow:0 8px 20px rgba(249,115,22,0.3);border-color:var(--o5);}
.memory-tile.flipped{background:linear-gradient(135deg,var(--o5),var(--o4));border-color:var(--o5);transform:rotateY(180deg) scale(1.05);}
.memory-tile.flipped::before{opacity:0;}
.memory-tile.matched{background:linear-gradient(135deg,var(--green),#16a34a);animation:pulse .5s;}
.memory-tile.matched::before{opacity:0;}
.memory-tile i{opacity:0;transition:opacity .2s;}
.memory-tile.flipped i,.memory-tile.matched i{opacity:1;}

/* Reflex Runner Game */
.runner-game{width:100%;max-width:600px;height:400px;background:linear-gradient(180deg,#dbeafe,#bfdbfe);border-radius:16px;border:2px solid var(--border);position:relative;overflow:hidden;box-shadow:0 4px 15px rgba(0,0,0,0.1);}
.runner-player{width:50px;height:50px;background:linear-gradient(135deg,var(--o5),var(--o4));border-radius:10px;position:absolute;bottom:20px;left:50px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;box-shadow:0 4px 12px rgba(249,115,22,0.4);transition:bottom .2s;}
.runner-player.jumping{bottom:150px;}
.runner-obstacle{width:40px;height:60px;background:linear-gradient(135deg,var(--red),#dc2626);border-radius:8px;position:absolute;bottom:20px;box-shadow:0 4px 12px rgba(239,68,68,0.4);}
.runner-ground{position:absolute;bottom:0;left:0;right:0;height:20px;background:repeating-linear-gradient(90deg,#94a3b8 0px,#94a3b8 20px,#cbd5e1 20px,#cbd5e1 40px);}

/* Word Builder Game */
.word-game{width:100%;max-width:600px;padding:30px;background:var(--card);border-radius:16px;border:2px solid var(--border);}
.word-target{font-size:1.8rem;font-weight:800;text-align:center;color:var(--text);margin-bottom:20px;letter-spacing:8px;padding:20px;background:linear-gradient(135deg,rgba(249,115,22,0.08),rgba(251,146,60,0.05));border-radius:12px;}
.word-input{width:100%;padding:18px;font-size:1.3rem;text-align:center;border:2px solid var(--border);border-radius:12px;font-family:inherit;font-weight:600;margin-bottom:20px;letter-spacing:4px;}
.word-input:focus{outline:none;border-color:var(--o5);box-shadow:0 0 0 4px rgba(249,115,22,0.1);}
.letters-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:10px;}
.letter-btn{padding:16px;font-size:1.2rem;font-weight:700;background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;border:none;border-radius:10px;cursor:pointer;transition:all .2s;box-shadow:0 3px 10px rgba(249,115,22,0.3);}
.letter-btn:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(249,115,22,0.4);}
.letter-btn:disabled{opacity:.3;cursor:not-allowed;}

/* Number Crush Game */
.number-board{width:100%;max-width:600px;aspect-ratio:1;background:linear-gradient(145deg,#f8fafc,#f1f5f9);border-radius:16px;border:2px solid var(--border);display:grid;grid-template-columns:repeat(6,1fr);gap:8px;padding:16px;}
.number-tile{background:var(--card);border-radius:10px;border:2px solid var(--border);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;transition:all .3s;box-shadow:0 2px 6px rgba(0,0,0,0.1);}
.number-tile:hover{transform:scale(1.1);box-shadow:0 6px 15px rgba(0,0,0,0.15);}
.number-tile.selected{background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;border-color:var(--o5);transform:scale(.95);}
.number-tile.matched{background:linear-gradient(135deg,var(--green),#16a34a);color:#fff;animation:popOut .5s forwards;}

/* Pattern Master Game */
.pattern-display{width:100%;max-width:600px;height:120px;background:linear-gradient(135deg,rgba(249,115,22,0.08),rgba(251,146,60,0.05));border-radius:16px;border:2px solid rgba(249,115,22,0.2);display:flex;align-items:center;justify-content:center;gap:12px;padding:20px;margin-bottom:20px;}
.pattern-dot{width:50px;height:50px;border-radius:50%;background:var(--card);border:3px solid var(--border);transition:all .3s;}
.pattern-dot.active{background:linear-gradient(135deg,var(--o5),var(--o4));border-color:var(--o5);transform:scale(1.2);box-shadow:0 4px 15px rgba(249,115,22,0.4);}
.pattern-input{width:100%;max-width:600px;display:grid;grid-template-columns:repeat(4,1fr);gap:12px;}
.pattern-btn{padding:30px;background:var(--card);border:2px solid var(--border);border-radius:12px;font-size:1.5rem;font-weight:700;cursor:pointer;transition:all .2s;}
.pattern-btn:hover{border-color:var(--o5);transform:translateY(-2px);}
.pattern-btn:active{background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;}

/* Controls */
.game-controls{display:flex;gap:12px;width:100%;max-width:600px;flex-wrap:wrap;}
.game-btn{flex:1;min-width:140px;padding:14px 24px;border-radius:10px;font-size:.9rem;font-weight:700;cursor:pointer;border:none;transition:all .3s;display:flex;align-items:center;justify-content:center;gap:8px;text-transform:uppercase;letter-spacing:.5px;}
.game-btn.primary{background:linear-gradient(135deg,var(--o5),var(--o4));color:#fff;box-shadow:0 4px 15px rgba(249,115,22,0.4);}
.game-btn.primary:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(249,115,22,0.5);}
.game-btn.secondary{background:var(--bg);border:2px solid var(--border);color:var(--text2);}
.game-btn.secondary:hover{border-color:var(--o5);color:var(--o5);}
.game-btn:disabled{opacity:.5;cursor:not-allowed;}

/* Sidebar */
.side-card{background:var(--card);border-radius:14px;border:1px solid var(--border);padding:18px;box-shadow:0 2px 8px rgba(0,0,0,0.06);}
.side-card-title{font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:14px;display:flex;align-items:center;gap:8px;}
.side-card-title i{color:var(--o5);}
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
.modal{background:var(--card);border-radius:20px;padding:32px;max-width:400px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.3);border:1px solid var(--border);text-align:center;animation:modalSlideUp .3s;}
.modal-icon{font-size:4rem;margin-bottom:16px;}
.modal-icon.success{color:var(--green);}
.modal-icon.fail{color:var(--red);}
.modal h2{font-size:1.6rem;font-weight:800;margin-bottom:8px;}
.modal p{color:var(--text2);margin-bottom:20px;font-size:.9rem;}
.modal-score{font-size:2.5rem;font-weight:800;background:linear-gradient(135deg,var(--o5),var(--o4));-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin:16px 0;}

@keyframes pulse{0%,100%{transform:scale(1);}50%{transform:scale(1.1);}}
@keyframes popOut{0%{transform:scale(1);}50%{transform:scale(1.2);}100%{transform:scale(0);opacity:0;}}
@keyframes modalSlideUp{from{transform:translateY(30px);opacity:0;}to{transform:translateY(0);opacity:1;}}

@media(max-width:1024px){.game-container{grid-template-columns:1fr;}}
@media(max-width:768px){
    .page-wrap{margin-left:0;}
    .topbar-hamburger{display:flex;}
    .main-content{padding:16px;}
    .games-grid{grid-template-columns:1fr;}
    .memory-board,.number-board{padding:12px;gap:8px;}
    .runner-game{height:300px;}
    .letters-grid{grid-template-columns:repeat(4,1fr);}
    .game-controls{flex-direction:column;}
    .game-btn{min-width:100%;}
}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="page-wrap">
    <div class="topbar">
        <button class="topbar-hamburger" onclick="toggleSidebar()"><i class="fas fa-bars fa-sm"></i></button>
        <div class="topbar-title"><i class="fas fa-gamepad"></i> Game Hub</div>
    </div>
    
    <div class="main-content">
        <!-- Game Selection View -->
        <div id="gameSelection" class="game-selection-view">
            <div class="game-hub-header">
                <h1><i class="fas fa-trophy"></i> Padak Game Hub</h1>
                <p>Take a break and sharpen your skills with fun games!</p>
            </div>

            <div class="games-grid">
                <div class="game-card orange" onclick="loadGame('memory')">
                    <div class="game-icon"><i class="fas fa-brain"></i></div>
                    <div class="game-title">Memory Match</div>
                    <div class="game-desc">Test your memory by matching pairs of icons. Progressive difficulty!</div>
                    <div class="game-stats-row">
                        <div class="game-stat">
                            <div class="game-stat-label">Best Score</div>
                            <div class="game-stat-value"><?php echo $gameStats['memory_match']['best']; ?></div>
                        </div>
                        <div class="game-stat">
                            <div class="game-stat-label">Times Played</div>
                            <div class="game-stat-value"><?php echo $gameStats['memory_match']['plays']; ?></div>
                        </div>
                    </div>
                </div>

                <div class="game-card blue" onclick="loadGame('reflex')">
                    <div class="game-icon"><i class="fas fa-running"></i></div>
                    <div class="game-title">Reflex Runner</div>
                    <div class="game-desc">Jump over obstacles and test your reflexes. How far can you go?</div>
                    <div class="game-stats-row">
                        <div class="game-stat">
                            <div class="game-stat-label">Best Score</div>
                            <div class="game-stat-value"><?php echo $gameStats['reflex_runner']['best']; ?></div>
                        </div>
                        <div class="game-stat">
                            <div class="game-stat-label">Times Played</div>
                            <div class="game-stat-value"><?php echo $gameStats['reflex_runner']['plays']; ?></div>
                        </div>
                    </div>
                </div>

                <div class="game-card green" onclick="loadGame('word')">
                    <div class="game-icon"><i class="fas fa-spell-check"></i></div>
                    <div class="game-title">Word Builder</div>
                    <div class="game-desc">Create words from given letters. Beat the clock and expand your vocabulary!</div>
                    <div class="game-stats-row">
                        <div class="game-stat">
                            <div class="game-stat-label">Best Score</div>
                            <div class="game-stat-value"><?php echo $gameStats['word_builder']['best']; ?></div>
                        </div>
                        <div class="game-stat">
                            <div class="game-stat-label">Times Played</div>
                            <div class="game-stat-value"><?php echo $gameStats['word_builder']['plays']; ?></div>
                        </div>
                    </div>
                </div>

                <div class="game-card purple" onclick="loadGame('number')">
                    <div class="game-icon"><i class="fas fa-calculator"></i></div>
                    <div class="game-title">Number Crush</div>
                    <div class="game-desc">Match numbers that add up to 10. Quick math skills required!</div>
                    <div class="game-stats-row">
                        <div class="game-stat">
                            <div class="game-stat-label">Best Score</div>
                            <div class="game-stat-value"><?php echo $gameStats['number_crush']['best']; ?></div>
                        </div>
                        <div class="game-stat">
                            <div class="game-stat-label">Times Played</div>
                            <div class="game-stat-value"><?php echo $gameStats['number_crush']['plays']; ?></div>
                        </div>
                    </div>
                </div>

                <div class="game-card pink" onclick="loadGame('pattern')">
                    <div class="game-icon"><i class="fas fa-eye"></i></div>
                    <div class="game-title">Pattern Master</div>
                    <div class="game-desc">Memorize and repeat the pattern sequence. Train your focus!</div>
                    <div class="game-stats-row">
                        <div class="game-stat">
                            <div class="game-stat-label">Best Score</div>
                            <div class="game-stat-value"><?php echo $gameStats['pattern_master']['best']; ?></div>
                        </div>
                        <div class="game-stat">
                            <div class="game-stat-label">Times Played</div>
                            <div class="game-stat-value"><?php echo $gameStats['pattern_master']['plays']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Memory Match Game -->
        <div id="memoryGame" class="game-view">
            <button class="back-btn" onclick="backToHub()"><i class="fas fa-arrow-left"></i> Back to Games</button>
            <div class="game-container">
                <div class="game-main">
                    <div class="game-canvas-wrap">
                        <div class="game-stats">
                            <div class="stat-box">
                                <div class="stat-label">Score</div>
                                <div class="stat-value" id="memScore">0</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-label">Level</div>
                                <div class="stat-value" id="memLevel">1</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-label">Lives</div>
                                <div class="stat-value" id="memLives">❤️❤️❤️</div>
                            </div>
                        </div>
                        <div class="memory-board" id="memoryBoard"></div>
                        <div class="game-controls">
                            <button class="game-btn primary" id="memStart"><i class="fas fa-play"></i> Start</button>
                            <button class="game-btn secondary" id="memReset"><i class="fas fa-redo"></i> Reset</button>
                        </div>
                    </div>
                </div>
                <div class="game-sidebar">
                    <div class="side-card">
                        <div class="side-card-title"><i class="fas fa-crown"></i> Top Players</div>
                        <div class="leader-list" id="memLeaderboard"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reflex Runner Game -->
        <div id="reflexGame" class="game-view">
            <button class="back-btn" onclick="backToHub()"><i class="fas fa-arrow-left"></i> Back to Games</button>
            <div class="game-container">
                <div class="game-main">
                    <div class="game-canvas-wrap">
                        <div class="game-stats">
                            <div class="stat-box">
                                <div class="stat-label">Score</div>
                                <div class="stat-value" id="runScore">0</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-label">Speed</div>
                                <div class="stat-value" id="runSpeed">1x</div>
                            </div>
                        </div>
                        <div class="runner-game" id="runnerGame">
                            <div class="runner-player" id="player">🏃</div>
                            <div class="runner-ground"></div>
                        </div>
                        <div class="game-controls">
                            <button class="game-btn primary" id="runStart"><i class="fas fa-play"></i> Start</button>
                            <button class="game-btn secondary" onclick="jumpPlayer()">
                                <i class="fas fa-arrow-up"></i> Jump (Space)
                            </button>
                        </div>
                    </div>
                </div>
                <div class="game-sidebar">
                    <div class="side-card">
                        <div class="side-card-title"><i class="fas fa-crown"></i> Top Runners</div>
                        <div class="leader-list" id="runLeaderboard"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Word Builder Game -->
        <div id="wordGame" class="game-view">
            <button class="back-btn" onclick="backToHub()"><i class="fas fa-arrow-left"></i> Back to Games</button>
            <div class="game-container">
                <div class="game-main">
                    <div class="game-canvas-wrap">
                        <div class="game-stats">
                            <div class="stat-box">
                                <div class="stat-label">Score</div>
                                <div class="stat-value" id="wordScore">0</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-label">Time</div>
                                <div class="stat-value" id="wordTime">60</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-label">Words</div>
                                <div class="stat-value" id="wordCount">0</div>
                            </div>
                        </div>
                        <div class="word-game">
                            <div class="word-target" id="wordTarget">CLICK START</div>
                            <input type="text" class="word-input" id="wordInput" placeholder="Type your word..." disabled>
                            <div class="letters-grid" id="lettersGrid"></div>
                        </div>
                        <div class="game-controls">
                            <button class="game-btn primary" id="wordStart"><i class="fas fa-play"></i> Start</button>
                            <button class="game-btn secondary" id="wordSubmit"><i class="fas fa-check"></i> Submit Word</button>
                        </div>
                    </div>
                </div>
                <div class="game-sidebar">
                    <div class="side-card">
                        <div class="side-card-title"><i class="fas fa-crown"></i> Word Masters</div>
                        <div class="leader-list" id="wordLeaderboard"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Number Crush Game -->
        <div id="numberGame" class="game-view">
            <button class="back-btn" onclick="backToHub()"><i class="fas fa-arrow-left"></i> Back to Games</button>
            <div class="game-container">
                <div class="game-main">
                    <div class="game-canvas-wrap">
                        <div class="game-stats">
                            <div class="stat-box">
                                <div class="stat-label">Score</div>
                                <div class="stat-value" id="numScore">0</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-label">Target</div>
                                <div class="stat-value" id="numTarget">10</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-label">Moves</div>
                                <div class="stat-value" id="numMoves">30</div>
                            </div>
                        </div>
                        <div class="number-board" id="numberBoard"></div>
                        <div class="game-controls">
                            <button class="game-btn primary" id="numStart"><i class="fas fa-play"></i> Start</button>
                            <button class="game-btn secondary" id="numClear"><i class="fas fa-times"></i> Clear Selection</button>
                        </div>
                    </div>
                </div>
                <div class="game-sidebar">
                    <div class="side-card">
                        <div class="side-card-title"><i class="fas fa-crown"></i> Math Wizards</div>
                        <div class="leader-list" id="numLeaderboard"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pattern Master Game -->
        <div id="patternGame" class="game-view">
            <button class="back-btn" onclick="backToHub()"><i class="fas fa-arrow-left"></i> Back to Games</button>
            <div class="game-container">
                <div class="game-main">
                    <div class="game-canvas-wrap">
                        <div class="game-stats">
                            <div class="stat-box">
                                <div class="stat-label">Score</div>
                                <div class="stat-value" id="patScore">0</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-label">Round</div>
                                <div class="stat-value" id="patRound">1</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-label">Length</div>
                                <div class="stat-value" id="patLength">3</div>
                            </div>
                        </div>
                        <div class="pattern-display" id="patternDisplay">
                            <div class="pattern-dot"></div>
                            <div class="pattern-dot"></div>
                            <div class="pattern-dot"></div>
                            <div class="pattern-dot"></div>
                        </div>
                        <div class="pattern-input">
                            <button class="pattern-btn" data-num="1">1</button>
                            <button class="pattern-btn" data-num="2">2</button>
                            <button class="pattern-btn" data-num="3">3</button>
                            <button class="pattern-btn" data-num="4">4</button>
                        </div>
                        <div class="game-controls">
                            <button class="game-btn primary" id="patStart"><i class="fas fa-play"></i> Start</button>
                            <button class="game-btn secondary" id="patWatch"><i class="fas fa-eye"></i> Watch Again</button>
                        </div>
                    </div>
                </div>
                <div class="game-sidebar">
                    <div class="side-card">
                        <div class="side-card-title"><i class="fas fa-crown"></i> Pattern Pros</div>
                        <div class="leader-list" id="patLeaderboard"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="gameModal">
    <div class="modal">
        <div class="modal-icon" id="modalIcon"></div>
        <h2 id="modalTitle"></h2>
        <p id="modalMessage"></p>
        <div class="modal-score" id="modalScore"></div>
        <form method="POST" style="display:none;" id="scoreForm">
            <input type="hidden" name="submit_score" value="1">
            <input type="hidden" name="game_type" id="gameType">
            <input type="hidden" name="score" id="finalScore">
            <input type="hidden" name="level" id="finalLevel">
        </form>
        <button class="game-btn primary" onclick="location.reload()" style="width:100%;">
            <i class="fas fa-play"></i> Play Again
        </button>
    </div>
</div>

<script>
// Navigation
function loadGame(game) {
    document.getElementById('gameSelection').style.display = 'none';
    const games = {
        'memory': 'memoryGame',
        'reflex': 'reflexGame',
        'word': 'wordGame',
        'number': 'numberGame',
        'pattern': 'patternGame'
    };
    document.getElementById(games[game]).classList.add('active');
    
    // Load leaderboard
    const leaderboards = <?php 
        $leaders = [];
        foreach($gameTypes as $type) {
            $leaders[$type] = getLeaderboard($db, $type, $sid);
        }
        echo json_encode($leaders);
    ?>;
    
    const typeMap = {
        'memory': 'memory_match',
        'reflex': 'reflex_runner',
        'word': 'word_builder',
        'number': 'number_crush',
        'pattern': 'pattern_master'
    };
    
    const boardId = game + 'Leaderboard';
    const leaders = leaderboards[typeMap[game]];
    let html = '';
    if (leaders.length === 0) {
        html = '<p style="text-align:center;color:var(--text3);font-size:.8rem;padding:10px 0;">No scores yet. Be first!</p>';
    } else {
        leaders.forEach((l, i) => {
            html += `<div class="leader-item">
                <div class="leader-rank">${i+1}</div>
                <div class="leader-name">${l.display_name}</div>
                <div class="leader-score">${l.best_score}</div>
            </div>`;
        });
    }
    document.getElementById(boardId).innerHTML = html;
}

function backToHub() {
    document.querySelectorAll('.game-view').forEach(v => v.classList.remove('active'));
    document.getElementById('gameSelection').style.display = 'block';
}

function submitScore(gameType, score, level = 1) {
    document.getElementById('gameType').value = gameType;
    document.getElementById('finalScore').value = score;
    document.getElementById('finalLevel').value = level;
    document.getElementById('scoreForm').submit();
}

function showModal(won, score, title = 'Game Over') {
    const modal = document.getElementById('gameModal');
    const icon = document.getElementById('modalIcon');
    const titleEl = document.getElementById('modalTitle');
    const msg = document.getElementById('modalMessage');
    const scoreEl = document.getElementById('modalScore');
    
    if (won) {
        icon.innerHTML = '<i class="fas fa-trophy"></i>';
        icon.className = 'modal-icon success';
        titleEl.textContent = title;
        msg.textContent = 'Congratulations! Amazing performance!';
    } else {
        icon.innerHTML = '<i class="fas fa-heart-broken"></i>';
        icon.className = 'modal-icon fail';
        titleEl.textContent = 'Game Over';
        msg.textContent = 'Better luck next time!';
    }
    
    scoreEl.textContent = score;
    modal.classList.add('active');
}

function jumpPlayer() {
    if (reflexGame && reflexGame.isRunning) {
        reflexGame.jump();
    }
}

// Memory Match Game
class MemoryGame {
    constructor() {
        this.icons = ['💼','📊','✅','🎯','🏆','📝','💡','⭐','🚀','📈','🎓','💻'];
        this.score = 0;
        this.level = 1;
        this.lives = 3;
        this.flippedTiles = [];
        this.matchedPairs = 0;
        this.totalPairs = 0;
        this.canFlip = false;
        
        document.getElementById('memStart').onclick = () => this.start();
        document.getElementById('memReset').onclick = () => this.reset();
    }
    
    start() {
        this.canFlip = true;
        document.getElementById('memStart').disabled = true;
        this.initBoard();
        this.updateDisplay();
    }
    
    initBoard() {
        const board = document.getElementById('memoryBoard');
        board.innerHTML = '';
        const pairsCount = Math.min(8, 4 + this.level);
        this.totalPairs = pairsCount;
        this.matchedPairs = 0;
        
        const selectedIcons = this.icons.slice(0, pairsCount);
        const tiles = [...selectedIcons, ...selectedIcons];
        tiles.sort(() => Math.random() - 0.5);
        
        tiles.forEach((icon) => {
            const tile = document.createElement('div');
            tile.className = 'memory-tile';
            tile.dataset.icon = icon;
            tile.innerHTML = `<i>${icon}</i>`;
            tile.onclick = () => this.flipTile(tile);
            board.appendChild(tile);
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
        
        if (tile1.dataset.icon === tile2.dataset.icon) {
            tile1.classList.add('matched');
            tile2.classList.add('matched');
            this.matchedPairs++;
            this.score += 100 * this.level;
            
            if (this.matchedPairs === this.totalPairs) {
                this.level++;
                this.score += 500;
                if (this.level > 5) {
                    submitScore('memory_match', this.score, this.level);
                    showModal(true, this.score, 'Memory Master!');
                } else {
                    setTimeout(() => this.initBoard(), 1000);
                }
            }
        } else {
            setTimeout(() => {
                tile1.classList.remove('flipped');
                tile2.classList.remove('flipped');
            }, 600);
            this.lives--;
            
            if (this.lives <= 0) {
                submitScore('memory_match', this.score, this.level);
                showModal(false, this.score);
                return;
            }
        }
        
        this.flippedTiles = [];
        this.canFlip = true;
        this.updateDisplay();
    }
    
    updateDisplay() {
        document.getElementById('memScore').textContent = this.score;
        document.getElementById('memLevel').textContent = this.level;
        document.getElementById('memLives').textContent = '❤️'.repeat(this.lives) + '🖤'.repeat(3 - this.lives);
    }
    
    reset() {
        this.score = 0;
        this.level = 1;
        this.lives = 3;
        this.flippedTiles = [];
        this.matchedPairs = 0;
        this.canFlip = false;
        document.getElementById('memStart').disabled = false;
        document.getElementById('memoryBoard').innerHTML = '';
        this.updateDisplay();
    }
}

// Reflex Runner Game
class ReflexGame {
    constructor() {
        this.score = 0;
        this.speed = 5;
        this.gameLoop = null;
        this.obstacles = [];
        this.isRunning = false;
        
        document.getElementById('runStart').onclick = () => this.start();
        document.addEventListener('keydown', (e) => {
            if (e.code === 'Space' && this.isRunning) {
                e.preventDefault();
                this.jump();
            }
        });
    }
    
    start() {
        this.isRunning = true;
        this.score = 0;
        this.speed = 5;
        document.getElementById('runStart').disabled = true;
        this.gameLoop = setInterval(() => this.update(), 20);
    }
    
    jump() {
        const player = document.getElementById('player');
        if (!player.classList.contains('jumping')) {
            player.classList.add('jumping');
            setTimeout(() => player.classList.remove('jumping'), 400);
        }
    }
    
    update() {
        const game = document.getElementById('runnerGame');
        const player = document.getElementById('player');
        
        // Spawn obstacles
        if (Math.random() < 0.02) {
            const obstacle = document.createElement('div');
            obstacle.className = 'runner-obstacle';
            obstacle.style.right = '0px';
            game.appendChild(obstacle);
            this.obstacles.push(obstacle);
        }
        
        // Move obstacles
        this.obstacles.forEach((obs, index) => {
            const right = parseInt(obs.style.right);
            obs.style.right = (right + this.speed) + 'px';
            
            // Check collision
            const obsRect = obs.getBoundingClientRect();
            const playerRect = player.getBoundingClientRect();
            
            if (obsRect.left < playerRect.right && 
                obsRect.right > playerRect.left &&
                obsRect.bottom > playerRect.top) {
                this.gameOver();
            }
            
            // Remove off-screen obstacles
            if (right > 650) {
                obs.remove();
                this.obstacles.splice(index, 1);
                this.score += 10;
                if (this.score % 100 === 0) this.speed += 0.5;
            }
        });
        
        document.getElementById('runScore').textContent = this.score;
        document.getElementById('runSpeed').textContent = (this.speed / 5).toFixed(1) + 'x';
    }
    
    gameOver() {
        this.isRunning = false;
        clearInterval(this.gameLoop);
        this.obstacles.forEach(obs => obs.remove());
        this.obstacles = [];
        document.getElementById('runStart').disabled = false;
        submitScore('reflex_runner', this.score);
        showModal(false, this.score);
    }
}

// Word Builder Game
class WordGame {
    constructor() {
        this.score = 0;
        this.timeLeft = 60;
        this.wordsFound = 0;
        this.timer = null;
        this.currentLetters = [];
        this.usedWords = new Set();
        this.wordList = ['WORK','TASK','TEAM','GOAL','PLAN','CODE','DATA','TEST','MEET','FILE','TIME','LEAD','GROW','BOLD','SMART','FOCUS','BUILD','CREATE','DEVELOP','SUCCESS'];
        
        document.getElementById('wordStart').onclick = () => this.start();
        document.getElementById('wordSubmit').onclick = () => this.submitWord();
        document.getElementById('wordInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.submitWord();
        });
    }
    
    start() {
        this.score = 0;
        this.timeLeft = 60;
        this.wordsFound = 0;
        this.usedWords.clear();
        document.getElementById('wordInput').disabled = false;
        document.getElementById('wordInput').value = '';
        document.getElementById('wordStart').disabled = true;
        
        this.generateLetters();
        this.timer = setInterval(() => {
            this.timeLeft--;
            document.getElementById('wordTime').textContent = this.timeLeft;
            if (this.timeLeft <= 0) this.gameOver();
        }, 1000);
    }
    
    generateLetters() {
        const word = this.wordList[Math.floor(Math.random() * this.wordList.length)];
        this.currentLetters = word.split('').concat(['A','E','I','O','U','R','S','T']);
        this.currentLetters.sort(() => Math.random() - 0.5);
        this.currentLetters = this.currentLetters.slice(0, 12);
        
        document.getElementById('wordTarget').textContent = this.currentLetters.join(' ');
        
        const grid = document.getElementById('lettersGrid');
        grid.innerHTML = '';
        this.currentLetters.forEach(letter => {
            const btn = document.createElement('button');
            btn.className = 'letter-btn';
            btn.textContent = letter;
            btn.onclick = () => {
                const input = document.getElementById('wordInput');
                input.value += letter;
                input.focus();
            };
            grid.appendChild(btn);
        });
    }
    
    submitWord() {
        const input = document.getElementById('wordInput');
        const word = input.value.toUpperCase().trim();
        
        if (word.length < 3) {
            input.value = '';
            return;
        }
        
        // Check if word uses only available letters
        const letters = [...this.currentLetters];
        const valid = word.split('').every(char => {
            const index = letters.indexOf(char);
            if (index > -1) {
                letters.splice(index, 1);
                return true;
            }
            return false;
        });
        
        if (valid && !this.usedWords.has(word)) {
            this.usedWords.add(word);
            this.score += word.length * 10;
            this.wordsFound++;
            this.timeLeft += 3;
            input.value = '';
            input.style.borderColor = 'var(--green)';
            setTimeout(() => input.style.borderColor = '', 300);
        } else {
            input.style.borderColor = 'var(--red)';
            setTimeout(() => input.style.borderColor = '', 300);
        }
        
        document.getElementById('wordScore').textContent = this.score;
        document.getElementById('wordCount').textContent = this.wordsFound;
    }
    
    gameOver() {
        clearInterval(this.timer);
        document.getElementById('wordInput').disabled = true;
        document.getElementById('wordStart').disabled = false;
        submitScore('word_builder', this.score);
        showModal(this.wordsFound > 5, this.score, this.wordsFound > 10 ? 'Word Master!' : 'Good Try!');
    }
}

// Number Crush Game
class NumberGame {
    constructor() {
        this.score = 0;
        this.target = 10;
        this.movesLeft = 30;
        this.selected = [];
        this.numbers = [];
        
        document.getElementById('numStart').onclick = () => this.start();
        document.getElementById('numClear').onclick = () => this.clearSelection();
    }
    
    start() {
        this.score = 0;
        this.movesLeft = 30;
        this.selected = [];
        document.getElementById('numStart').disabled = true;
        this.generateBoard();
        this.updateDisplay();
    }
    
    generateBoard() {
        const board = document.getElementById('numberBoard');
        board.innerHTML = '';
        this.numbers = [];
        
        for (let i = 0; i < 36; i++) {
            const num = Math.floor(Math.random() * 9) + 1;
            this.numbers.push(num);
            
            const tile = document.createElement('div');
            tile.className = 'number-tile';
            tile.textContent = num;
            tile.dataset.index = i;
            tile.onclick = () => this.selectTile(tile, i);
            board.appendChild(tile);
        }
    }
    
    selectTile(tile, index) {
        if (tile.classList.contains('matched')) return;
        
        if (tile.classList.contains('selected')) {
            tile.classList.remove('selected');
            this.selected = this.selected.filter(s => s !== index);
        } else {
            tile.classList.add('selected');
            this.selected.push(index);
            this.checkMatch();
        }
    }
    
    checkMatch() {
        if (this.selected.length >= 2) {
            const sum = this.selected.reduce((acc, i) => acc + this.numbers[i], 0);
            
            if (sum === this.target) {
                this.selected.forEach(i => {
                    const tile = document.querySelector(`[data-index="${i}"]`);
                    tile.classList.remove('selected');
                    tile.classList.add('matched');
                });
                this.score += this.selected.length * 50;
                this.selected = [];
                this.movesLeft--;
                this.updateDisplay();
                
                setTimeout(() => {
                    const matched = document.querySelectorAll('.number-tile.matched');
                    if (matched.length === 36 || this.movesLeft <= 0) {
                        this.gameOver();
                    } else {
                        matched.forEach(t => {
                            const index = parseInt(t.dataset.index);
                            this.numbers[index] = Math.floor(Math.random() * 9) + 1;
                            t.textContent = this.numbers[index];
                            t.classList.remove('matched');
                        });
                    }
                }, 500);
            } else if (this.selected.length > 4) {
                this.clearSelection();
            }
        }
    }
    
    clearSelection() {
        this.selected.forEach(i => {
            const tile = document.querySelector(`[data-index="${i}"]`);
            if (tile) tile.classList.remove('selected');
        });
        this.selected = [];
    }
    
    updateDisplay() {
        document.getElementById('numScore').textContent = this.score;
        document.getElementById('numMoves').textContent = this.movesLeft;
    }
    
    gameOver() {
        document.getElementById('numStart').disabled = false;
        submitScore('number_crush', this.score);
        showModal(this.score > 500, this.score, this.score > 1000 ? 'Math Genius!' : 'Nice Try!');
    }
}

// Pattern Master Game
class PatternGame {
    constructor() {
        this.score = 0;
        this.round = 1;
        this.pattern = [];
        this.playerInput = [];
        this.isPlaying = false;
        this.isWatching = false;
        
        document.getElementById('patStart').onclick = () => this.start();
        document.getElementById('patWatch').onclick = () => this.showPattern();
        
        document.querySelectorAll('.pattern-btn').forEach(btn => {
            btn.onclick = () => {
                if (this.isPlaying && !this.isWatching) {
                    this.playerInput.push(parseInt(btn.dataset.num));
                    this.checkInput();
                }
            };
        });
    }
    
    start() {
        this.score = 0;
        this.round = 1;
        this.pattern = [];
        this.isPlaying = true;
        document.getElementById('patStart').disabled = true;
        this.nextRound();
    }
    
    nextRound() {
        this.pattern.push(Math.floor(Math.random() * 4) + 1);
        this.playerInput = [];
        document.getElementById('patRound').textContent = this.round;
        document.getElementById('patLength').textContent = this.pattern.length;
        this.showPattern();
    }
    
    async showPattern() {
        if (!this.isPlaying) return;
        this.isWatching = true;
        const dots = document.querySelectorAll('.pattern-dot');
        
        for (const num of this.pattern) {
            dots[num - 1].classList.add('active');
            await new Promise(resolve => setTimeout(resolve, 600));
            dots[num - 1].classList.remove('active');
            await new Promise(resolve => setTimeout(resolve, 200));
        }
        
        this.isWatching = false;
    }
    
    checkInput() {
        const index = this.playerInput.length - 1;
        
        if (this.playerInput[index] !== this.pattern[index]) {
            this.gameOver(false);
            return;
        }
        
        if (this.playerInput.length === this.pattern.length) {
            this.score += this.round * 100;
            document.getElementById('patScore').textContent = this.score;
            this.round++;
            
            if (this.round > 10) {
                this.gameOver(true);
            } else {
                setTimeout(() => this.nextRound(), 1000);
            }
        }
    }
    
    gameOver(won) {
        this.isPlaying = false;
        document.getElementById('patStart').disabled = false;
        submitScore('pattern_master', this.score, this.round);
        showModal(won, this.score, won ? 'Pattern Master!' : 'Game Over');
    }
}

// Initialize games
const memoryGame = new MemoryGame();
const reflexGame = new ReflexGame();
const wordGame = new WordGame();
const numberGame = new NumberGame();
const patternGame = new PatternGame();
</script>
</body>
</html>