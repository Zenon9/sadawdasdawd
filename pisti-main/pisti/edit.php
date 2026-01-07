<?php
session_start();
include "db.php";

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// REQUIRE book_id
if (!isset($_GET['book_id'])) {
    header("Location: index.php");
    exit();
}

$book_id = intval($_GET['book_id']);

// ===============================
// GET BOOK INFO
// ===============================
$stmt = $conn->prepare("SELECT * FROM books WHERE book_id=? LIMIT 1");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();

if (!$book) {
    die("Book not found.");
}

// ===============================
// GET LATEST TRANSACTION FOR BOOK
// ===============================
$stmt2 = $conn->prepare("
    SELECT * FROM transactions 
    WHERE book_id=? 
    ORDER BY Transaction_id DESC 
    LIMIT 1
");
$stmt2->bind_param("i", $book_id);
$stmt2->execute();
$transaction = $stmt2->get_result()->fetch_assoc();

// If no transaction exists, create blank
if (!$transaction) {
    $transaction = [
        "Transaction_id" => null,
        "quantity" => $book["volume"]
    ];
}

// ===============================
// UPDATE ITEM
// ===============================
if (isset($_POST['updateItem'])) {

    $name     = $_POST['itemName'];
    $category = $_POST['itemCategory'];
    $status   = $_POST['itemStatus'];
    $quantity = $_POST['itemQuantity'];

  

    // -------- UPDATE BOOK TABLE --------
    $updateBook = $conn->prepare("
        UPDATE books 
        SET book_name=?, category=?, status=?, volume=?
        WHERE book_id=?
    ");
    $updateBook->bind_param("sssii", $name, $category, $status, $quantity, $book_id);
    $updateBook->execute();

    // -------- UPDATE ONLY LATEST TRANSACTION --------
    if ($transaction['Transaction_id']) {

        $trans_id = $transaction['Transaction_id'];

        $updateTrans = $conn->prepare("
            UPDATE transaction 
            SET quantity=?, book_name=?
            WHERE Transaction_id=?
        ");

        $updateTrans->bind_param("sissisi", 
           
            $quantity, 
            $name,
            $trans_id
        );

        $updateTrans->execute();
    }

    header("Location: index.php?updated=1");
    exit();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Item</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

    <aside class="sidebar">
        <h2>ADMIN</h2>
        <ul>
             <li><a href="index.php">Home</a></li>
            <li><a href="borrow.php">Borrow Books</a></li>
            <li><a href="TransactionHistory.php">Transaction History</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>

    <main class="main">

        <header class="topbar">
            <h1>Edit Item</h1>
        </header>

        <section class="page-inner">

            <form method="POST" class="item-form">

               
                <label>Item Name:</label>
                <input type="text" name="itemName" 
                       value="<?= htmlspecialchars($book['book_name']) ?>" required>

                <label>Category:</label>
                <input type="text" name="itemCategory"
                       value="<?= htmlspecialchars($book['category']) ?>" required>

                <label>Status:</label>
                <select name="itemStatus">
                    <option <?= $book['status']=="Usable / Available" ? "selected" : "" ?>>Usable / Available</option>
                    <option <?= $book['status']=="Borrowed" ? "selected" : "" ?>>Borrowed</option>
                    <option <?= $book['status']=="Broken / Defective" ? "selected" : "" ?>>Broken / Defective</option>
                </select>

                <label>Quantity:</label>
                <input type="number" name="itemQuantity" min="1"
                       value="<?= htmlspecialchars($book['volume']) ?>" required>

                <button type="submit" name="updateItem">Save Changes</button>
                <a href="index.php">Cancel</a>

            </form>

        </section>
    </main>

</div>

</body>
</html>