<?php
session_start();
include 'includes/db.php';
include 'includes/header.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

$query = "SELECT id, username, email, role FROM users";
$result = $conn->query($query);
?>

<h2>Manage Users</h2>
<table border="1">
    <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Email</th>
        <th>Role</th>
        <th>Action</th>
    </tr>
    <?php while ($user = $result->fetch_assoc()) : ?>
        <tr>
            <td><?= $user['id'] ?></td>
            <td><?= $user['username'] ?></td>
            <td><?= $user['email'] ?></td>
            <td><?= $user['role'] ?></td>
            <td><a href="delete_user.php?id=<?= $user['id'] ?>">Delete</a></td>
        </tr>
    <?php endwhile; ?>
</table>

<?php include 'includes/footer.php'; ?>
