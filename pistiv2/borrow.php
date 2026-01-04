<?php
session_start();
include "db.php";

if (isset($_POST['borrow'])) {
    $student_id = $_POST['student_id'];
    $book_id = $_POST['book_id'];
    $date = date('Y-m-d');
    
    // 1. Insert transaction
    $stmt = $conn->prepare("INSERT INTO transactions (student_id, book_id, date_borrowed) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $student_id, $book_id, $date);
    $stmt->execute();
    
    // 2. Update book status
    $conn->query("UPDATE books SET status='Borrowed' WHERE book_id=$book_id");
    
    header("Location: index.php");
    exit();
}
?>

<form method="POST">
    <h3>Borrow Book</h3>
    Student ID: <input type="number" name="student_id" required>
    Book ID: <input type="number" name="book_id" required>
    <button name="borrow">Borrow</button>
</form>