<?php
session_start();
include "db.php";

// Only allow admin
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$admin = $_SESSION['username'];
$admin_id = 0; // Fake student_id for admin transactions

// ===============================
// ADD NEW ITEM
// ===============================
if (isset($_POST['addItem'])) {

    $name     = $_POST['itemName'];
    $category = $_POST['itemCategory'];
    $status   = $_POST['itemStatus'];
    $quantity = $_POST['itemQuantity'];

    // Insert item in books table
    $stmt = $conn->prepare("INSERT INTO books (book_name, category, status, volume) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $name, $category, $status, $quantity);
    $stmt->execute();
    $book_id = $conn->insert_id;
    $stmt->close();

    // Log transaction
    $date = date("Y-m-d");
    $log = $conn->prepare("INSERT INTO transaction (student_name, student_id, date, quantity, book_name, book_id) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    $log->bind_param("sisisi", $admin, $admin_id, $date, $quantity, $name, $book_id);
    $log->execute();
    $log->close();

    header("Location: manage_items.php");
    exit();
}

// LOAD ALL ITEMS
$items = $conn->query("SELECT * FROM books ORDER BY book_id DESC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Items</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

    <aside class="sidebar">
        <h2>ADMIN</h2>
        <ul>
            <li><a href="manage_items.php" class="nav-item active"> Manage Items</a></li>
            <li><a href="TransactionHistory.php" class="nav-item"> Transaction History</a></li>
            <li><a href="logout.php" class="nav-item">Logout</a></li>
        </ul>
    </aside>

    <main class="main">
        <header class="topbar"><h1>Manage Items</h1></header>

        <section class="page-inner">

            <!-- ADD ITEM FORM -->
            <form method="POST" class="item-form">
                <input type="text" name="itemName" placeholder="Item Name" required>
                <input type="text" name="itemCategory" placeholder="Category" required>
                <select name="itemStatus">
                    <option value="Usable / Available">Usable / Available</option>
                    <option value="Borrowed">Borrowed</option>
                    <option value="Broken / Defective">Broken / Defective</option>
                </select>
                <input type="number" name="itemQuantity" placeholder="Quantity" min="1" required>
                <button type="submit" name="addItem">Add Item</button>
            </form>

            <!-- ITEMS TABLE -->
            <table id="itemTable">
                <thead>
                    <tr>
                        <th>Book Name</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Quantity</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php while ($row = $items->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['book_name']) ?></td>
                        <td><?= htmlspecialchars($row['category']) ?></td>
                        <td><?= htmlspecialchars($row['status']) ?></td>
                        <td><?= htmlspecialchars($row['volume']) ?></td>

                        <td>
                            <a href="edit.php?book_id=<?= $row['book_id'] ?>" class="edit-btn">✏ Edit</a>
                            <a href="delete.php?book_id=<?= $row['book_id'] ?>" 
                               class="delete-btn" 
                               onclick="return confirm('Are you sure you want to delete this item?');">
                               🗑 Delete
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>

            </table>
        </section>
    </main>

</div>

</body>
</html>