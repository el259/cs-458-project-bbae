<?php
session_start();
require __DIR__ . '/auth.php';
require_once __DIR__ . '/experimental-feats/fuzzy-checker.php';
requireAccount('assignment');
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

$userId = $_SESSION['user_id'];
$drillId = intval($_GET['id'] ?? $_POST['drillId'] ?? 0);
$classroomId = intval($_GET['classroom'] ?? $_POST['classroom'] ?? 0);
$error = '';
$items = [];
$guesses = [];
$totalCorrect = 0;
$showScore = false;
$pdfUrl = '';

if ($drillId <= 0) {
    $error = 'No drill selected.';
} else {
    $databaseConnection = hum_conn_no_login();

    // Access check
    $accessSql = '
        SELECT 1
        FROM "CLASSROOM_DRILL" cd
        JOIN "CLASSROOM_MEMBERSHIP" m ON m."CLASSROOM_ID" = cd."CLASSROOM_ID"
        WHERE cd."DRILL_ID" = :drill_id
          AND m."USER_ID" = :user_id
    ';
    $accessStmt = oci_parse($databaseConnection, $accessSql);
    oci_bind_by_name($accessStmt, ':drill_id', $drillId);
    oci_bind_by_name($accessStmt, ':user_id', $userId);
    oci_execute($accessStmt);
    $hasAccess = oci_fetch_assoc($accessStmt) !== false;
    oci_free_statement($accessStmt);

    if (!$hasAccess) {
        $error = 'You do not have access to this drill.';
    } else {
        // Load drill config for optional PDF
        $configSql = 'SELECT "CONFIG" FROM "DRILL" WHERE "DRILL_ID" = :drill_id';
        $configStmt = oci_parse($databaseConnection, $configSql);
        oci_bind_by_name($configStmt, ':drill_id', $drillId);
        if (oci_execute($configStmt)) {
            $cfgRow = oci_fetch_assoc($configStmt);
            if ($cfgRow && !empty($cfgRow['CONFIG'])) {
                $cfgJson = is_object($cfgRow['CONFIG']) && method_exists($cfgRow['CONFIG'], 'load')
                    ? $cfgRow['CONFIG']->load()
                    : (string) $cfgRow['CONFIG'];
                $cfg = json_decode($cfgJson, true);
                if (is_array($cfg) && !empty($cfg['pdf'])) {
                    $pdfUrl = (string) $cfg['pdf'];
                }
            }
        }
        oci_free_statement($configStmt);

        // Load items
        $sql = '
            SELECT i."ITEM_ID", i."TERM", i."TRANSLATION", i."EXAMPLE", i."TYPE"
            FROM "ITEM" i
            JOIN "DRILL_ITEM" di ON di."ITEM_ID" = i."ITEM_ID"
            WHERE di."DRILL_ID" = :drill_id
            ORDER BY di."ORDER_INDEX", i."TERM"
        ';
        $stmt = oci_parse($databaseConnection, $sql);
        oci_bind_by_name($stmt, ':drill_id', $drillId);
        if (oci_execute($stmt)) {
            while ($row = oci_fetch_assoc($stmt)) {
                $items[] = $row;
            }
        }
        oci_free_statement($stmt);
    }
    oci_close($databaseConnection);
}

if ($error === '' && isset($_POST['guesses']) && is_array($_POST['guesses']) && count($items) > 0) {
    $guesses = $_POST['guesses'];
    if (count($guesses) === count($items)) {
        $showScore = true;
        foreach ($items as $index => $item) {
            $given = trim($guesses[$index] ?? '');
            $expected = $item['TRANSLATION'] ?: $item['TERM'];
            $result = checkAnswer($given, $expected, true, true);
            $items[$index]['verdict'] = $result['verdict'];
            $items[$index]['expected'] = $expected;
            if ($result['verdict'] !== 'wrong') {
                $totalCorrect++;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Drill</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="quiz.css" />
    <style>
        .quiz-with-pdf { display: flex; gap: 1rem; height: calc(100vh - 140px); }
        .pdf-panel { flex: 1; display: flex; flex-direction: column; border: 1px solid #ccc; min-width: 280px; }
        .pdf-panel.hidden { display: none; }
        .pdf-header { display: flex; justify-content: space-between; align-items: center; padding: 0.4rem 0.6rem; background: #f5f5f5; border-bottom: 1px solid #ccc; font-size: 0.9rem; }
        .toggle-btn { padding: 0.2rem 0.6rem; cursor: pointer; }
        .questions-panel { flex: 1; overflow-y: auto; }
        #pdfFrame { flex: 1; border: none; }
    </style>
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<form method="get" action="list_drills.php">
    <input type="hidden" name="id" value="<?= $classroomId ?>" />
    <input type="submit" value="Back to drills" />
</form>

<?php if ($error !== ''): ?>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php elseif (count($items) === 0): ?>
    <p>This drill has no items.</p>
<?php else: ?>
    <?php if ($showScore): ?>
        <?php $percent = count($items) > 0 ? round(($totalCorrect / count($items)) * 100) : 0; ?>
        <h2>Score: <?= $totalCorrect ?> / <?= count($items) ?> (<?= $percent ?>%)</h2>
    <?php endif; ?>

    <div class="<?= $pdfUrl !== '' ? 'quiz-with-pdf' : '' ?>">
        <?php if ($pdfUrl !== ''): ?>
            <div class="pdf-panel" id="pdfPanel">
                <div class="pdf-header">
                    <span>Reference PDF</span>
                    <button type="button" id="togglePdf" class="toggle-btn">Hide PDF</button>
                </div>
                <iframe id="pdfFrame" src="<?= htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') ?>"></iframe>
            </div>
        <?php endif; ?>

        <div class="questions-panel">
            <form method="post">
                <input type="hidden" name="drillId" value="<?= $drillId ?>" />
                <input type="hidden" name="classroom" value="<?= $classroomId ?>" />

                <?php foreach ($items as $index => $item): ?>
                    <div class="quiz-question">
                        <p class="q-text">
                            <strong><?= htmlspecialchars($item['TERM'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if ($item['EXAMPLE']): ?>
                                <br /><em><?= htmlspecialchars($item['EXAMPLE'], ENT_QUOTES, 'UTF-8') ?></em>
                            <?php endif; ?>
                        </p>
                        <input
                            type="text"
                            name="guesses[<?= $index ?>]"
                            value="<?= isset($guesses[$index]) ? htmlspecialchars($guesses[$index], ENT_QUOTES, 'UTF-8') : '' ?>"
                            placeholder="Your translation"
                            required
                        />
                        <?php if ($showScore): ?>
                            <?php if ($item['verdict'] === 'exact' || $item['verdict'] === 'diacritic' || $item['verdict'] === 'typo'): ?>
                                <p class="correct">Correct<?= $item['verdict'] !== 'exact' ? ' (accepted)' : '' ?></p>
                            <?php else: ?>
                                <p class="incorrect">Incorrect. Expected: <?= htmlspecialchars($item['expected'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <input type="submit" value="<?= $showScore ? 'Try Again' : 'Submit' ?>" />
            </form>
        </div>
    </div>

    <?php if ($pdfUrl !== ''): ?>
    <script>
    const toggleBtn = document.getElementById('togglePdf');
    const pdfPanel = document.getElementById('pdfPanel');
    if (toggleBtn && pdfPanel) {
        toggleBtn.addEventListener('click', function () {
            pdfPanel.classList.toggle('hidden');
            toggleBtn.textContent = pdfPanel.classList.contains('hidden') ? 'Show PDF' : 'Hide PDF';
        });
    }
    </script>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
