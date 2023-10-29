<!DOCTYPE html>
<body>
    <h1>Welcome to localhost</h1>
    <p>
        View <a href="http://localhost:2345/index.php">this page with HTTP</a>, served by Apache.
    </p>
    <p>
        View <a href="https://localhost:3456/index.php">this page with HTTPS</a>, served by Apache.
    </p>
    <p>
        View <a href="http://localhost:4567/index.php">this page with HTTP</a>, served by nginx.
    </p>
    <p>
        View <a href="https://localhost:5678/index.php">this page with HTTPS</a>, served by nginx.
    </p>
    <p>
        Page served <?php echo date('Y-m-d H:i:s'); ?> UTC.
    </p>
</body>
