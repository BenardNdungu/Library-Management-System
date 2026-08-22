<?php
/**
 * Footer
 * Library Management System
 */
?>

</main>
</div>

<!-- jQuery (for AJAX) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Custom JavaScript -->
<script src="<?php echo APP_URL; ?>/assets/js/app.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/dashboard.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/books.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/members.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/loans.js"></script>

<!-- Page specific scripts -->
<?php if (isset($pageScripts)): ?>
    <?php foreach ($pageScripts as $script): ?>
        <script src="<?php echo APP_URL; ?>/assets/js/<?php echo $script; ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>