<?php
require_once '../vendor/autoload.php';
require_once 'config.php'; // DB
require_once 'session.php'; // Sessies

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

// Setup logging
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

// Create logger
$log = new Logger('products');
$dateFormat = "Y-m-d H:i:s";
$output = "[%datetime%] %channel%.%level_name%: %message% %context%\n";
$formatter = new LineFormatter($output, $dateFormat);

// Create handler for product views (rotates daily, keeps 30 days of logs)
$productHandler = new RotatingFileHandler($logDir . '/products.log', 30, Logger::INFO);
$productHandler->setFormatter($formatter);
$log->pushHandler($productHandler);

if (!isset($_GET['id'])) { // ID check
    header('Location: index.php');
    exit();
}

// Product ophalen met mysqli prepared statement
$stmt = $mysqli->prepare("SELECT * FROM producten WHERE id = ?");
$stmt->bind_param("i", $_GET['id']);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) { // Bestaat niet
    header('Location: index.php');
    exit();
}

// Log de product weergave
$log->info('Product bekeken', [
    'product_id' => $product['id'],
    'product_naam' => $product['naam'],
    'user_id' => $_SESSION['user_id'] ?? 'gast',
    'prijs' => $product['prijs'],
    'categorie' => $product['categorie'],
    'ip' => $_SERVER['REMOTE_ADDR'],
    'timestamp' => date('Y-m-d H:i:s')
]);
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($product['naam']) ?> - Funko Pop Shop</title>
      <link rel="stylesheet" href="../css/css.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
       
    </style>
</head>
<body>
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="logo">Funko Pop Shop</a>
            <div class="user-info">
                <?php if (isLoggedIn()): ?>
                    <a href="winkelwagen.php">Winkelwagen (<?= getCartItemCount() ?>)</a>
                    <a href="logout.php">Uitloggen</a>
                <?php else: ?>
                    <a href="inlogen.php">Inloggen</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="product-detail">
            <div class="product-images">
                <div class="main-image">
                    <?php if ($product['afbeelding']): ?>
                       <img src="../images/<?= htmlspecialchars($product['afbeelding']) ?>" alt="<?= htmlspecialchars($product['naam']) ?>">
                    <?php else: ?>
                        <div class="no-image">🎭</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="product-info">
                <h1><?= htmlspecialchars($product['naam']) ?></h1>
                
                <div class="product-meta">
                    <?php if ($product['funko_nummer']): ?>
                        <span class="meta-badge meta-number"><?= htmlspecialchars($product['funko_nummer']) ?></span>
                    <?php endif; ?>
                    <?php if ($product['categorie']): ?>
                        <span class="meta-badge meta-category"><?= htmlspecialchars($product['categorie']) ?></span>
                    <?php endif; ?>
                    <?php if ($product['serie']): ?>
                        <span class="meta-badge meta-serie"><?= htmlspecialchars($product['serie']) ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if ($product['exclusief'] || $product['vaulted'] || $product['limited_edition']): ?>
                    <div class="special-badges">
                        <?php if ($product['exclusief']): ?>
                            <span class="special-badge exclusive-badge-large">✨ Exclusive</span>
                        <?php endif; ?>
                        <?php if ($product['vaulted']): ?>
                            <span class="special-badge vaulted-badge-large">🔒 Vaulted</span>
                        <?php endif; ?>
                        <?php if ($product['limited_edition']): ?>
                            <span class="special-badge limited-badge-large">⭐ Limited Edition</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="product-pricing">
                    <?php if ($product['in_aanbieding']): ?>
                        <div style="margin-bottom: 10px;">
                            <span class="original-price" style="font-size: 20px;">€<?= number_format($product['prijs'], 2, ',', '.') ?></span>
                        </div>
                        <div>
                            <span class="sale-price" style="font-size: 32px; font-family: 'Fredoka One', cursive;">€<?= number_format($product['aanbieding_prijs'], 2, ',', '.') ?></span>
                            <div style="margin-top: 10px; background: var(--funko-red); color: white; padding: 8px 16px; border-radius: 15px; display: inline-block; font-family: 'Fredoka One', cursive; font-size: 12px; text-transform: uppercase;">🔥 Special Offer!</div>
                        </div>
                    <?php else: ?>
                        <span class="current-price" style="font-size: 32px; font-family: 'Fredoka One', cursive;">€<?= number_format($product['prijs'], 2, ',', '.') ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="product-voorraad">
                    <?php if ($product['voorraad'] > 0): ?>
                        <div class="in-stock">
                            ✅ Op voorraad (<?= $product['voorraad'] ?> stuks beschikbaar)
                        </div>
                    <?php else: ?>
                        <div class="out-of-stock">
                            ❌ Tijdelijk uitverkocht
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="product-actions">
                    <?php if ($product['voorraad'] > 0): ?>
                        <form method="POST" action="add_to_cart.php">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <button type="submit" class="btn btn-primary btn-block" style="font-size: 18px; padding: 18px;">
                                🛒 Toevoegen aan winkelwagen
                            </button>
                        </form>
                    <?php else: ?>
                        <button class="btn btn-secondary btn-block" disabled style="font-size: 18px; padding: 18px;">
                            ❌ Uitverkocht
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php if ($product['beschrijving']): ?>
                    <div class="product-description">
                        <h3>📝 Productbeschrijving</h3>
                        <p><?= nl2br(htmlspecialchars($product['beschrijving'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="text-align: center; margin: 40px 0;">
            <a href="index.php" class="btn btn-secondary">← Terug naar collectie</a>
        </div>
    </div>

    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Parallax effect on main image
            const mainImage = document.querySelector('.main-image img');
            if (mainImage) {
                document.addEventListener('mousemove', function(e) {
                    const x = (e.clientX / window.innerWidth) * 10;
                    const y = (e.clientY / window.innerHeight) * 10;
                    mainImage.style.transform = `translateX(${x}px) translateY(${y}px) scale(1.02)`;
                });
            }

            // Pulse effect for limited edition
            const limitedBadge = document.querySelector('.limited-badge-large');
            if (limitedBadge) {
                setInterval(() => {
                    limitedBadge.style.transform = 'scale(1.1)';
                    setTimeout(() => {
                        limitedBadge.style.transform = 'scale(1)';
                    }, 200);
                }, 2000);
            }
        });
    </script>
</body>
</html>