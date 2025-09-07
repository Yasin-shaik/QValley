<?php
$result = "";
$status = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['qrImage']) && $_FILES['qrImage']['error'] == 0) {
        $uploadDir = "uploads/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir);
        }

        $filePath = $uploadDir . basename($_FILES['qrImage']['name']);
        move_uploaded_file($_FILES['qrImage']['tmp_name'], $filePath);

        // Decode QR using Zxing (you must install it or use any PHP QR library)
        // Example with shell_exec (requires ZXing installed on server)
        $output = shell_exec("java -cp 'zxing-core.jar:zxing-javase.jar' com.google.zxing.client.j2se.CommandLineRunner " . escapeshellarg($filePath));

        $result = $output ? $output : "QR code could not be read.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>QR Status Checker</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin-top: 50px; }
        .result { margin: 20px; font-size: 18px; font-weight: bold; }
        .status { font-size: 22px; padding: 10px; margin-top: 20px; display: inline-block; }
        .safe { color: green; }
        .fraud { color: red; }
        .suspicious { color: orange; }
    </style>
</head>
<body>
    <h2>Upload QR Code</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="qrImage" required>
        <button type="submit">Upload & Decode</button>
    </form>

    <?php if ($result): ?>
        <div class="result">QR Content: <?= htmlspecialchars($result) ?></div>
        <div class="result">Press <b>1</b> = Safe, <b>2</b> = Fraud, <b>3</b> = Suspicious</div>
        <div id="status" class="status"></div>
    <?php endif; ?>

    <script>
        document.addEventListener("keydown", function(event) {
            let statusBox = document.getElementById("status");
            if (!statusBox) return;

            if (event.key === "1") {
                statusBox.innerHTML = "✅ SAFE";
                statusBox.className = "status safe";
            } else if (event.key === "2") {
                statusBox.innerHTML = "❌ FRAUD";
                statusBox.className = "status fraud";
            } else if (event.key === "3") {
                statusBox.innerHTML = "⚠️ SUSPICIOUS";
                statusBox.className = "status suspicious";
            }
        });
    </script>
</body>
</html>
