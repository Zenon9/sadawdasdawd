<?php
session_start();
include "db.php";

// Check admin login
if (!isset($_SESSION['username']) || $_SESSION['username'] != "admin") {
    header("Location: login.php");
    exit();
}

// ---------- BORROW BOOK ----------
if (isset($_POST['borrow'])) {
    $book_id    = intval($_POST['book_id']);
    $student_id = intval($_POST['student_id']);

    // Check book exists and is available
    $stmt = $conn->prepare("SELECT book_name, volume, status FROM books WHERE book_id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $bookResult = $stmt->get_result();
    if ($bookResult->num_rows == 0) {
        $_SESSION['error'] = "Book not found.";
        header("Location: borrow.php");
        exit();
    }
    $book = $bookResult->fetch_assoc();
    $stmt->close();

    if ($book['volume'] <= 0 || $book['status'] == 'Out of Stock') {
        $_SESSION['error'] = "Book '{$book['book_name']}' is not available for borrowing.";
        header("Location: borrow.php");
        exit();
    }

    // Check student exists
    $stmt = $conn->prepare("SELECT student_name FROM students WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $studentResult = $stmt->get_result();
    if ($studentResult->num_rows == 0) {
        $_SESSION['error'] = "Student not found.";
        header("Location: borrow.php");
        exit();
    }
    $student = $studentResult->fetch_assoc();
    $stmt->close();

    // Insert transaction
    $stmt = $conn->prepare("INSERT INTO transactions (book_id, student_id, date_borrowed) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $book_id, $student_id);
    if ($stmt->execute()) {
        // Reduce book quantity
        $new_qty = $book['volume'] - 1;
        $new_status = $new_qty > 0 ? 'Available' : 'Out of Stock';
        $updateStmt = $conn->prepare("UPDATE books SET volume=?, status=? WHERE book_id=?");
        $updateStmt->bind_param("isi", $new_qty, $new_status, $book_id);
        $updateStmt->execute();
        $updateStmt->close();

        $_SESSION['message'] = "Book '{$book['book_name']}' borrowed by {$student['student_name']} successfully!";
        header("Location: borrow.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to borrow book: " . $stmt->error;
    }
    $stmt->close();
}

// ---------- RETURN BOOK ----------
if (isset($_POST['return'])) {
    $transaction_id = intval($_POST['transaction_id']);

    // Fetch transaction
    $stmt = $conn->prepare("
        SELECT t.book_id, b.book_name, b.volume, b.status
        FROM transactions t
        JOIN books b ON t.book_id = b.book_id
        WHERE t.transaction_id = ? AND t.date_returned IS NULL
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
        $_SESSION['error'] = "Transaction not found or already returned.";
        header("Location: borrow.php");
        exit();
    }
    $trans = $result->fetch_assoc();
    $stmt->close();

    // Update transaction to returned
    $stmt = $conn->prepare("UPDATE transactions SET date_returned = NOW() WHERE transaction_id = ?");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $stmt->close();

    // Increase book quantity and update status
    $new_qty = $trans['volume'] + 1;
    $new_status = 'Available';
    $stmt = $conn->prepare("UPDATE books SET volume = ?, status = ? WHERE book_id = ?");
    $stmt->bind_param("isi", $new_qty, $new_status, $trans['book_id']);
    $stmt->execute();
    $stmt->close();

    $_SESSION['message'] = "Book '{$trans['book_name']}' returned successfully!";
    header("Location: borrow.php");
    exit();
}

// Fetch all books
$books = $conn->query("SELECT book_id, book_name, author, isbn, category, volume, status FROM books ORDER BY book_name ASC");

// Fetch all students
$students = $conn->query("SELECT student_id, student_name, course, year FROM students ORDER BY student_name ASC");

// Fetch all borrowed books
$borrowed = $conn->query("
    SELECT t.transaction_id, t.date_borrowed, b.book_name, b.author, b.isbn, b.category,
           s.student_name, s.course, s.year
    FROM transactions t
    JOIN books b ON t.book_id = b.book_id
    JOIN students s ON t.student_id = s.student_id
    WHERE t.date_returned IS NULL
    ORDER BY t.date_borrowed ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Borrow / Return Books — Admin</title>
<link rel="stylesheet" href="style.css">
<style>
.borrow-btn { background: linear-gradient(90deg, #0a7a50, #066a41); color: #fff; width:100%; padding:5px 0; border:none; border-radius:6px; cursor:pointer; font-weight:bold; }
.borrow-btn:hover { background: linear-gradient(90deg, #066a41, #045a33); }
.return-btn { background: linear-gradient(90deg, #e74c3c, #c0392b); color: #fff; border:none; padding:5px 0; border-radius:6px; cursor:pointer; font-weight:bold; }
.return-btn:hover { background: linear-gradient(90deg, #c0392b, #a93226); }
.borrow-select { width:100%; padding:5px; border-radius:6px; margin-bottom:3px; }
</style>
</head>
<body>
<div class="container">

    <aside class="sidebar">
        <h2>ADMIN</h2>
        <ul>
            <li><a href="index.php">Books</a></li>
            <li><a class="nav-item active">Borrow / Return</a></li>
            <li><a href="transaction.php">Transaction History</a></li>
            <li><a href="logout.php">➜ Logout</a></li>
        </ul>
    </aside>

    <main class="main">
        <header><h1>Borrow / Return Books</h1></header>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <h2>Borrow Books</h2>
        <input type="text" id="borrowSearch" placeholder="Search books to borrow..." onkeyup="searchTable('borrowSearch', 'borrowTable')">
        <table id="borrowTable">
            <thead>
                <tr>
                    <th>Book Name</th>
                    <th>Author</th>
                    <th>ISBN</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Quantity</th>
                    <th>Borrow</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($book = $books->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($book['book_name']) ?></td>
                    <td><?= htmlspecialchars($book['author'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($book['isbn'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($book['category']) ?></td>
                    <td class="status-<?= strtolower(str_replace(' ', '-', $book['status'])) ?>"><?= htmlspecialchars($book['status']) ?></td>
                    <td><?= $book['volume'] ?></td>
                    <td>
                        <?php if ($book['volume'] > 0 && $book['status'] != 'Out of Stock'): ?>
                        <form method="POST">
                            <input type="hidden" name="book_id" value="<?= $book['book_id'] ?>">
                            <select name="student_id" class="borrow-select" required>
                                <option value="">Select Student</option>
                                <?php $students->data_seek(0); while ($s = $students->fetch_assoc()): ?>
                                    <option value="<?= $s['student_id'] ?>"><?= htmlspecialchars($s['student_name']) ?> — <?= htmlspecialchars($s['course']) ?> Year <?= $s['year'] ?></option>
                                <?php endwhile; ?>
                            </select>
                            <button type="submit" name="borrow" class="borrow-btn">Borrow</button>
                        </form>
                        <?php else: ?> — <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>

        <h2 style="margin-top:30px;">Return Books</h2>
        <input type="text" id="returnSearch" placeholder="Search borrowed books..." onkeyup="searchTable('returnSearch', 'returnTable')">
        <table id="returnTable">
            <thead>
                <tr>
                    <th>Book Name</th>
                    <th>Author</th>
                    <th>ISBN</th>
                    <th>Category</th>
                    <th>Student</th>
                    <th>Course / Year</th>
                    <th>Borrowed Since</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $borrowed->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['book_name']) ?></td>
                    <td><?= htmlspecialchars($row['author'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($row['isbn'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($row['category']) ?></td>
                    <td><?= htmlspecialchars($row['student_name']) ?></td>
                    <td><?= htmlspecialchars($row['course']) ?> / <?= $row['year'] ?></td>
                    <td><?= $row['date_borrowed'] ?></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Mark this book as returned?');">
                            <input type="hidden" name="transaction_id" value="<?= $row['transaction_id'] ?>">
                            <button type="submit" name="return" class="return-btn">Return</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>

    </main>
</div>

<script>
function searchTable(inputId, tableId) {
    let input = document.getElementById(inputId).value.toLowerCase();
    document.querySelectorAll("#" + tableId + " tbody tr").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(input) ? "" : "none";
    });
}
</script>

</body>
</html>
