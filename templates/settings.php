<div class="wrap">
    <h1>WP Headless CI Settings</h1>

    <!-- デバッグ情報 -->
    <!-- <div style="background-color: #f0f0f0; padding: 10px; margin-bottom: 20px;">
        <h3>Debug Information:</h3>
        <p>options_group: <?php echo isset($options_group) ? $options_group : 'Not set'; ?></p>
        <p>page: <?php echo isset($page) ? $page : 'Not set'; ?></p>
    </div> -->

    <form method="post" action="options.php">
        <?php
        if (isset($options_group)) {
            settings_fields($options_group);
        }
        if (isset($page)) {
            do_settings_sections($page);
        } else {
            echo "Warning: 'page' variable is not set.";
        }
        submit_button();
        ?>
    </form>
</div>