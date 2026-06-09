<?php
/**
 * trainer.php v1.0 – ML Training Center – Grid Bot v12.7
 * Entrena ml_weights_v2.json directamente desde el navegador.
 * – Ejecuta train_ml_weights.py en background con output en vivo (SSE)
 * – Visualiza pesos actuales como heatmap
 * – Historial de entrenamientos (log local)
 */
error_reporting(0); ini_set('display_errors', '0');
define('PUBHTML',  __DIR__);
define('WEIGHTS',  PUBHTML . '/ml_weights_v2.json');
define('TRAIN_PY', PUBHTML . '/train_ml_weights.py');
define('TRAIN_LOG', PUBHTML . '/trainer_history.json');
define('PID_FILE', PUBHTML . '/trainer.pid');
define('OUT_FILE', PUBHTML . '/trainer_out.txt');
define('EXPORT_TOKEN', 'g273f123');

/* ── Security token ── */
$token = $_GET['token'] ?? $_POST['token'] ?? '';
if ($token !== EXPORT_TOKEN && !empty($token)) {
    http_response_code(403); exit('Acceso denegado');
}

/* ── SSE stream (GET ?stream=1) ── */
if (isset($_GET['stream'])) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    set_time_limit(300);
    $offset = 0;
    $maxWait = 270;
    $start = time();
    while (time() - $start < $maxWait) {
        if (file_exists(OUT_FILE)) {
            $content = file_get_contents(OUT_FILE);
            if (strlen($content) > $offset) {
                $chunk = substr($content, $offset);
                $offset = strlen($content);
                $lines = explode("\n", $chunk);
                foreach ($lines as $line) {
                    $line = rtrim($line, "\r");
                    if ($line !== '') {
                        echo "data: " . json_encode(['line' => $line]) . "\n\n";
                    }
                }
                ob_flush(); flush();
            }
        }
        // Check if done
        if (file_exists(PID_FILE)) {
            $pid = trim(file_get_contents(PID_FILE));
            if ($pid && !file_exists("/proc/$pid")) {
                // Process finished
                echo "data: " . json_encode(['done' => true, 'weights' => loadWeights()]) . "\n\n";
                ob_flush(); flush();
                @unlink(PID_FILE);
                break;
            }
        } elseif ($offset > 0) {
            echo "data: " . json_encode(['done' => true, 'weights' => loadWeights()]) . "\n\n";
            ob_flush(); flush();
            break;
        }
        usleep(400000);
    }
    exit;
}

