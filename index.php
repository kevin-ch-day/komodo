<?php

declare(strict_types=1);

/**
 * Root entry: send visitors to public/ so the project tree is not directory-listed.
 * No database; no dependencies.
 */
$target = 'public/';

if (!headers_sent()) {
    header('Location: ' . $target, true, 302);
    exit;
}

// Fallback if headers were already sent (should be rare at project root).
$href = htmlspecialchars($target, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Komodo</title>
</head>
<body>
    <p><a href="<?= $href ?>">Continue to Komodo</a></p>
</body>
</html>
