<?php
$h1_str = wp_headless_ci_translate('Settings');
?>
<div class="wrap">
    <h1><?= $h1_str ?></h1>
    <form method="post" action="options.php">
        <?php
        if (isset($options_group)) {
            settings_fields($options_group);
        }
        if (isset($page)) {
            do_settings_sections($page);
        }
        submit_button();
        ?>
    </form>
</div>