/* ── POST: start training ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'start') {
        // Already running?
        if (file_exists(PID_FILE)) {
            $pid = trim(file_get_contents(PID_FILE));
            if ($pid && file_exists("/proc/$pid")) {
                echo json_encode(['ok' => false, 'msg' => 'Ya hay un entrenamiento en curso (PID '.$pid.')']);
                exit;
            }
        }
        if (!file_exists(TRAIN_PY)) {
            echo json_encode(['ok' => false, 'msg' => 'train_ml_weights.py no encontrado en ' . PUBHTML]);
            exit;
        }
        // Check python3
        exec('which python3 2>/dev/null', $py_out);
        $py3 = $py_out[0] ?? 'python3';

        // Read params from POST
        $symbol   = preg_replace('/[^A-Z0-9]/', '', strtoupper($_POST['symbol'] ?? 'ETHUSDT'));
        $horizon  = max(1, min(24, (int)($_POST['horizon'] ?? 8)));
        $up_thr   = max(0.05, min(2.0, (float)($_POST['up_thr'] ?? 0.25)));
        $down_thr = -abs(max(0.05, min(2.0, (float)($_POST['down_thr'] ?? 0.25))));
        $candles  = max(2000, min(20000, (int)($_POST['candles'] ?? 12000)));
        $c_reg    = max(0.01, min(5.0, (float)($_POST['c_reg'] ?? 0.3)));

        // Generate temp trainer with overridden params
        $src = file_get_contents(TRAIN_PY);
        $src = preg_replace('/^SYMBOL\s*=.*/m',  "SYMBOL       = '$symbol'", $src);
        $src = preg_replace('/^HORIZON\s*=.*/m', "HORIZON      = $horizon", $src);
        $src = preg_replace('/^UP_THR\s*=.*/m',  "UP_THR       =  $up_thr", $src);
        $src = preg_replace('/^DOWN_THR\s*=.*/m',"DOWN_THR     = $down_thr", $src);
        $src = preg_replace('/^MAX_CANDLES\s*=.*/m',"MAX_CANDLES  = $candles", $src);
        // Fix C param
        $src = preg_replace('/C=[\d.]+,/', "C=$c_reg,", $src);
        // Force output to PUBHTML
        $out_path = WEIGHTS;
        $src = preg_replace('/^OUTPUT\s*=.*/m', "OUTPUT       = '$out_path'", $src);

        $tmp_py = PUBHTML . '/trainer_run.py';
        file_put_contents($tmp_py, $src);
        @unlink(OUT_FILE);

        $cmd = "cd " . escapeshellarg(PUBHTML) . " && $py3 " . escapeshellarg($tmp_py) . " > " . escapeshellarg(OUT_FILE) . " 2>&1 & echo $!";
        $pid = trim(shell_exec($cmd));
        if ($pid && is_numeric($pid)) {
            file_put_contents(PID_FILE, $pid);
            // Log
            $hist = file_exists(TRAIN_LOG) ? json_decode(file_get_contents(TRAIN_LOG), true) : [];
            if (!is_array($hist)) $hist = [];
            array_unshift($hist, ['ts' => date('Y-m-d H:i:s'), 'pid' => (int)$pid,
                'params' => compact('symbol','horizon','up_thr','down_thr','candles','c_reg'), 'status' => 'running']);
            file_put_contents(TRAIN_LOG, json_encode(array_slice($hist, 0, 20)));
            echo json_encode(['ok' => true, 'pid' => (int)$pid]);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo iniciar el proceso']);
        }
        exit;
    }

    if ($action === 'stop') {
        if (file_exists(PID_FILE)) {
            $pid = trim(file_get_contents(PID_FILE));
            if ($pid && is_numeric($pid)) {
                exec("kill -TERM $pid 2>/dev/null");
                @unlink(PID_FILE);
            }
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'status') {
        $running = false;
        if (file_exists(PID_FILE)) {
            $pid = trim(file_get_contents(PID_FILE));
            $running = $pid && file_exists("/proc/$pid");
        }
        echo json_encode(['ok' => true, 'running' => $running, 'weights' => loadWeights()]);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Acción desconocida']);
    exit;
}

/* ── Helpers ── */
function loadWeights(): ?array {
    if (!file_exists(WEIGHTS)) return null;
    $d = json_decode(file_get_contents(WEIGHTS), true);
    return is_array($d) ? $d : null;
}
function loadHistory(): array {
    if (!file_exists(TRAIN_LOG)) return [];
    $d = json_decode(file_get_contents(TRAIN_LOG), true);
    return is_array($d) ? $d : [];
}
$weights = loadWeights();
$history = loadHistory();
$isRunning = file_exists(PID_FILE) && file_exists("/proc/" . trim(@file_get_contents(PID_FILE)));
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ML Trainer · Grid Bot v12.7</title>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#06080e;--bg2:#0b0f18;--bg3:#10151f;--bg4:#141b26;--border:#1a2535;--border2:#243448;--text:#c8daf0;--muted:#3a5270;--dim:#7a99bb;--accent:#2d8cff;--green:#00c97a;--red:#f03c52;--yellow:#f5a623;--purple:#9b72f5;--mono:'JetBrains Mono',monospace;--sans:'Inter',system-ui,sans-serif;--r:10px;--r2:6px}
*{box-sizing:border-box;margin:0;padding:0}html,body{min-height:100%;background:var(--bg);color:var(--text);font-family:var(--sans);font-size:13px}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:var(--border2);border-radius:4px}

/* layout */
.wrap{max-width:1200px;margin:0 auto;padding:20px}
.grid{display:grid;grid-template-columns:360px 1fr;gap:16px;align-items:start}
.grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}

/* topbar */
.topbar{background:rgba(11,15,24,.97);backdrop-filter:blur(20px);border-bottom:1px solid var(--border);padding:0 20px;height:50px;display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:100;margin-bottom:20px}
.brand{display:flex;align-items:center;gap:9px}
.brand-icon{width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,var(--accent),var(--purple));display:grid;place-items:center;font-size:14px}
.brand-name{font-size:13px;font-weight:700;color:#fff}
.brand-sub{font-size:8px;color:var(--muted);margin-top:1px}
.tb-right{margin-left:auto;display:flex;align-items:center;gap:10px}
.a-link{font-size:11px;color:var(--dim);text-decoration:none;padding:4px 9px;border-radius:6px;border:1px solid var(--border);transition:.15s}
.a-link:hover{border-color:var(--border2);color:var(--text)}

/* card */
.card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r);overflow:hidden}
.card-hd{padding:10px 14px;background:var(--bg3);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.card-hd h3{font-size:11px;font-weight:700;color:var(--dim);text-transform:uppercase;letter-spacing:.7px}
.card-bd{padding:14px}

/* form */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px}
.field{display:flex;flex-direction:column;gap:5px}
.field label{font-size:9px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.6px}
.field input,.field select{background:var(--bg3);border:1px solid var(--border2);border-radius:var(--r2);padding:7px 10px;color:var(--text);font-family:var(--mono);font-size:12px;outline:none;transition:.15s;width:100%}
.field input:focus,.field select:focus{border-color:var(--accent);box-shadow:0 0 0 2px rgba(45,140,255,.1)}
.field-hint{font-size:8px;color:var(--muted);margin-top:2px}
.field-full{grid-column:1/-1}

