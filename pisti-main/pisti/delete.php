<?php
session_start();
include "db.php";

// Check admin login
if (!isset($_SESSION['username']) || $_SESSION['username'] != "admin") {
    header("Location: login.php");
    exit();
}

// Check if book_id is provided
if (!isset($_GET['book_id'])) {
    header("Location: index.php");
    exit();
}

$book_id = intval($_GET['book_id']);

// 1. First check if book exists
$result = $conn->query("SELECT * FROM books WHERE book_id=$book_id");
if ($result->num_rows == 0) {
    $_SESSION['error'] = "Book not found!";
    header("Location: index.php");
    exit();
}

$book = $result->fetch_assoc();
$book_name = $book['book_name'];
$book_quantity = $book['volume'];

// 2. Check if book is currently borrowed
if ($book['status'] == 'Borrowed') {
    // Get the active transaction for this book
    $trans_sql = "SELECT t.*, s.student_name 
                  FROM transactions t
                  JOIN students s ON t.student_id = s.student_id
                  WHERE t.book_id = ? AND t.date_returned IS NULL 
                  LIMIT 1";
    $trans_stmt = $conn->prepare($trans_sql);
    $trans_stmt->bind_param("i", $book_id);
    $trans_stmt->execute();
    $transaction = $trans_stmt->get_result()->fetch_assoc();
    $trans_stmt->close();
    
    if ($transaction) {
        $_SESSION['error'] = "Cannot delete '$book_name' - Currently borrowed by {$transaction['student_name']}! Return it first.";
        header("Location: index.php");
        exit();
    }
}

// 3. Ask for confirmation via JavaScript in index.php
// The confirmation happens in index.php with onclick="return confirm(...)"
// If user confirms, we proceed here:

// 4. Delete related transactions first
$conn->query("DELETE FROM transactions WHERE book_id=$book_id");

// 5. Delete the book
if ($conn->query("DELETE FROM books WHERE book_id=$book_id")) {
    $_SESSION['message'] = "Successfully deleted '$book_name' ($book_quantity copies)";
} else {
    $_SESSION['error'] = "Error deleting book: " . $conn->error;
}

header("Location: index.php");
exit();
?>