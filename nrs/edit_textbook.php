<?php
session_start();
require __DIR__ . '/auth.php';
requireTeacher('dashboard');
require_once dirname(__DIR__) . '/hum_conn_no_login.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

$userId = $_SESSION['user_id'];
$textbookId = intval($_GET['id'] ?? 0);
$error = '';
$success = '';

if ($textbookId <= 0) {
    header('Location: manage_textbooks.php');
    exit();
}

$databaseConnection = hum_conn_no_login();

// Verify ownership
$verifySql = 'SELECT "TITLE", "LANGUAGE" FROM "TEXTBOOK" WHERE "TEXTBOOK_ID" = :id AND "CREATED_BY" = :user_id';
$verifyStmt = oci_parse($databaseConnection, $verifySql);
oci_bind_by_name($verifyStmt, ':id', $textbookId);
oci_bind_by_name($verifyStmt, ':user_id', $userId);
oci_execute($verifyStmt);
$textbook = oci_fetch_assoc($verifyStmt);
oci_free_statement($verifyStmt);

if ($textbook === false) {
    oci_close($databaseConnection);
    header('Location: manage_textbooks.php');
    exit();
}

// Add unit
if (isset($_POST['addUnit'])) {
    $title = trim($_POST['unitTitle'] ?? '');
    $order = intval($_POST['orderIndex'] ?? 0);
    if ($title !== '') {
        $sql = 'INSERT INTO "UNIT" ("TEXTBOOK_ID", "TITLE", "ORDER_INDEX") VALUES (:tb, :title, :ord)';
        $stmt = oci_parse($databaseConnection, $sql);
        oci_bind_by_name($stmt, ':tb', $textbookId);
        oci_bind_by_name($stmt, ':title', $title);
        oci_bind_by_name($stmt, ':ord', $order);
        if (oci_execute($stmt)) {
            oci_commit($databaseConnection);
            $success = 'Unit added.';
        } else {
            $error = 'Failed to add unit.';
            oci_rollback($databaseConnection);
        }
        oci_free_statement($stmt);
    }
}

// Add item to a unit
if (isset($_POST['addItem'])) {
    $unitId = intval($_POST['unitId'] ?? 0);
    $term = trim($_POST['term'] ?? '');
    $translation = trim($_POST['translation'] ?? '');
    $example = trim($_POST['example'] ?? '');
    $type = trim($_POST['type'] ?? 'vocab');

    if ($term !== '' && $unitId > 0) {
        $sql = '
            INSERT INTO "ITEM" (
                "UNIT_ID", "TEXTBOOK_ID", "TYPE", "LANGUAGE", "TERM",
                "TRANSLATION", "EXAMPLE", "CREATED_BY"
            ) VALUES (
                :p_unit_id, :p_textbook_id, :p_item_type, :p_language,
                :p_term, :p_translation, :p_example, :p_created_by
            )
        ';
        $stmt = oci_parse($databaseConnection, $sql);
        oci_bind_by_name($stmt, ':p_unit_id', $unitId);
        oci_bind_by_name($stmt, ':p_textbook_id', $textbookId);
        oci_bind_by_name($stmt, ':p_item_type', $type);
        oci_bind_by_name($stmt, ':p_language', $textbook['LANGUAGE']);
        oci_bind_by_name($stmt, ':p_term', $term);
        oci_bind_by_name($stmt, ':p_translation', $translation);
        oci_bind_by_name($stmt, ':p_example', $example);
        oci_bind_by_name($stmt, ':p_created_by', $userId);

        if (oci_execute($stmt)) {
            oci_commit($databaseConnection);
            $success = 'Item added.';
        } else {
            $error = oci_error($stmt)['message'] ?? 'Failed to add item.';
            oci_rollback($databaseConnection);
        }
        oci_free_statement($stmt);
    }
}

// Load units
$units = [];
$unitSql = 'SELECT "UNIT_ID", "TITLE", "ORDER_INDEX" FROM "UNIT" WHERE "TEXTBOOK_ID" = :tb ORDER BY "ORDER_INDEX", "TITLE"';
$unitStmt = oci_parse($databaseConnection, $unitSql);
oci_bind_by_name($unitStmt, ':tb', $textbookId);
oci_execute($unitStmt);
while ($u = oci_fetch_assoc($unitStmt)) {
    $units[] = $u;
}
oci_free_statement($unitStmt);

// Load items grouped by unit
$itemsByUnit = [];
if (count($units) > 0) {
    $itemSql = '
        SELECT "ITEM_ID", "UNIT_ID", "TERM", "TRANSLATION", "EXAMPLE", "TYPE"
        FROM "ITEM"
        WHERE "TEXTBOOK_ID" = :tb
        ORDER BY "TERM"
    ';
    $itemStmt = oci_parse($databaseConnection, $itemSql);
    oci_bind_by_name($itemStmt, ':tb', $textbookId);
    oci_execute($itemStmt);
    while ($it = oci_fetch_assoc($itemStmt)) {
        $u_id = $it['UNIT_ID'] ?? 0;
        $itemsByUnit[$u_id][] = $it;
    }
    oci_free_statement($itemStmt);
}

oci_close($databaseConnection);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Textbook</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="teacher.css" />
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<h1><?= htmlspecialchars($textbook['TITLE'], ENT_QUOTES, 'UTF-8') ?></h1>
<p>Language: <?= htmlspecialchars($textbook['LANGUAGE'], ENT_QUOTES, 'UTF-8') ?></p>

<?php if ($error !== ''): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($success !== ''): ?><p class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<h2>Add Unit</h2>
<form method="post">
    <input type="text" name="unitTitle" placeholder="Unit title" required />
    <input type="number" name="orderIndex" value="0" style="width:60px" />
    <input type="submit" name="addUnit" value="Add Unit" />
</form>

<?php foreach ($units as $unit): ?>
    <h3><?= htmlspecialchars($unit['TITLE'], ENT_QUOTES, 'UTF-8') ?></h3>

    <form method="post" style="margin-bottom:1em">
        <input type="hidden" name="unitId" value="<?= $unit['UNIT_ID'] ?>" />
        <input type="text" name="term" placeholder="Term / word" required />
        <input type="text" name="translation" placeholder="Translation" />
        <input type="text" name="example" placeholder="Example (optional)" />
        <select name="type">
            <option value="vocab">Vocab</option>
            <option value="phrase">Phrase</option>
            <option value="grammar">Grammar</option>
        </select>
        <input type="submit" name="addItem" value="Add Item" />
    </form>

    <?php if (!empty($itemsByUnit[$unit['UNIT_ID']])): ?>
        <ul>
            <?php foreach ($itemsByUnit[$unit['UNIT_ID']] as $item): ?>
                <li>
                    <strong><?= htmlspecialchars($item['TERM'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php if ($item['TRANSLATION']): ?>
                        — <?= htmlspecialchars($item['TRANSLATION'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                    <em>(<?= htmlspecialchars($item['TYPE'], ENT_QUOTES, 'UTF-8') ?>)</em>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p><em>No items yet.</em></p>
    <?php endif; ?>
<?php endforeach; ?>

<p><a href="manage_textbooks.php">Back to textbooks</a></p>
<p><a href="create_drill.php?textbook_id=<?= $textbookId ?>">Create a drill from this textbook</a></p>

<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
