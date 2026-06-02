<?php
// registrasi.php — Buat akun saja (email + password), terpisah dari formulir pendaftaran siswa
session_start();
require_once 'koneksi.php';

if (isset($_SESSION['akun_id'])) {
    header('Location: index.php');
    exit;
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama    = trim($_POST['nama_lengkap'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $hp      = trim($_POST['nomor_hp'] ?? '');
    $sandi   = $_POST['kata_sandi'] ?? '';
    $konfirm = $_POST['konfirmasi'] ?? '';

    if (empty($nama))                                            $errors[] = 'Nama lengkap wajib diisi.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
                                                                 $errors[] = 'Email tidak valid.';
    if (empty($hp))                                              $errors[] = 'Nomor HP wajib diisi.';
    if (strlen($sandi) < 6)                                      $errors[] = 'Kata sandi minimal 6 karakter.';
    if ($sandi !== $konfirm)                                     $errors[] = 'Konfirmasi kata sandi tidak cocok.';

    if (empty($errors)) {
        $cek = $pdo->prepare("SELECT id FROM akun WHERE email = ?");
        $cek->execute([$email]);
        if ($cek->fetch()) {
            $errors[] = 'Email sudah terdaftar. Gunakan email lain atau login.';
        } else {
            $hash = password_hash($sandi, PASSWORD_DEFAULT);
            $ins  = $pdo->prepare("INSERT INTO akun (nama_lengkap, email, nomor_hp, kata_sandi, role) VALUES (?,?,?,?,'siswa')");
            $ins->execute([$nama, $email, $hp, $hash]);
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registrasi Akun – SMK FBAK</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --teal:#0d7377;--teal-lt:#14a2a8;--gold:#e8a020;--gold-lt:#f5c35a;
    --navy:#0d1f2d;--navy2:#122535;--white:#ffffff;
    --err:#e53935;--ok:#2e7d32;--text:#1a2e3b;--muted:#5a7080;--border:#cde4e5;
  }
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{
    font-family:'DM Sans',sans-serif;
    background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 40%,#0d3d42 100%);
    min-height:100vh;display:flex;align-items:center;justify-content:center;
    padding:40px 16px;position:relative;
  }
  body::before{
    content:'';position:fixed;inset:0;
    background:radial-gradient(ellipse 80% 60% at 10% 20%,rgba(13,115,119,.25) 0%,transparent 60%),
               radial-gradient(ellipse 60% 60% at 90% 80%,rgba(232,160,32,.1) 0%,transparent 60%);
    pointer-events:none;
  }
  .card{
    background:var(--white);border-radius:20px;padding:44px 44px;
    max-width:500px;width:100%;
    box-shadow:0 32px 80px rgba(0,0,0,.35),0 0 0 1px rgba(13,115,119,.15);
    position:relative;overflow:hidden;
  }
  .card::before{
    content:'';position:absolute;top:0;left:0;right:0;height:5px;
    background:linear-gradient(90deg,var(--teal),var(--gold),var(--teal-lt));
  }
  .header{text-align:center;margin-bottom:28px;}
  .logo-wrap{
    width:72px;height:72px;border-radius:50%;
    background:linear-gradient(135deg,var(--teal),var(--teal-lt));
    display:flex;align-items:center;justify-content:center;
    margin:0 auto 14px;
    font-family:'Sora',sans-serif;font-weight:800;font-size:22px;color:var(--white);
    box-shadow:0 8px 28px rgba(13,115,119,.45);letter-spacing:-1px;
  }
  h1{font-family:'Sora',sans-serif;font-size:23px;font-weight:800;color:var(--navy);margin-bottom:4px;}
  .subtitle{color:var(--muted);font-size:13px;}

  /* Steps indicator */
  .steps{display:flex;align-items:center;gap:0;margin-bottom:26px;}
  .step{flex:1;text-align:center;position:relative;}
  .step:not(:last-child)::after{
    content:'';position:absolute;top:16px;left:50%;width:100%;height:2px;
    background:var(--border);z-index:0;
  }
  .step-circle{
    width:32px;height:32px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-family:'Sora',sans-serif;font-weight:700;font-size:13px;
    margin:0 auto 6px;position:relative;z-index:1;
  }
  .step.active .step-circle{background:var(--teal);color:var(--white);box-shadow:0 4px 12px rgba(13,115,119,.35);}
  .step.done .step-circle{background:var(--ok);color:var(--white);}
  .step.inactive .step-circle{background:var(--border);color:var(--muted);}
  .step-label{font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.3px;}
  .step.active .step-label{color:var(--teal);}

  .info-box{
    background:#e8f7f8;border:1px solid #b3dfe1;border-radius:10px;
    padding:12px 14px;margin-bottom:22px;font-size:13px;color:#0a5c5f;line-height:1.5;
  }
  .info-box strong{display:block;margin-bottom:3px;}
  .alert-err{background:#fde8e8;border-left:4px solid var(--err);color:var(--err);border-radius:10px;padding:12px 14px;margin-bottom:18px;font-size:13px;}
  .alert-err ul{margin:6px 0 0 18px;}
  .alert-ok{background:#e8f5e9;border-left:4px solid var(--ok);color:var(--ok);border-radius:10px;padding:16px;margin-bottom:18px;font-size:14px;line-height:1.6;}
  .alert-ok strong{display:block;margin-bottom:6px;font-size:15px;}
  .field{margin-bottom:16px;}
  label{display:block;font-weight:600;font-size:12px;color:var(--text);margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px;}
  input{
    width:100%;padding:11px 14px;border:1.5px solid var(--border);
    border-radius:10px;font-size:14px;font-family:'DM Sans',sans-serif;
    color:var(--text);background:#fafdfd;
    transition:border-color .2s,box-shadow .2s;outline:none;
  }
  input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(13,115,119,.12);background:var(--white);}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
  .btn{
    width:100%;padding:13px;
    background:linear-gradient(135deg,var(--teal),var(--teal-lt));
    color:var(--white);border:none;border-radius:12px;
    font-family:'Sora',sans-serif;font-size:15px;font-weight:700;
    cursor:pointer;margin-top:6px;
    box-shadow:0 6px 20px rgba(13,115,119,.35);
    transition:transform .15s,box-shadow .15s;
  }
  .btn:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(13,115,119,.45);}
  .btn-login{
    display:block;width:100%;padding:13px;text-align:center;
    background:linear-gradient(135deg,var(--teal),var(--teal-lt));
    color:var(--white);border:none;border-radius:12px;
    font-family:'Sora',sans-serif;font-size:15px;font-weight:700;
    cursor:pointer;margin-top:12px;text-decoration:none;
    box-shadow:0 6px 20px rgba(13,115,119,.35);
    transition:transform .15s;
  }
  .btn-login:hover{transform:translateY(-2px);}
  .footer-link{text-align:center;margin-top:18px;font-size:14px;color:var(--muted);}
  .footer-link a{color:var(--teal);font-weight:600;text-decoration:none;}
  .footer-link a:hover{text-decoration:underline;}
  @media(max-width:520px){.card{padding:32px 18px;}.row2{grid-template-columns:1fr;}}
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <div class="logo-wrap">FB</div>
    <h1>Registrasi Akun</h1>
    <p class="subtitle">SMK FBAK – Portal Siswa Baru</p>
  </div>

  <!-- Steps -->
  <div class="steps">
    <div class="step <?= $success ? 'done' : 'active' ?>">
      <div class="step-circle"><?= $success ? '✓' : '1' ?></div>
      <div class="step-label">Buat Akun</div>
    </div>
    <div class="step <?= $success ? 'active' : 'inactive' ?>">
      <div class="step-circle">2</div>
      <div class="step-label">Login</div>
    </div>
    <div class="step inactive">
      <div class="step-circle">3</div>
      <div class="step-label">Isi Formulir</div>
    </div>
  </div>

  <?php if (!$success): ?>
  <div class="info-box">
    <strong>📋 Cara Pendaftaran Siswa Baru</strong>
    Langkah 1: Buat akun di halaman ini. Langkah 2: Login dengan akun yang dibuat. Langkah 3: Di dashboard, klik menu <strong>Formulir Pendaftaran</strong> untuk mengisi data dan upload berkas.
  </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert-err">
      <strong>⚠ Periksa kembali:</strong>
      <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert-ok">
      <strong>✅ Akun berhasil dibuat!</strong>
      Selanjutnya, silakan login menggunakan email dan kata sandi yang sudah Anda buat. Setelah masuk, lengkapi <strong>Formulir Pendaftaran Siswa Baru</strong> melalui menu di dashboard.
    </div>
    <a href="login.php" class="btn-login">→ Masuk ke Dashboard</a>
  <?php else: ?>

  <form method="POST">
    <div class="field">
      <label>Nama Lengkap</label>
      <input type="text" name="nama_lengkap" placeholder="Masukkan nama lengkap"
             value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>" required autofocus>
    </div>
    <div class="row2">
      <div class="field">
        <label>Email</label>
        <input type="email" name="email" placeholder="contoh@email.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label>Nomor HP</label>
        <input type="tel" name="nomor_hp" placeholder="08xxxxxxxxxx"
               value="<?= htmlspecialchars($_POST['nomor_hp'] ?? '') ?>" required>
      </div>
    </div>
    <div class="row2">
      <div class="field">
        <label>Kata Sandi</label>
        <input type="password" name="kata_sandi" placeholder="Min. 6 karakter" required>
      </div>
      <div class="field">
        <label>Konfirmasi Sandi</label>
        <input type="password" name="konfirmasi" placeholder="Ulangi kata sandi" required>
      </div>
    </div>
    <button type="submit" class="btn">Buat Akun →</button>
  </form>

  <?php endif; ?>

  <p class="footer-link">Sudah punya akun? <a href="login.php">Login di sini</a></p>
</div>
</body>
</html>
