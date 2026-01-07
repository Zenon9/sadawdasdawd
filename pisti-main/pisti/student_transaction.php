<?php
session_start();
include "db.php";

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Load all transactions ordered by Transaction_id DESC
$transactions = $conn->query("SELECT * FROM transaction ORDER BY Transaction_id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transaction History</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <h2>STUDENT</h2>
        <ul>
            <li><a href="Transaction.php" class="active">📜 Transaction History</a></li>
            <li><a href="logout.php">➜] Logout</a></li>
        </ul>
    </aside>

    <main class="main">
        <header class="topbar">
            <h1>Transaction History</h1>
        </header>

        <section class="page-inner">

            <!-- ⭐ SEARCH BAR ⭐ -->
            <input type="text" id="searchInput" 
                   placeholder="Search transactions..." 
                   onkeyup="searchTable()" 
                   style="padding:10px; width:300px; margin:15px 0; border:1px solid #777; border-radius:5px;">

            <table id="transactionTable">
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Date</th>
                        <th>Student Name</th>
                        <th>Student ID</th>
                        <th>Course</th>
                        <th>Year</th>
                        <th>Book Name</th>
                        <th>Quantity</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($transactions->num_rows > 0): ?>
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
                    <?php else: ?>
                        <tr><td colspan="8">No transactions found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

<!-- ⭐ LIVE SEARCH SCRIPT ⭐ -->
<script>
function searchTable() {
    let input = document.getElementById("searchInput").value.toLowerCase();
    let rows = document.querySelectorAll("#transactionTable tbody tr");

    rows.forEach(row => {
        let rowText = row.innerText.toLowerCase();
        row.style.display = rowText.includes(input) ? "" : "none";
    });
}
</script>

</body>
</html>