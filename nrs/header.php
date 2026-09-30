<?php
$logo_href = "index.php";
if (!empty($_SESSION['teacher'])) {
    $logo_href = "teacher.php";
} else {
    $logo_href = "student.php";
}
?>

<div class="site-header">
    <a class="logo" href="<?= $logo_href ?>">
        <p>HumGlot</p>
    </a>

    <div class="right-side">
        <?php
        if(!empty($_SESSION['account']))
        {
        ?>
            <div class="account">
                <p class="account-name"><?= htmlspecialchars($_SESSION['account'], ENT_QUOTES, 'UTF-8') ?></p>
                <span></span>
            </div>
        <?php
        }
        ?>
    </div>
</div>