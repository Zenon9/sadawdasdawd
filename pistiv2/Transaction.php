<?php
session_start();
include "db.php";

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$transactions = $conn->query("SELECT * FROM transaction ORDER BY Transaction_id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Transaction History</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

    <aside class="sidebar">
        <h2>ADMIN</h2>
        <ul>
            <li><a href="index.php"> Items</a></li>
            <li><a href="Transaction.php" class="active"> Transaction History</a></li>
            <li><a href="logout.php">➜] Logout</a></li>
        </ul>
    </aside>

    <main class="main">

        <header class="topbar"><h1>Transaction History</h1></header>

        <section class="page-inner">

            <input type="text" id="searchInput" placeholder="Search..." 
                   onkeyup="searchTable()" class="search-bar">

            <table id="transactionTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Student ID</th>
                        <th>Course</th>
                        <th>Year</th>
                        <th>Book</th>
                        <th>Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $transactions->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['Transaction_id']) ?></td>
                            <td><?= htmlspecialchars($row['date']) ?></td>
                            <td><?= htmlspecialchars($row['student_name']) ?></td>
                            <td><?= htmlspecialchars($row['student_id']) ?></td>
                            <td><?= htmlspecialchars($row['course']) ?></td>
                            <td><?= htmlspecialchars($row['year']) ?></td>
                            <td><?= htmlspecialchars($row['book_name']) ?></td>
                            <td><?= htmlspecialchars($row['quantity']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

        </section>

    </main>
</div>

<script>
function searchTable() {
    let input = document.getElementById("searchInput").value.toLowerCase();
    let rows = document.querySelectorAll("#transactionTable tbody tr");

    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(input) ? "" : "none";
    });
}
</script>

</body>
</html>