/* buttons */
.btn{border:1px solid var(--border2);background:transparent;color:var(--dim);font-family:var(--sans);font-size:11px;font-weight:700;padding:6px 14px;border-radius:var(--r2);cursor:pointer;transition:.15s;display:inline-flex;align-items:center;gap:6px;letter-spacing:.1px}
.btn:hover{background:var(--bg3)}
.btn-primary{background:var(--accent);border-color:var(--accent);color:#fff;font-size:12px;padding:9px 18px;border-radius:var(--r2);width:100%;justify-content:center;transition:.15s}
.btn-primary:hover{background:#1a6fdd}
.btn-primary:disabled{background:var(--bg3);border-color:var(--border);color:var(--muted);cursor:not-allowed}
.btn-stop{background:var(--red);border-color:var(--red);color:#fff;font-size:12px;padding:9px 18px;border-radius:var(--r2);width:100%;justify-content:center;transition:.15s}
.btn-stop:hover{background:#c8253b}
.btn-sm{font-size:10px;padding:3px 8px}

/* terminal */
.terminal{background:#000;border-radius:var(--r2);overflow:hidden;border:1px solid #0d1520}
.term-hd{background:#0a0e18;padding:8px 12px;display:flex;align-items:center;gap:6px;border-bottom:1px solid #0d1520}
.term-dot{width:8px;height:8px;border-radius:50%}
.term-title{font-family:var(--mono);font-size:9px;color:#3a5270;margin-left:4px}
.term-body{padding:10px 12px;height:320px;overflow-y:auto;font-family:var(--mono);font-size:10px;line-height:1.8;color:#7a99bb}
.term-body::-webkit-scrollbar{width:3px}::-webkit-scrollbar-thumb{background:#1a2535}
.t-ok{color:#00c97a}.t-err{color:#f03c52}.t-warn{color:#f5a623}.t-info{color:#2d8cff}.t-dim{color:#3a5270}.t-data{color:#9b72f5}
.cursor{display:inline-block;width:8px;height:13px;background:#2d8cff;vertical-align:middle;animation:blink .8s infinite}
@keyframes blink{0%,49%{opacity:1}50%,100%{opacity:0}}

/* metrics */
.metric-row{display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid rgba(26,37,53,.5)}
.metric-row:last-child{border-bottom:none}
.metric-label{font-size:9px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px}
.metric-val{font-family:var(--mono);font-size:13px;font-weight:600}
.c-green{color:var(--green)}.c-red{color:var(--red)}.c-blue{color:var(--accent)}.c-yellow{color:var(--yellow)}.c-purple{color:var(--purple)}

/* heatmap */
.heatmap{width:100%;border-collapse:collapse;font-family:var(--mono);font-size:9px}
.heatmap th{background:var(--bg3);color:var(--muted);padding:5px 8px;text-align:center;font-family:var(--sans);font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border:1px solid var(--border)}
.heatmap td{padding:6px 10px;text-align:center;border:1px solid var(--border);transition:background .3s;font-weight:600}
.hm-feat{color:var(--dim);font-weight:700;text-align:left!important;background:var(--bg3)}

/* history */
.hist-row{display:flex;align-items:center;gap:10px;padding:8px 14px;border-bottom:1px solid rgba(26,37,53,.5);font-size:10px}
.hist-row:last-child{border-bottom:none}
.hist-ts{font-family:var(--mono);font-size:9px;color:var(--muted);min-width:130px}
.hist-tag{font-size:8px;padding:1px 5px;border-radius:3px;font-weight:700;text-transform:uppercase}
.tag-run{background:rgba(45,140,255,.15);color:var(--accent)}.tag-done{background:rgba(0,201,122,.15);color:var(--green)}

/* progress */
.prog-wrap{margin:10px 0}
.prog-track{height:4px;background:var(--border);border-radius:4px;overflow:hidden}
.prog-fill{height:100%;background:linear-gradient(90deg,var(--accent),var(--purple));border-radius:4px;transition:width .4s;animation:prog-shimmer 1.5s infinite}
@keyframes prog-shimmer{0%{opacity:.7}50%{opacity:1}100%{opacity:.7}}

/* badge */
.badge{padding:2px 7px;border-radius:4px;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
.b-run{background:rgba(245,166,35,.15);color:var(--yellow)}.b-ready{background:rgba(0,201,122,.15);color:var(--green)}.b-none{background:rgba(58,82,112,.2);color:var(--muted)}

/* preset buttons */
.presets{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px}
.preset-btn{font-family:var(--mono);font-size:9px;padding:3px 9px;border-radius:4px;border:1px solid var(--border2);background:transparent;color:var(--dim);cursor:pointer;transition:.15s}
.preset-btn:hover{border-color:var(--accent);color:var(--accent);background:rgba(45,140,255,.05)}
.preset-btn.active{border-color:var(--accent);color:var(--accent);background:rgba(45,140,255,.1)}

/* section title */
.sec-title{font-size:9px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;display:flex;align-items:center;gap:6px}
.sec-title::after{content:'';flex:1;height:1px;background:var(--border)}
</style>
</head>
<body>

<div class="topbar">
  <div class="brand">
    <div class="brand-icon">🧠</div>
    <div>
      <div class="brand-name">ML Training Center</div>
      <div class="brand-sub">Grid Bot v12.7 · ETH/USDT</div>
    </div>
  </div>
  <div class="tb-right">
    <span id="statusBadge" class="badge <?= $isRunning ? 'b-run' : ($weights ? 'b-ready' : 'b-none') ?>">
      <?= $isRunning ? '⏳ Entrenando' : ($weights ? '✓ Listo' : 'Sin pesos') ?>
    </span>
    <a class="a-link" href="index.php">← Dashboard</a>
  </div>
</div>

<div class="wrap">
<div class="grid">

<!-- ══ LEFT: FORMULARIO ══ -->
<div style="display:flex;flex-direction:column;gap:14px">

  <!-- Presets -->
  <div class="card">
    <div class="card-hd"><h3>⚡ Presets</h3></div>
    <div class="card-bd">
      <div class="presets">
        <button class="preset-btn active" onclick="applyPreset('balanced')">Balanceado</button>
        <button class="preset-btn" onclick="applyPreset('aggressive')">Agresivo</button>
        <button class="preset-btn" onclick="applyPreset('conservative')">Conservador</button>
        <button class="preset-btn" onclick="applyPreset('scalping')">Scalping</button>
        <button class="preset-btn" onclick="applyPreset('deep')">Profundo</button>
      </div>

      <!-- Parámetros -->
      <div class="sec-title">Símbolo & Dataset</div>
      <div class="form-row">
        <div class="field">
          <label>Símbolo</label>
          <select id="symbol" name="symbol">
            <option value="ETHUSDT" selected>ETHUSDT</option>
            <option value="BTCUSDT">BTCUSDT</option>
            <option value="SOLUSDT">SOLUSDT</option>
            <option value="XRPUSDT">XRPUSDT</option>
          </select>
        </div>
        <div class="field">
          <label>Velas históricas</label>
          <input type="number" id="candles" value="12000" min="2000" max="20000" step="1000">
          <span class="field-hint">≈ <span id="daysHint">41</span> días de 5m</span>
        </div>
      </div>

      <div class="sec-title" style="margin-top:8px">Clasificación</div>
      <div class="form-row">
        <div class="field">
          <label>Horizonte (velas)</label>
          <input type="number" id="horizon" value="8" min="1" max="24">
          <span class="field-hint"><span id="minHint">40</span> min al futuro</span>
        </div>
        <div class="field">
          <label>Umbral UP %</label>
          <input type="number" id="up_thr" value="0.25" min="0.05" max="2" step="0.05">
        </div>
      </div>
      <div class="form-row">
        <div class="field">
          <label>Umbral DOWN %</label>
          <input type="number" id="down_thr" value="0.25" min="0.05" max="2" step="0.05">
        </div>
        <div class="field">
          <label>Reg. C (Logistic)</label>
          <input type="number" id="c_reg" value="0.3" min="0.01" max="5" step="0.05">
          <span class="field-hint">Menor C = + regularización</span>
        </div>
      </div>

      <div style="margin-top:14px">
        <button id="trainBtn" class="btn-primary" onclick="startTraining()">
          🚀 Iniciar Entrenamiento
        </button>
        <button id="stopBtn" class="btn-stop" onclick="stopTraining()" style="display:none;margin-top:6px">
          ⏹ Detener
        </button>
      </div>
      <div id="progWrap" class="prog-wrap" style="display:none">
        <div class="prog-track"><div class="prog-fill" id="progFill" style="width:30%"></div></div>
        <div style="font-size:9px;color:var(--muted);margin-top:4px;text-align:center" id="progTxt">Descargando velas…</div>
      </div>
    </div>
  </div>

  <!-- Métricas actuales -->
  <div class="card">
    <div class="card-hd">
      <h3>📊 Pesos Actuales</h3>
      <?php if ($weights): ?>
        <span style="font-family:var(--mono);font-size:9px;color:var(--muted)"><?= htmlspecialchars($weights['updated_at'] ?? '--') ?></span>
      <?php endif; ?>
    </div>
    <div class="card-bd" id="metricsBox">
      <?php if ($weights): ?>
      <div class="metric-row">
        <span class="metric-label">Símbolo</span>
        <span class="metric-val c-blue"><?= htmlspecialchars($weights['symbol'] ?? '--') ?></span>
      </div>
      <div class="metric-row">
        <span class="metric-label">Accuracy (OOS)</span>
        <span class="metric-val <?= (($weights['acc']??0) >= 0.55) ? 'c-green' : 'c-yellow' ?>">
          <?= number_format(($weights['acc'] ?? 0) * 100, 2) ?>%
        </span>
      </div>
      <div class="metric-row">
        <span class="metric-label">Clases</span>
        <span class="metric-val" style="font-size:10px"><?= implode(' · ', $weights['classes'] ?? []) ?></span>
      </div>
      <div class="metric-row">
        <span class="metric-label">Scaler center (RSI)</span>
        <span class="metric-val c-purple"><?= number_format($weights['scaler_mean'][0] ?? 0, 2) ?></span>
      </div>
      <?php else: ?>
      <div style="text-align:center;padding:20px;color:var(--muted);font-size:11px">
        Sin pesos entrenados.<br>Ejecutá el entrenamiento para generar <code>ml_weights_v2.json</code>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Historial -->
  <div class="card">
    <div class="card-hd"><h3>📋 Historial</h3></div>
    <?php if ($history): ?>
      <?php foreach (array_slice($history, 0, 5) as $h): ?>
      <div class="hist-row">
        <span class="hist-ts"><?= htmlspecialchars($h['ts']) ?></span>
        <span class="hist-tag <?= $h['status']==='running'?'tag-run':'tag-done' ?>"><?= $h['status'] ?></span>
        <span style="color:var(--dim);font-family:var(--mono);font-size:9px"><?= htmlspecialchars($h['params']['symbol']??'') ?> H:<?= $h['params']['horizon']??'' ?></span>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="padding:14px;text-align:center;color:var(--muted);font-size:10px">Sin historial aún</div>
    <?php endif; ?>
  </div>

</div><!-- /left -->

<!-- ══ RIGHT: TERMINAL + HEATMAP ══ -->
<div style="display:flex;flex-direction:column;gap:14px">

  <!-- Terminal -->
  <div class="card">
    <div class="card-hd">
      <h3>🖥️ Terminal</h3>
      <div style="display:flex;gap:6px">
        <button class="btn btn-sm" onclick="clearTerm()">Limpiar</button>
        <button class="btn btn-sm" onclick="toggleAutoScroll()">Auto-scroll</button>
      </div>
    </div>
    <div class="terminal">
      <div class="term-hd">
        <span class="term-dot" style="background:#f03c52"></span>
        <span class="term-dot" style="background:#f5a623"></span>
        <span class="term-dot" style="background:#00c97a"></span>
        <span class="term-title">train_ml_weights.py · ETH/USDT · Bybit Testnet</span>
      </div>
      <div class="term-body" id="term">
        <div class="t-dim">╔══════════════════════════════════════╗</div>
        <div class="t-dim">║   ML Trainer v1.0 · Grid Bot v12.7   ║</div>
        <div class="t-dim">╚══════════════════════════════════════╝</div>
        <div class="t-dim">Esperando inicio de entrenamiento…</div>
        <div><span class="cursor"></span></div>
      </div>
    </div>
  </div>

  <!-- Heatmap de pesos -->
  <div class="card" id="heatmapCard">
    <div class="card-hd">
      <h3>🔥 Heatmap de Pesos</h3>
      <span style="font-size:8px;color:var(--muted)">Contribución por feature y clase</span>
    </div>
    <div class="card-bd">
      <?php if ($weights && isset($weights['weights'])): ?>
        <div id="heatmapWrap">
          <?php renderHeatmap($weights) ?>
        </div>
        <div style="margin-top:12px">
          <div class="sec-title">Vectores Scaler</div>
          <div class="grid-2" style="gap:8px;margin-top:6px">
            <div>
              <div style="font-size:8px;color:var(--muted);margin-bottom:4px">Center (Mediana)</div>
              <?php foreach (($weights['scaler_mean'] ?? []) as $i => $v): ?>
                <div style="display:flex;justify-content:space-between;padding:2px 0;font-family:var(--mono);font-size:9px">
                  <span style="color:var(--muted)"><?= ['RSI','EMA_diff','vol_norm','MACD'][$i] ?? "feat$i" ?></span>
                  <span class="c-blue"><?= number_format($v, 6) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
            <div>
              <div style="font-size:8px;color:var(--muted);margin-bottom:4px">Scale (IQR)</div>
              <?php foreach (($weights['scaler_scale'] ?? []) as $i => $v): ?>
                <div style="display:flex;justify-content:space-between;padding:2px 0;font-family:var(--mono);font-size:9px">
                  <span style="color:var(--muted)"><?= ['RSI','EMA_diff','vol_norm','MACD'][$i] ?? "feat$i" ?></span>
                  <span class="c-purple"><?= number_format($v, 6) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div style="text-align:center;padding:30px;color:var(--muted)">
          Heatmap disponible tras el primer entrenamiento
        </div>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /right -->
</div><!-- /grid -->
</div><!-- /wrap -->

<script>
const PRESETS = {
  balanced:     {symbol:'ETHUSDT', candles:12000, horizon:8,  up_thr:0.25, down_thr:0.25, c_reg:0.3},
  aggressive:   {symbol:'ETHUSDT', candles:10000, horizon:6,  up_thr:0.15, down_thr:0.15, c_reg:0.5},
  conservative: {symbol:'ETHUSDT', candles:15000, horizon:12, up_thr:0.40, down_thr:0.40, c_reg:0.1},
  scalping:     {symbol:'ETHUSDT', candles:8000,  horizon:4,  up_thr:0.10, down_thr:0.10, c_reg:0.8},
  deep:         {symbol:'ETHUSDT', candles:20000, horizon:16, up_thr:0.30, down_thr:0.30, c_reg:0.2},
};
let autoScroll = true, es = null;

function applyPreset(name) {
  const p = PRESETS[name];
  if (!p) return;
  document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
  event.target.classList.add('active');
  Object.keys(p).forEach(k => { const el = document.getElementById(k); if(el) el.value = p[k]; });
  updateHints();
}

function updateHints() {
  const c = parseInt(document.getElementById('candles').value) || 12000;
  const h = parseInt(document.getElementById('horizon').value) || 8;
  const days = Math.round(c / 288);
  const mins = h * 5;
  const dh = document.getElementById('daysHint');
  const mh = document.getElementById('minHint');
  if (dh) dh.textContent = days;
  if (mh) mh.textContent = mins;
}
['candles','horizon'].forEach(id => {
  const el = document.getElementById(id);
  if (el) el.addEventListener('input', updateHints);
});

// ── TERMINAL ──────────────────────────────────────
const term = document.getElementById('term');
function termLine(text, cls='') {
  // Remove last cursor line
  const last = term.lastElementChild;
  if (last && last.querySelector('.cursor')) last.remove();

  const div = document.createElement('div');
  const tclass = detectClass(text);
  div.className = cls || tclass;
  div.textContent = text;
  term.appendChild(div);

  // Re-add cursor
  const cur = document.createElement('div');
  cur.innerHTML = '<span class="cursor"></span>';
  term.appendChild(cur);

  if (autoScroll) term.scrollTop = term.scrollHeight;
}
function detectClass(text) {
  if (text.includes('✅') || text.includes('OK') || text.includes('Precisión')) return 't-ok';
  if (text.includes('❌') || text.includes('Error') || text.includes('error')) return 't-err';
  if (text.includes('⚠') || text.includes('WARN') || text.includes('Distribución')) return 't-warn';
  if (text.includes('📥') || text.includes('📈') || text.includes('📊')) return 't-info';
  if (/^\s*(DOWN|SIDEWAYS|UP|macro|weighted|accuracy)/.test(text)) return 't-data';
  return '';
}
function clearTerm() {
  term.innerHTML = '<div class="t-dim">Terminal limpiada.</div><div><span class="cursor"></span></div>';
}
function toggleAutoScroll() {
  autoScroll = !autoScroll;
  termLine(`Auto-scroll: ${autoScroll ? 'ON' : 'OFF'}`, 't-dim');
}

// ── PROGRESS PHASES ───────────────────────────────
const phases = [
  [0, 'Descargando velas de Bybit…'],
  [20, 'Calculando indicadores (RSI, MACD, EMA…)'],
  [35, 'Aplicando SMOTE (balanceo de clases)…'],
  [50, 'Escalado RobustScaler…'],
  [60, 'Entrenando regresión logística multinomial…'],
  [80, 'Evaluando OOS (out-of-sample)…'],
  [90, 'Exportando ml_weights_v2.json…'],
];
let phaseIdx = 0, phaseTimer = null;
function startPhases() {
  phaseIdx = 0;
  document.getElementById('progFill').style.width = '5%';
  document.getElementById('progTxt').textContent = phases[0][1];
  phaseTimer = setInterval(() => {
    if (phaseIdx < phases.length - 1) {
      phaseIdx++;
      document.getElementById('progFill').style.width = phases[phaseIdx][0] + '%';
      document.getElementById('progTxt').textContent = phases[phaseIdx][1];
    }
  }, 14000);
}
function stopPhases(success) {
  clearInterval(phaseTimer);
  document.getElementById('progFill').style.width = success ? '100%' : '0%';
  document.getElementById('progTxt').textContent = success ? '✅ Completado' : '❌ Error';
}

// ── TRAINING FLOW ─────────────────────────────────
async function startTraining() {
  const trainBtn = document.getElementById('trainBtn');
  const stopBtn  = document.getElementById('stopBtn');
  const progWrap = document.getElementById('progWrap');

  const params = {
    action: 'start',
    symbol: document.getElementById('symbol').value,
    candles: document.getElementById('candles').value,
    horizon: document.getElementById('horizon').value,
    up_thr: document.getElementById('up_thr').value,
    down_thr: document.getElementById('down_thr').value,
    c_reg: document.getElementById('c_reg').value,
  };

  termLine('', 't-dim');
  termLine('════════════════════════════════════════', 't-dim');
  termLine(`🚀 Iniciando entrenamiento: ${params.symbol} | H:${params.horizon} velas | ${params.candles} datos`, 't-info');
  termLine(`   Thresholds: UP>${params.up_thr}% / DOWN<-${params.down_thr}% | C=${params.c_reg}`, 't-dim');

  const fd = new FormData();
  Object.entries(params).forEach(([k,v]) => fd.append(k, v));
  const resp = await fetch('', {method:'POST', body: fd}).then(r => r.json()).catch(() => null);

  if (!resp || !resp.ok) {
    termLine('❌ ' + (resp?.msg || 'Error iniciando entrenamiento'), 't-err');
    return;
  }

  termLine(`📋 PID: ${resp.pid}`, 't-dim');
  trainBtn.disabled = true;
  stopBtn.style.display = 'block';
  progWrap.style.display = 'block';
  document.getElementById('statusBadge').textContent = '⏳ Entrenando';
  document.getElementById('statusBadge').className = 'badge b-run';
  startPhases();

  // SSE stream
  if (es) es.close();
  es = new EventSource(`?stream=1`);
  es.onmessage = (e) => {
    const d = JSON.parse(e.data);
    if (d.line !== undefined) {
      termLine(d.line);
    }
    if (d.done) {
      es.close(); es = null;
      stopPhases(true);
      trainBtn.disabled = false;
      stopBtn.style.display = 'none';
      document.getElementById('statusBadge').textContent = '✓ Listo';
      document.getElementById('statusBadge').className = 'badge b-ready';
      termLine('════════════════════════════════════════', 't-dim');
      termLine('✅ Entrenamiento completado. Pesos actualizados.', 't-ok');
      if (d.weights) updateHeatmap(d.weights);
    }
  };
  es.onerror = () => {
    if (es) { es.close(); es = null; }
    stopPhases(false);
    trainBtn.disabled = false;
    stopBtn.style.display = 'none';
    termLine('⚠ Stream SSE cerrado (el entrenamiento puede seguir en server)', 't-warn');
    // Fallback: poll for completion
    pollCompletion();
  };
}

async function stopTraining() {
  if (!confirm('¿Detener entrenamiento en curso?')) return;
  await fetch('', {method:'POST', body: new URLSearchParams({action:'stop'})});
  if (es) { es.close(); es = null; }
  stopPhases(false);
  document.getElementById('trainBtn').disabled = false;
  document.getElementById('stopBtn').style.display = 'none';
  termLine('⏹ Entrenamiento detenido por el usuario.', 't-warn');
}

async function pollCompletion() {
  for (let i = 0; i < 30; i++) {
    await new Promise(r => setTimeout(r, 5000));
    const d = await fetch('', {method:'POST', body: new URLSearchParams({action:'status'})}).then(r=>r.json()).catch(()=>null);
    if (!d) break;
    if (!d.running) {
      stopPhases(true);
      document.getElementById('trainBtn').disabled = false;
      document.getElementById('stopBtn').style.display = 'none';
      if (d.weights) updateHeatmap(d.weights);
      termLine('✅ Entrenamiento completado (detectado via poll).', 't-ok');
      break;
    }
  }
}

// ── HEATMAP UPDATE (JS) ───────────────────────────
function updateHeatmap(w) {
  const feats = ['rsi', 'ema_diff', 'vol_norm', 'macd'];
  const featLabels = {'rsi':'RSI', 'ema_diff':'EMA diff', 'vol_norm':'Vol norm', 'macd':'MACD'};
  const classes = w.classes || ['DOWN', 'SIDEWAYS', 'UP'];
  const weights = w.weights || {};

  // Gather all values for color normalization
  const allVals = feats.flatMap(f => classes.map(c => parseFloat(weights[f]?.[c] ?? 0)));
  const maxAbs = Math.max(...allVals.map(Math.abs), 0.001);

  let html = '<table class="heatmap"><thead><tr><th>Feature</th>';
  classes.forEach(c => { html += `<th>${c}</th>`; });
  html += '</tr></thead><tbody>';

  feats.forEach(feat => {
    html += `<tr><td class="hm-feat">${featLabels[feat] || feat}</td>`;
    classes.forEach(cls => {
      const val = parseFloat(weights[feat]?.[cls] ?? 0);
      const norm = val / maxAbs; // -1 to +1
      const r = norm < 0 ? Math.round(240 * Math.abs(norm)) : 0;
      const g = norm > 0 ? Math.round(201 * norm) : 0;
      const alpha = Math.abs(norm) * 0.5 + 0.05;
      const bg = `rgba(${r},${g},0,${alpha.toFixed(2)})`;
      const color = Math.abs(norm) > 0.5 ? '#fff' : (norm > 0 ? '#00c97a' : '#f03c52');
      html += `<td style="background:${bg};color:${color}">${val.toFixed(4)}</td>`;
    });
    html += '</tr>';
  });
  html += '</tbody></table>';

  const wrap = document.getElementById('heatmapWrap');
  if (wrap) wrap.innerHTML = html;

  // Update metrics
  const mb = document.getElementById('metricsBox');
  if (mb && w.acc) {
    const acc = (w.acc * 100).toFixed(2);
    const cls = w.acc >= 0.55 ? 'c-green' : 'c-yellow';
    mb.innerHTML = `
      <div class="metric-row"><span class="metric-label">Símbolo</span><span class="metric-val c-blue">${w.symbol||'--'}</span></div>
      <div class="metric-row"><span class="metric-label">Accuracy (OOS)</span><span class="metric-val ${cls}">${acc}%</span></div>
      <div class="metric-row"><span class="metric-label">Actualizado</span><span class="metric-val" style="font-size:10px;color:var(--dim)">${w.updated_at||'--'}</span></div>
    `;
  }
}

updateHints();
</script>
</body>
</html>
<?php
function renderHeatmap(array $w) {
    $feats = ['rsi', 'ema_diff', 'vol_norm', 'macd'];
    $featLabels = ['rsi'=>'RSI', 'ema_diff'=>'EMA diff', 'vol_norm'=>'Vol norm', 'macd'=>'MACD'];
    $classes = $w['classes'] ?? ['DOWN','SIDEWAYS','UP'];
    $weights = $w['weights'] ?? [];

    $allVals = [];
    foreach ($feats as $f) foreach ($classes as $c) $allVals[] = abs((float)($weights[$f][$c] ?? 0));
    $maxAbs = max(array_merge([0.0001], $allVals));

    echo '<table class="heatmap"><thead><tr><th>Feature</th>';
    foreach ($classes as $c) echo "<th>$c</th>";
    echo '</tr></thead><tbody>';

    foreach ($feats as $feat) {
        $label = $featLabels[$feat] ?? $feat;
        echo "<tr><td class=\"hm-feat\">$label</td>";
        foreach ($classes as $cls) {
            $val = (float)($weights[$feat][$cls] ?? 0);
            $norm = $val / $maxAbs;
            $r = $norm < 0 ? (int)(240 * abs($norm)) : 0;
            $g = $norm > 0 ? (int)(201 * $norm) : 0;
            $alpha = round(abs($norm) * 0.5 + 0.05, 2);
            $bg = "rgba($r,$g,0,$alpha)";
            $color = abs($norm) > 0.5 ? '#fff' : ($norm > 0 ? '#00c97a' : '#f03c52');
            echo "<td style=\"background:$bg;color:$color\">" . number_format($val, 4) . "</td>";
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
}
