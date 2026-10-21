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
$error = '';
$success = '';
$databaseConnection = hum_conn_no_login();

// Create free-floating item
if (isset($_POST['createItem'])) {
    $term = trim($_POST['term'] ?? '');
    $translation = trim($_POST['translation'] ?? '');
    $language = trim($_POST['language'] ?? '');
    $example = trim($_POST['example'] ?? '');
    $type = trim($_POST['type'] ?? 'vocab');

    if ($term === '' || $language === '') {
        $error = 'Term and language are required.';
    } else {
        $sql = '
            INSERT INTO "ITEM" (
                "TYPE", "LANGUAGE", "TERM", "TRANSLATION", "EXAMPLE", "CREATED_BY"
            ) VALUES (
                :type, :lang, :term, :trans, :ex, :uid
            )
        ';
        $stmt = oci_parse($databaseConnection, $sql);
        oci_bind_by_name($stmt, ':type', $type);
        oci_bind_by_name($stmt, ':lang', $language);
        oci_bind_by_name($stmt, ':term', $term);
        oci_bind_by_name($stmt, ':trans', $translation);
        oci_bind_by_name($stmt, ':ex', $example);
        oci_bind_by_name($stmt, ':uid', $userId);

        if (oci_execute($stmt)) {
            oci_commit($databaseConnection);
            $success = 'Free-floating item created.';
        } else {
            $error = 'Failed to create item.';
            oci_rollback($databaseConnection);
        }
        oci_free_statement($stmt);
    }
}

// List free-floating items by this teacher
$items = [];
$sql = '
    SELECT "ITEM_ID", "TERM", "TRANSLATION", "LANGUAGE", "TYPE"
    FROM "ITEM"
    WHERE "TEXTBOOK_ID" IS NULL
      AND "CREATED_BY" = :uid
    ORDER BY "TERM"
';
$stmt = oci_parse($databaseConnection, $sql);
oci_bind_by_name($stmt, ':uid', $userId);
oci_execute($stmt);
while ($row = oci_fetch_assoc($stmt)) {
    $items[] = $row;
}
oci_free_statement($stmt);
oci_close($databaseConnection);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Free-floating Items</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="teacher.css" />
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<h1>Free-floating Items</h1>
<p>Items not attached to any textbook. Useful for custom drills or extra practice.</p>

<?php if ($error !== ''): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php if ($success !== ''): ?><p class="success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<h2>Add Item</h2>
<form method="post">
    <div><label>Term</label><input type="text" name="term" required /></div>
    <div><label>Translation</label><input type="text" name="translation" /></div>
    <div><label>Language</label><input type="text" name="language" required placeholder="Spanish" /></div>
    <div><label>Example</label><input type="text" name="example" /></div>
    <div>
        <label>Type</label>
        <select name="type">
            <option value="vocab">Vocab</option>
            <option value="phrase">Phrase</option>
            <option value="grammar">Grammar</option>
        </select>
    </div>
    <input type="submit" name="createItem" value="Add Item" />
</form>

<h2>Your Free-floating Items</h2>
<?php if (count($items) === 0): ?>
    <p>None yet.</p>
<?php else: ?>
    <ul>
        <?php foreach ($items as $item): ?>
            <li>
                <strong><?= htmlspecialchars($item['TERM'], ENT_QUOTES, 'UTF-8') ?></strong>
                <?php if ($item['TRANSLATION']): ?> — <?= htmlspecialchars($item['TRANSLATION'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                <em>(<?= htmlspecialchars($item['LANGUAGE'], ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($item['TYPE'], ENT_QUOTES, 'UTF-8') ?>)</em>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<p><a href="teacher.php">Back to dashboard</a></p>
</body>
</html>
