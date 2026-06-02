<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['akun_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $lama = $_POST['password_lama'];
    $baru = $_POST['password_baru'];

    $stmt = $pdo->prepare("SELECT * FROM akun WHERE id=?");
    $stmt->execute([$_SESSION['akun_id']]);

    $akun = $stmt->fetch();

    if (!$akun || !password_verify($lama, $akun['kata_sandi'])) {

        $error = "Password lama salah.";

    } else {

        $hash = password_hash($baru, PASSWORD_DEFAULT);

        $update = $pdo->prepare("
            UPDATE akun
            SET kata_sandi=?
            WHERE id=?
        ");

        $update->execute([
            $hash,
            $_SESSION['akun_id']
        ]);

        $success = "Password berhasil diubah.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Ubah Password</title>
</head>
<body>

<h2>Ubah Password</h2>

<?php if($error): ?>
<p style="color:red;"><?= $error ?></p>
<?php endif; ?>

<?php if($success): ?>
<p style="color:green;"><?= $success ?></p>
<?php endif; ?>

<form method="POST">

    <input type="password"
           name="password_lama"
           placeholder="Password Lama"
           required>

    <br><br>

    <input type="password"
           name="password_baru"
           placeholder="Password Baru"
           required>

    <br><br>

    <button type="submit">
        Simpan
    </button>

</form>

</body>
</html>