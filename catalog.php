<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/core/db.php';

// Fetch categories for the filter
$categories = $pdo->query("SELECT id, name FROM categories")->fetchAll(PDO::FETCH_ASSOC);

// Handle filtering and searching
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : null;
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE 1=1";
$params = [];

if ($categoryFilter) {
    $sql .= " AND p.category_id = ?";
    $params[] = $categoryFilter;
}

if ($searchQuery) {
    $sql .= " AND (p.name LIKE ? OR p.article LIKE ? OR p.ean LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$products = $pdo->prepare($sql);
$products->execute($params);
$products = $products->fetchAll(PDO::FETCH_ASSOC);

// Improved function to shorten the description
function shortenDescription($description, $maxLength = 100) {
    $description = str_replace(['�', "\n", "\r"], '', $description); // Remove unwanted characters
    return mb_strlen($description) > $maxLength ? mb_substr($description, 0, $maxLength) . '...' : $description;
}

// Handle file type checking for uploaded files
if (isset($_FILES['file'])) {
    $fileType = $_FILES['file']['name'];
    $fileExt = explode('.', $fileType);
    $fileActualExt = strtolower(end($fileExt));
    $allowed = array('jpg', 'jpeg', 'png');

    if (in_array($fileActualExt, $allowed)) {
        // ...existing code...
    } else {
        echo "Invalid file type.";
    }
}

// Function to send order details to Telegram
function sendOrderToTelegram($message) {
    $token = 'YOUR_TELEGRAM_BOT_TOKEN';
    $chat_id = 'YOUR_TELEGRAM_CHAT_ID';
    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];

    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Каталог товарів</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="acsees/catalogstyle.css">
    <style>
        .table thead th {
            position: sticky;
            top: 0;
            background-color: #fff;
            z-index: 1;
        }
        .order-button {
            position: absolute;
            top: 2px;
            right: 10px;
        }
        .zoom {
            transition: transform 0.2s;
        }
        .zoom:hover {
            transform: scale(2);
            z-index: 2;
            position: relative;
        }
    </style>
</head>
<body>
    <main>
        <section class="catalog-section">
            <h2>Каталог товарів</h2>
            <form method="GET" class="mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <select name="category" class="form-select">
                            <option value="">Всі категорії</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>" <?= $categoryFilter == $category['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="search" class="form-control" placeholder="Пошук за назвою, артикулом або штрихкодом" value="<?= htmlspecialchars($searchQuery) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Фільтрувати</button>
                    </div>
                </div>
            </form>
            <form id="orderForm" method="POST" action="order.php">
                <button type="button" class="btn btn-primary order-button" data-bs-toggle="modal" data-bs-target="#confirmModal">Замовити вибрані товари</button>
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Фото</th>
                            <th>Назва</th>
                            <th>Опис</th>
                            <th>Артикул</th>
                            <th>Категорія</th>
                            <th>Ціна</th>
                            <th>Кількість</th>
                            <th>Замовити</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="8" class="text-center">Товари не знайдено</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($product['photo'])): ?>
                                            <?php $photos = explode(',', $product['photo']); ?>
                                            <?php foreach ($photos as $photo): ?>
                                                <?php if (file_exists($photo)): ?>
                                                    <img src="<?= htmlspecialchars($photo) ?>" alt="<?= htmlspecialchars($product['name']) ?>" style="max-width: 100px;" class="zoom">
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            Немає фото
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="#" class="product-name" data-bs-toggle="modal" data-bs-target="#productModal" 
                                           data-name="<?= htmlspecialchars($product['name']) ?>" 
                                           data-description="<?= htmlspecialchars($product['description']) ?>" 
                                           data-article="<?= htmlspecialchars($product['article']) ?>" 
                                           data-category="<?= htmlspecialchars($product['category_name']) ?>" 
                                           data-price="<?= number_format($product['price'], 2) ?> грн." 
                                           data-photo="<?= htmlspecialchars($product['photo']) ?>">
                                            <?= htmlspecialchars($product['name']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span title="<?= htmlspecialchars(str_replace('�', '', $product['description'])) ?>">
                                            <?= htmlspecialchars(shortenDescription($product['description'], 100)) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($product['article']) ?></td>
                                    <td><?= htmlspecialchars($product['category_name']) ?></td>
                                    <td><?= number_format($product['price'], 2) ?> грн.</td>
                                    <td><?= $product['quantity'] ?></td>
                                    <td>
                                        <input type="hidden" name="products[<?= $product['id'] ?>][id]" value="<?= $product['id'] ?>">
                                        <div class="mb-3">
                                            <input type="number" name="products[<?= $product['id'] ?>][quantity]" class="form-control" min="1" max="<?= $product['quantity'] ?>" required>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
            <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="imageModalLabel">Зображення</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img id="modalImage" src="" alt="Зображення" class="img-fluid">
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmModalLabel">Підтвердження замовлення</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" ></button>
                </div>
                <div class="modal-body">
                    Ви впевнені, що хочете замовити вибрані товари?
                    <div id="orderDetails"></div>
                    <div id="orderTotal"></div>
                    <div class="mb-3">
                        <label for="customerName" class="form-label">Ім'я</label>
                        <input type="text" class="form-control" id="customerName" name="customerName" required>
                    </div>
                    <div class="mb-3">
                        <label for="customerPhone" class="form-label">Номер телефону</label>
                        <input type="tel" class="form-control" id="customerPhone" name="customerPhone" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Скасувати</button>
                    <button type="button" class="btn btn-primary" id="confirmOrder">Підтвердити</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productModalLabel">Деталі товару</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <img id="modalProductImage" src="" alt="Фото товару" class="img-fluid mb-3" style="max-width: 100%;">
                        <p><strong>Ціна:</strong> <span id="modalProductPrice"></span></p>
                        <p><strong>Категорія:</strong> <span id="modalProductCategory"></span></p>
                    </div>
                    <p><strong>Назва:</strong> <span id="modalProductName"></span></p>
                    <p><strong>Опис:</strong> <span id="modalProductDescription"></span></p>
                    <p><strong>Артикул:</strong> <span id="modalProductArticle"></span></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const CURRENCY = 'грн.';

        // Function to handle image modal display
        document.querySelectorAll('.zoom').forEach(img => {
            img.addEventListener('click', function() {
                const modalImage = document.getElementById('modalImage');
                modalImage.src = this.src;
                const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
                imageModal.show();
            });
        });

        // Function to calculate and display order details
        function updateOrderDetails() {
            const orderDetails = document.getElementById('orderDetails');
            const orderTotal = document.getElementById('orderTotal');
            orderDetails.innerHTML = '';
            let totalAmount = 0;

            document.querySelectorAll('input[type="number"]').forEach(input => {
                if (input.value > 0) {
                    const row = input.closest('tr');
                    const productName = row.querySelector('td:nth-child(2)').innerText;
                    const price = parseFloat(row.querySelector('td:nth-child(6)').innerText.replace(` ${CURRENCY}`, '').replace(',', ''));
                    const quantity = input.value;
                    const total = price * quantity;
                    totalAmount += total;
                    orderDetails.innerHTML += `<p>${productName}: ${quantity} x ${price.toFixed(2)} ${CURRENCY} = ${total.toFixed(2)} ${CURRENCY}</p>`;
                }
            });

            orderTotal.innerHTML = `<p><strong>Всього: ${totalAmount.toFixed(2)} ${CURRENCY}</strong></p>`;
        }

        document.querySelector('button[data-bs-target="#confirmModal"]').addEventListener('click', updateOrderDetails);

        // Function to send order details to the server
        document.getElementById('confirmOrder').addEventListener('click', function() {
            const inputs = document.querySelectorAll('input[type="number"]');
            let orderMessage = 'Нове замовлення:\n';

            inputs.forEach(input => {
                if (input.value > 0) {
                    const row = input.closest('tr');
                    const productName = row.querySelector('td:nth-child(2)').innerText;
                    const price = parseFloat(row.querySelector('td:nth-child(6)').innerText.replace(` ${CURRENCY}`, '').replace(',', ''));
                    const quantity = input.value;
                    const total = price * quantity;
                    orderMessage += `${productName}: ${quantity} x ${price.toFixed(2)} ${CURRENCY} = ${total.toFixed(2)} ${CURRENCY}\n`;
                }
            });

            const customerName = document.getElementById('customerName').value;
            const customerPhone = document.getElementById('customerPhone').value;
            orderMessage += `Ім'я: ${customerName}\nТелефон: ${customerPhone}\n`;

            fetch('send_telegram.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: orderMessage })
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.text();
            })
            .then(data => {
                console.log(data);
                document.getElementById('orderForm').submit();
            })
            .catch(error => console.error('Error:', error));
        });

        // Prevent default form submission and handle via AJAX
        document.getElementById('orderForm').addEventListener('submit', function(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);

            fetch(form.action, {
                method: form.method,
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.text();
            })
            .then(data => {
                console.log(data);
                window.location.href = 'catalog.php';
            })
            .catch(error => console.error('Error:', error));
        });

        document.querySelectorAll('.product-name').forEach(name => {
            name.addEventListener('click', function() {
                const modal = document.getElementById('productModal');
                const photo = this.dataset.photo.split(',')[0]; // Use the first photo if multiple exist
                document.getElementById('modalProductImage').src = photo ? photo : 'placeholder.jpg';
                document.getElementById('modalProductName').textContent = this.dataset.name;
                document.getElementById('modalProductDescription').textContent = this.dataset.description;
                document.getElementById('modalProductArticle').textContent = this.dataset.article;
                document.getElementById('modalProductCategory').textContent = this.dataset.category;
                document.getElementById('modalProductPrice').textContent = this.dataset.price;
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
