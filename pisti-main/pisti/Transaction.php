<?php
session_start();
include "db.php";

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Fetch transactions with book and student info
$transactions = $conn->query("
    SELECT 
        t.transaction_id,
        t.date_borrowed,
        t.date_returned,
        b.book_name,
        s.student_name,
        s.student_id,
        s.course,
        s.year
    FROM transactions t
    JOIN books b ON t.book_id = b.book_id
    JOIN students s ON t.student_id = s.student_id
    ORDER BY t.transaction_id DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transaction History</title>
<link rel="stylesheet" href="style.css">
<style>
#transactionTable th, #transactionTable td { font-size:13px; padding:8px; }
#transactionTable tbody tr:hover { background:#e2f5ea; }
.status-returned { color: #27ae60; font-weight:bold; }
.status-borrowed { color: #e67e22; font-weight:bold; }
.search-bar { padding:10px; width:100%; max-width:300px; margin-bottom:15px; border-radius:6px; border:1px solid #ccc; }
</style>
</head>
<body>
<div class="container">

    <aside class="sidebar">
        <h2>ADMIN</h2>
        <ul>
            <li><a href="index.php">Books</a></li>
            <li><a href="borrow.php">Borrow / Return</a></li>
            <li><a href="transaction.php" class="active">Transaction History</a></li>
            <li><a href="logout.php">➜ Logout</a></li>
        </ul>
    </aside>

    <main class="main">
        <header><h1>Transaction History</h1></header>

        <input type="text" id="searchInput" placeholder="Search..." onkeyup="searchTable()" class="search-bar">

        <table id="transactionTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Book</th>
                    <th>Student</th>
                    <th>Student ID</th>
                    <th>Course</th>
                    <th>Year</th>
                    <th>Date Borrowed</th>
                    <th>Date Returned</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $transactions->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['transaction_id'] ?></td>
                    <td><?= htmlspecialchars($row['book_name']) ?></td>
                    <td><?= htmlspecialchars($row['student_name']) ?></td>
                    <td><?= $row['student_id'] ?></td>
                    <td><?= htmlspecialchars($row['course']) ?></td>
                    <td><?= $row['year'] ?></td>
                    <td><?= $row['date_borrowed'] ?></td>
                    <td><?= $row['date_returned'] ?? '—' ?></td>
                    <td class="<?= $row['date_returned'] ? 'status-returned' : 'status-borrowed' ?>">
                        <?= $row['date_returned'] ? 'Returned' : 'Borrowed' ?>
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
    document.querySelectorAll("#transactionTable tbody tr").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(input) ? "" : "none";
    });
}
</script>
</body>
</html>
