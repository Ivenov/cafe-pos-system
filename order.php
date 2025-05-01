<?php
session_start();
require_once __DIR__ . '/core/db.php';

// Ensure the orders table exists
require_once __DIR__ . '/create_orders_table.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['products'])) {
    $products = $_POST['products'];
    $orderSuccess = true;

    foreach ($products as $product) {
        $productId = intval($product['id']);
        $quantity = intval($product['quantity']);

        // Fetch product details
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $productDetails = $stmt->fetch();

        if ($productDetails && $quantity > 0 && $quantity <= $productDetails['quantity']) {
            // Insert order into the database
            $stmt = $pdo->prepare("INSERT INTO orders (product_id, quantity, total_price) VALUES (?, ?, ?)");
            if (!$stmt->execute([$productId, $quantity, $productDetails['price'] * $quantity])) {
                $orderSuccess = false;
                $_SESSION['error'] = "Помилка при додаванні замовлення для продукту ID: $productId.";
                break;
            }

            // Update product quantity
            $stmt = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
            if (!$stmt->execute([$quantity, $productId])) {
                $orderSuccess = false;
                $_SESSION['error'] = "Помилка при оновленні кількості для продукту ID: $productId.";
                break;
            }
        } else {
            $orderSuccess = false;
            $_SESSION['error'] = "Невірна кількість товару для продукту ID: $productId.";
            break;
        }
    }

    if ($orderSuccess) {
        $_SESSION['message'] = "Замовлення успішно оформлено.";
    }
}

header('Location: catalog.php');
exit();
?>
