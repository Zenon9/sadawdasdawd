<?php
session_start();
include "db.php";

// Check admin login
if (!isset($_SESSION['username']) || $_SESSION['username'] != "admin") {
    header("Location: login.php");
    exit();
}

// HANDLE QUANTITY ADJUSTMENT
if (isset($_POST['adjust_qty'])) {
    $book_id = intval($_POST['book_id']);
    $adjustment = intval($_POST['adjustment']);
    
    // Get current quantity
    $result = $conn->query("SELECT volume, book_name FROM books WHERE book_id=$book_id");
    if ($result->num_rows > 0) {
        $book = $result->fetch_assoc();
        $new_qty = $book['volume'] + $adjustment;
        
        // Prevent negative quantity
        if ($new_qty < 0) $new_qty = 0;
        
        // Update database
        $conn->query("UPDATE books SET volume=$new_qty WHERE book_id=$book_id");
        
        // Update status if quantity reaches 0
        if ($new_qty == 0) {
            $conn->query("UPDATE books SET status='Out of Stock' WHERE book_id=$book_id");
        } elseif ($new_qty > 0 && $book['status'] == 'Out of Stock') {
            $conn->query("UPDATE books SET status='Available' WHERE book_id=$book_id");
        }
        
        $_SESSION['message'] = "Updated '{$book['book_name']}': " . 
                              ($adjustment >= 0 ? "+" : "") . "$adjustment copies. New total: $new_qty";
    }
    header("Location: index.php");
    exit();
}

// ADD BOOK WITH DUPLICATE DETECTION (FIXED FOR LOWERCASE COLUMN NAMES)
if (isset($_POST['addItem'])) {
    $name     = trim($_POST['itemName']);
    $isbn     = trim($_POST['itemISBN'] ?? '');
    $author   = trim($_POST['itemAuthor'] ?? '');
    $category = trim($_POST['itemCategory']);
    $status   = trim($_POST['itemStatus']);
    $quantity = (int)$_POST['itemQuantity'];
    
    // DEBUG: Show what we're receiving
    echo "<pre>";
    echo "DEBUG - POST Data:\n";
    print_r($_POST);
    echo "\nProcessed values:\n";
    echo "Name: $name\n";
    echo "ISBN: $isbn\n";
    echo "Author: $author\n";
    echo "Category: $category\n";
    echo "Status: $status\n";
    echo "Quantity: $quantity\n";
    echo "</pre>";
    // Remove this debug output after testing
    
    // Validate ISBN format (13 digits) - optional
    if (!empty($isbn) && !preg_match('/^\d{10,13}$/', $isbn)) {
        $_SESSION['error'] = "ISBN must be 10 or 13 digits";
        header("Location: index.php");
        exit();
    }
    
    // Check if book already exists (check by ISBN if provided, otherwise by name+author)
    if (!empty($isbn)) {
        $check_sql = "SELECT book_id, book_name, volume, status FROM books WHERE isbn = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $isbn);
    } else {
        $check_sql = "SELECT book_id, book_name, volume, status FROM books 
                      WHERE LOWER(book_name) = LOWER(?) 
                      AND LOWER(author) = LOWER(?) 
                      AND LOWER(category) = LOWER(?)";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("sss", $name, $author, $category);
    }
    
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Book exists - UPDATE quantity instead of INSERT
        $existing = $check_result->fetch_assoc();
        $new_qty = $existing['volume'] + $quantity;
        
        // Determine new status
        $new_status = $existing['status'];
        if ($new_qty > 0 && $existing['status'] == 'Out of Stock') {
            $new_status = 'Available';
        }
        
        $update_sql = "UPDATE books SET volume = ?, status = ? WHERE book_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("isi", $new_qty, $new_status, $existing['book_id']);
        
        if ($update_stmt->execute()) {
            $_SESSION['message'] = "Book already exists! Added $quantity copies to '{$existing['book_name']}'. Total now: $new_qty copies.";
        } else {
            $_SESSION['error'] = "Error updating book: " . $conn->error;
        }
        $update_stmt->close();
    } else {
        // Book doesn't exist - INSERT new
        $stmt = $conn->prepare("INSERT INTO books (book_name, isbn, author, category, status, volume) VALUES (?, ?, ?, ?, ?, ?)");
        
        // DEBUG: Check if prepare worked
        if ($stmt === false) {
            echo "DEBUG: Prepare failed! Error: " . $conn->error;
            exit();
        }
        
        $stmt->bind_param("sssssi", $name, $isbn, $author, $category, $status, $quantity);
        
        // DEBUG: Show the SQL being executed
        echo "DEBUG: SQL: INSERT INTO books (book_name, isbn, author, category, status, volume) VALUES ('$name', '$isbn', '$author', '$category', '$status', $quantity)";
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "New book '$name' added successfully!";
        } else {
            $_SESSION['error'] = "Error adding book: " . $conn->error;
            echo "DEBUG: Execute failed! Error: " . $stmt->error;
        }
        $stmt->close();
    }
    $check_stmt->close();
    
    // Remove the debug exit() below after testing
    // exit(); // Remove this line after checking debug output
    
    header("Location: index.php");
    exit();
}

// Fetch books with their latest borrower (if any)
$sql = "
    SELECT b.*, 
           s.student_name, 
           s.student_id, 
           s.course, 
           s.year,
           t.transaction_id,
           t.date_borrowed
    FROM books b
    LEFT JOIN transactions t ON b.book_id = t.book_id AND t.date_returned IS NULL
    LEFT JOIN students s ON t.student_id = s.student_id
    ORDER BY b.book_id DESC
