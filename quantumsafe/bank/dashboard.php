<?php
// index.php - main entry point
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>
<!DOCTYPE html>
<html>
<head>
    <title>My PHP App</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; }
        nav { background: #222; padding: 15px; }
        nav button { margin: 0 10px; padding: 10px 15px; background: #555; color: #fff; border: none; cursor: pointer; }
        nav button:hover { background: #007BFF; }
        .content { padding: 20px; }
    </style>
</head>
<body>

    <!-- Navigation Bar -->
    <nav>
        <form method="GET" style="display:inline;">
            <input type="hidden" name="page" value="dashboard">
            <button type="submit">Dashboard</button>
        </form>

        <form method="GET" style="display:inline;">
            <input type="hidden" name="page" value="profile">
            <button type="submit">Profile</button>
        </form>

        <form method="GET" style="display:inline;">
            <input type="hidden" name="page" value="settings">
            <button type="submit">Settings</button>
        </form>
    </nav>

    <!-- Content Area -->
    <div class="content">
        <?php
        switch($page) {
            case "dashboard":
                include "dashboard.php";
                break;
            case "profile":
                include "profile.php";
                break;
            case "settings":
                include "settings.php";
                break;
            default:
                echo "<h2>404 - Page not found</h2>";
        }
        ?>
    </div>

</body>
</html>
