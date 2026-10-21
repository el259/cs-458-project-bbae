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
$textbookId = intval($_GET['textbook_id'] ?? 0);
$error = '';
$databaseConnection = hum_conn_no_login();

// Load available items (from textbook if specified, plus free-floating)
$items = [];
if ($textbookId > 0) {
    $sql = '
        SELECT "ITEM_ID", "TERM", "TRANSLATION", "TYPE"
        FROM "ITEM"
        WHERE ("TEXTBOOK_ID" = :tb OR "TEXTBOOK_ID" IS NULL)
          AND "CREATED_BY" = :uid
        ORDER BY "TERM"
    ';
    $stmt = oci_parse($databaseConnection, $sql);
    oci_bind_by_name($stmt, ':tb', $textbookId);
    oci_bind_by_name($stmt, ':uid', $userId);
} else {
    $sql = '
        SELECT "ITEM_ID", "TERM", "TRANSLATION", "TYPE"
        FROM "ITEM"
        WHERE "CREATED_BY" = :uid
        ORDER BY "TERM"
    ';
    $stmt = oci_parse($databaseConnection, $sql);
    oci_bind_by_name($stmt, ':uid', $userId);
}
oci_execute($stmt);
while ($row = oci_fetch_assoc($stmt)) {
    $items[] = $row;
}
oci_free_statement($stmt);

// Create drill
if (isset($_POST['createDrill'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $selected = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];

    if ($title === '' || count($selected) === 0) {
        $error = 'Title and at least one item are required.';
    } else {
        $sql = '
            INSERT INTO "DRILL" (
                "TITLE", "DESCRIPTION", "TEXTBOOK_ID", "CREATED_BY"
            ) VALUES (
                :title, :desc, :tb, :uid
            ) RETURNING "DRILL_ID" INTO :new_id
        ';
        $stmt = oci_parse($databaseConnection, $sql);
        $newId = 0;
        oci_bind_by_name($stmt, ':title', $title);
        oci_bind_by_name($stmt, ':desc', $description);
        oci_bind_by_name($stmt, ':tb', $textbookId);
        oci_bind_by_name($stmt, ':uid', $userId);
        oci_bind_by_name($stmt, ':new_id', $newId, 32);

        if (oci_execute($stmt)) {
            // Link items
            $linkSql = 'INSERT INTO "DRILL_ITEM" ("DRILL_ID", "ITEM_ID", "ORDER_INDEX") VALUES (:did, :iid, :ord)';
            $linkStmt = oci_parse($databaseConnection, $linkSql);
            $order = 0;
            foreach ($selected as $itemId) {
                $iid = intval($itemId);
                oci_bind_by_name($linkStmt, ':did', $newId);
                oci_bind_by_name($linkStmt, ':iid', $iid);
                oci_bind_by_name($linkStmt, ':ord', $order);
                oci_execute($linkStmt, OCI_NO_AUTO_COMMIT);
                $order++;
            }
            oci_free_statement($linkStmt);
            oci_commit($databaseConnection);
            oci_free_statement($stmt);
            oci_close($databaseConnection);
            header('Location: manage_drills.php');
            exit();
        } else {
            $error = 'Failed to create drill.';
            oci_rollback($databaseConnection);
        }
        oci_free_statement($stmt);
    }
}

oci_close($databaseConnection);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Create Drill</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="teacher.css" />
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<h1>Create Drill</h1>
<?php if ($textbookId > 0): ?>
    <p>Pulling items from textbook + free-floating items.</p>
<?php endif; ?>

<?php if ($error !== ''): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<form method="post">
    <div>
        <label>Title</label>
        <input type="text" name="title" required />
    </div>
    <div>
        <label>Description</label>
        <textarea name="description" rows="2"></textarea>
    </div>

    <h3>Select Items</h3>
    <?php if (count($items) === 0): ?>
        <p>No items available. Add some first.</p>
    <?php else: ?>
        <div style="max-height:400px; overflow:auto; border:1px solid #ccc; padding:0.5em">
            <?php foreach ($items as $item): ?>
                <label style="display:block">
                    <input type="checkbox" name="items[]" value="<?= $item['ITEM_ID'] ?>" />
                    <strong><?= htmlspecialchars($item['TERM'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php if ($item['TRANSLATION']): ?>
                        — <?= htmlspecialchars($item['TRANSLATION'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                    <em>(<?= htmlspecialchars($item['TYPE'], ENT_QUOTES, 'UTF-8') ?>)</em>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p><input type="submit" name="createDrill" value="Create Drill" /></p>
</form>

<p><a href="teacher.php">Back</a></p>
<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
