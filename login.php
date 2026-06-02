<?php
session_start();
require_once 'koneksi.php';

// Redirect jika sudah login
if (isset($_SESSION['akun_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin.php' : 'index.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $sandi = $_POST['kata_sandi'] ?? '';

    if (empty($email) || empty($sandi)) {
        $error = 'Email dan kata sandi wajib diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM akun WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $akun = $stmt->fetch();

        if ($akun && password_verify($sandi, $akun['kata_sandi'])) {
            $_SESSION['akun_id']   = $akun['id'];
            $_SESSION['akun_nama'] = $akun['nama_lengkap'];
            $_SESSION['role']      = $akun['role'];
            header('Location: ' . ($akun['role'] === 'admin' ? 'admin.php' : 'index.php'));
            exit;
        } else {
            $error = 'Email atau kata sandi salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login – SMK FBAK</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --teal:    #0d7377;
    --teal-dk: #0a5c5f;
    --teal-lt: #14a2a8;
    --gold:    #e8a020;
    --gold-lt: #f5c35a;
    --navy:    #0d1f2d;
    --navy2:   #122535;
    --white:   #ffffff;
    --err:     #e53935;
    --text:    #1a2e3b;
    --muted:   #5a7080;
    --border:  #cde4e5;
  }
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'DM Sans', sans-serif;
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy2) 40%, #0d3d42 100%);
    min-height: 100vh;
    display: flex; align-items: center; justify-content: center;
    padding: 40px 16px;
    position: relative;
  }
  body::before {
    content: '';
    position: fixed; inset: 0;
    background:
      radial-gradient(ellipse 80% 60% at 10% 20%, rgba(13,115,119,.25) 0%, transparent 60%),
      radial-gradient(ellipse 60% 60% at 90% 80%, rgba(232,160,32,.1) 0%, transparent 60%);
    pointer-events: none;
  }
  .card {
    background: var(--white);
    border-radius: 20px;
    padding: 52px 44px;
    max-width: 420px; width: 100%;
    box-shadow: 0 32px 80px rgba(0,0,0,.35), 0 0 0 1px rgba(13,115,119,.15);
    position: relative; overflow: hidden;
  }
  .card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 5px;
    background: linear-gradient(90deg, var(--teal), var(--gold), var(--teal-lt));
  }
  .header { text-align: center; margin-bottom: 36px; }
  .logo-wrap {
    width: 80px; height: 80px; border-radius: 50%;
    background: linear-gradient(135deg, var(--teal), var(--teal-lt));
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 18px;
    font-family: 'Sora', sans-serif; font-weight: 800;
    font-size: 24px; color: var(--white);
    box-shadow: 0 8px 28px rgba(13,115,119,.45);
    letter-spacing: -1px;
  }
  h1 {
    font-family: 'Sora', sans-serif;
    font-size: 26px; font-weight: 800;
    color: var(--navy); margin-bottom: 6px;
  }
  .subtitle { color: var(--muted); font-size: 14px; }
  .alert-err {
    background: #fde8e8; border-left: 4px solid var(--err);
    color: var(--err); border-radius: 10px;
    padding: 12px 14px; margin-bottom: 22px; font-size: 14px;
  }
  .field { margin-bottom: 20px; }
  label {
    display: block; font-weight: 600; font-size: 13px;
    color: var(--text); margin-bottom: 7px;
    text-transform: uppercase; letter-spacing: .4px;
  }
  input[type=email], input[type=password] {
    width: 100%; padding: 12px 14px;
    border: 1.5px solid var(--border);
    border-radius: 10px; font-size: 15px;
    font-family: 'DM Sans', sans-serif;
    color: var(--text); background: #fafdfd;
    transition: border-color .2s, box-shadow .2s;
    outline: none;
  }
  input:focus {
    border-color: var(--teal);
    box-shadow: 0 0 0 3px rgba(13,115,119,.12);
    background: var(--white);
  }
  .btn {
    width: 100%; padding: 14px;
    background: linear-gradient(135deg, var(--teal), var(--teal-lt));
    color: var(--white); border: none; border-radius: 12px;
    font-family: 'Sora', sans-serif; font-size: 16px; font-weight: 700;
    cursor: pointer; margin-top: 6px;
    box-shadow: 0 6px 20px rgba(13,115,119,.35);
    transition: transform .15s, box-shadow .15s;
  }
  .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(13,115,119,.45); }
  .btn:active { transform: translateY(0); }
  .footer-link {
    text-align: center; margin-top: 22px;
    font-size: 14px; color: var(--muted);
  }
  .footer-link a { color: var(--teal); font-weight: 600; text-decoration: none; }
  .footer-link a:hover { text-decoration: underline; }
  @media(max-width: 480px) {
    .card { padding: 36px 20px; }
    h1 { font-size: 22px; }
  }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <div class="logo-wrap">FB</div>
    <h1>Masuk Akun</h1>
    <p class="subtitle">SMK FBAK – Portal Siswa &amp; Admin</p>
  </div>

  <?php if ($error): ?>
    <div class="alert-err">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="field">
      <label>Email</label>
      <input type="email" name="email" placeholder="contoh@email.com"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
    </div>
    <div class="field">
      <label>Kata Sandi</label>
      <input type="password" name="kata_sandi" placeholder="Masukkan kata sandi" required>
    </div>
    <button type="submit" class="btn">Masuk</button>
  </form>

  <p class="footer-link">Belum punya akun? <a href="registrasi.php">Daftar di sini</a></p>
</div>
</body>
</html>
