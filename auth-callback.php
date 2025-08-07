<?php
// auth-callback.php - OAuth callback endpoint (not used in current implementation)
// This file is only needed if you want to test interactive authentication

// Note: The current staff directory implementation uses "client credentials flow"
// which doesn't require this callback. This file is provided for completeness.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication Callback</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { color: #198754; }
        .error { color: #dc3545; }
        .info { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>Microsoft Graph Authentication</h1>
    
    <div class="info">
        <strong>Note:</strong> The Staff Directory system uses application-only authentication 
        and doesn't require user sign-in. This callback page is not used in normal operation.
    </div>

    <?php if (isset($_GET['code'])): ?>
        <div class="success">
            <h3>✓ Authorization Code Received</h3>
            <p>Authorization code: <code><?php echo htmlspecialchars(substr($_GET['code'], 0, 20)); ?>...</code></p>
            <p>This indicates the OAuth flow is working correctly.</p>
        </div>
    <?php elseif (isset($_GET['error'])): ?>
        <div class="error">
            <h3>✗ Authorization Error</h3>
            <p>Error: <code><?php echo htmlspecialchars($_GET['error']); ?></code></p>
            <?php if (isset($_GET['error_description'])): ?>
                <p>Description: <?php echo htmlspecialchars($_GET['error_description']); ?></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p>This is the OAuth callback endpoint for Microsoft Graph authentication.</p>
        <p>For the Staff Directory system, this endpoint is not actively used since we employ application-only authentication.</p>
    <?php endif; ?>

    <div style="margin-top: 30px;">
        <a href="directory.php">← Back to Staff Directory</a> |
        <a href="graph_setup.php">Graph Setup</a>
    </div>
</body>
</html>