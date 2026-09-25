<?php
require_once __DIR__ . '/../config/database.php';

function cart_add($product_id, $qty = 1) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $product_id = (int)$product_id;
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $qty;
    } else {
        $_SESSION['cart'][$product_id] = $qty;
    }
}

function cart_update($product_id, $qty) {
    $product_id = (int)$product_id;
    $qty = max(1, (int)$qty);
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] = $qty;
    }
}

function cart_remove($product_id) {
    unset($_SESSION['cart'][(int)$product_id]);
}

function cart_clear() {
    $_SESSION['cart'] = [];
}

function cart_items() {
    if (empty($_SESSION['cart'])) {
        return [];
    }
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = get_db()->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND status = 'active'");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();

    $items = [];
    foreach ($products as $product) {
        $qty = $_SESSION['cart'][$product['id']] ?? 1;
        $items[] = [
            'product' => $product,
            'qty' => $qty,
            'line_total' => $product['price'] * $qty,
        ];
    }
    return $items;
}

function cart_count() {
    if (empty($_SESSION['cart'])) {
        return 0;
    }
    return array_sum($_SESSION['cart']);
}

function cart_subtotal() {
    $total = 0;
    foreach (cart_items() as $item) {
        $total += $item['line_total'];
    }
    return $total;
}
