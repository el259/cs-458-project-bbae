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
    <button class="classroom-button">
        Classroom
        <svg width="800px" height="800px" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <g>
                <path fill="none" d="M0 0h24v24H0z"/>
                <path d="M12 15l-4.243-4.243 1.415-1.414L12 12.172l2.828-2.829 1.415 1.414z"/>
            </g>
        </svg>
    </button>

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

    <div class="classroom-popup active" aria-hidden="true">
        <div class="background"></div>
        <div class="content">
            <p>Classroom details will appear here.</p>
        </div>
    </div>
</div>