";
$books = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Manage Books</title>
<link rel="stylesheet" href="style.css">
<style>
/* Status colors */
.status-available { color: #2ecc71; }
.status-borrowed { color: #e67e22; }
.status-defective { color: #95a5a6; }
.status-out-of-stock { color: #e74c3c; }

/* Message alerts */
.alert {
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
    font-weight: bold;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style>
</head>
<body>
<div class="container">

    <aside class="sidebar">
        <h2>ADMIN</h2>
        <ul>
            <li><a class="nav-item active">Books</a></li>
            <li><a href="borrow.php">Borrow / Return</a></li>
            <li><a href="transaction.php">Transaction History</a></li>
            <li><a href="logout.php">➜ Logout</a></li>
        </ul>
    </aside>

    <main class="main">
        <header><h1>Manage Books</h1></header>
        
        <!-- Display messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Add Book Form -->
        <form method="POST" class="item-form">
            <h3>Add New Book to Inventory</h3>
            <input type="text" name="itemName" placeholder="Book Name" required>
            <input type="text" name="itemAuthor" placeholder="Author" required>
            <input type="text" name="itemISBN" placeholder="ISBN (10 or 13 digits, optional)" maxlength="13">
            <input type="text" name="itemCategory" placeholder="Category" required>
            <select name="itemStatus" required>
                <option value="Available">Available</option>
                <option value="Defective">Defective</option>
                <option value="Out of Stock">Out of Stock</option>
            </select>
            <input type="number" name="itemQuantity" min="1" value="1" placeholder="Quantity" required>
            <button type="submit" name="addItem">Add Book to Inventory</button>
        </form>
        
        <input type="text" id="searchInput" placeholder="Search books..." onkeyup="searchTable()">
        
        <!-- Books Table -->
        <table id="itemTable">
            <thead>
                <tr>
                    <th>Book ID</th>
                    <th>Book Name</th>
                    <th>Author</th>
                    <th>ISBN</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Quantity</th>
                    <th>Adjust Qty</th>
                    <th>Current Borrower</th>
                    <th>Borrowed Since</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $books->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['book_id'] ?></td>
                    <td><?= htmlspecialchars($row['book_name']) ?></td>
                    <td><?= !empty($row['author']) ? htmlspecialchars($row['author']) : '—' ?></td>
                    <td><?= !empty($row['isbn']) ? htmlspecialchars($row['isbn']) : '—' ?></td>
                    <td><?= htmlspecialchars($row['category']) ?></td>
                    <td class="status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>">
                        <?= htmlspecialchars($row['status']) ?>
                    </td>
                    <td>
                        <span class="qty-display"><?= $row['volume'] ?></span>
                    </td>
                    <td>
                        <form method="POST" class="qty-form">
                            <input type="hidden" name="book_id" value="<?= $row['book_id'] ?>">
                            <div class="quantity-controls">
                                <button type="submit" name="adjust_qty" class="qty-btn reduce-btn" 
                                        onclick="this.form.adjustment.value = -1">
                                    −
                                </button>    
                                <input type="number" name="adjustment" value="1" 
                                       min="-<?= $row['volume'] ?>" max="99" 
                                       class="qty-input" 
                                       title="Enter positive to add, negative to reduce">
                                
                                <button type="submit" name="adjust_qty" class="qty-btn add-btn"
                                        onclick="this.form.adjustment.value = 1">
                                    +
                                </button>
                            </div>
                            <small style="display:block; margin-top:3px; color:#666; font-size:12px;">
                                Click +/- buttons
                            </small>
                        </form>
                    </td>
                    <td>
                        <?= $row['student_name'] 
                            ? htmlspecialchars($row['student_name']) . " (" . $row['student_id'] . ")" 
                            : "—" ?>
                    </td>
                    <td>
                        <?= $row['date_borrowed'] ?? "—" ?>
                    </td>
                    <td>
                        <a href="edit.php?book_id=<?= $row['book_id'] ?>">Edit</a>
                        <?php if ($row['status'] == 'Available' && $row['volume'] > 0): ?>
                            <a href="borrow.php?book_id=<?= $row['book_id'] ?>">Borrow</a>
                        <?php elseif ($row['status'] == 'Borrowed'): ?>
                            <a href="return.php?transaction_id=<?= $row['transaction_id'] ?>">Return</a>
                        <?php endif; ?>
                        <a href="delete.php?book_id=<?= $row['book_id'] ?>" 
                           onclick="return confirm('Delete ALL <?= $row['volume'] ?> copies?');">Delete All</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </main>
</div>

<script>
function searchTable() {
    let input = document.getElementById("searchInput").value.toLowerCase();
    document.querySelectorAll("#itemTable tbody tr").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(input) ? "" : "none";
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize quantity inputs
    document.querySelectorAll('.qty-input').forEach(input => {
        input.value = '1';
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const form = this.closest('form');
                const adjustment = parseInt(this.value);
                const currentQty = parseInt(this.closest('tr').querySelector('.qty-display').textContent);
                if (isNaN(adjustment) || adjustment === 0) {
                    alert("Please enter a non-zero number");
                    this.focus();
                    return;
                }
                
                const newQty = currentQty + adjustment;
                if (newQty < 0) {
                    alert(`Cannot reduce below 0! Current: ${currentQty}`);
                    this.focus();
                    return;
                }
                form.submit();
            }
        });
    });
    
    // ISBN input validation
    const isbnInput = document.querySelector('input[name="itemISBN"]');
    if (isbnInput) {
        isbnInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
            if (this.value.length > 13) {
                this.value = this.value.slice(0, 13);
            }
        });
    }
});
</script>
</body>
</html>