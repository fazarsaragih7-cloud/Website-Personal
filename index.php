<?php
// index.php — Dashboard Siswa
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['akun_id']) || $_SESSION['role'] !== 'siswa') {
    header('Location: login.php');
    exit;
}

$akun_id = $_SESSION['akun_id'];

// Ambil data akun
$stmt = $pdo->prepare("SELECT * FROM akun WHERE id = ? LIMIT 1");
$stmt->execute([$akun_id]);
$akun = $stmt->fetch();
if (!$akun) { session_destroy(); header('Location: login.php'); exit; }

// Ambil data pendaftaran jika ada
$qp = $pdo->prepare("SELECT * FROM pendaftaran WHERE akun_id = ? LIMIT 1");
$qp->execute([$akun_id]);
$pendaftaran = $qp->fetch();

// Handle ubah data akun
$editErrors  = [];
$editSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'ubah') {
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $hp   = trim($_POST['nomor_hp'] ?? '');
    if (empty($nama)) $editErrors[] = 'Nama lengkap wajib diisi.';
    if (empty($hp))   $editErrors[] = 'Nomor HP wajib diisi.';
    if (empty($editErrors)) {
        $up = $pdo->prepare("UPDATE akun SET nama_lengkap=?, nomor_hp=? WHERE id=?");
        $up->execute([$nama, $hp, $akun_id]);
        $_SESSION['akun_nama'] = $nama;
        $editSuccess = 'Data akun berhasil diperbarui.';
        $akun['nama_lengkap'] = $nama;
        $akun['nomor_hp'] = $hp;
    }
}

// Handle logout
if (isset($_GET['logout'])) { session_destroy(); header('Location: login.php'); exit; }

// Deteksi section dari URL
$activeSec = $_GET['sec'] ?? 'profil';
if (!in_array($activeSec, ['profil','formulir','status','cetak','ubah'])) $activeSec = 'profil';

$statusLabel = ['menunggu'=>'Menunggu Verifikasi','diterima'=>'Diterima ✓','ditolak'=>'Ditolak ✗'];
$statusColor = ['menunggu'=>'#e8a020','diterima'=>'#2e7d32','ditolak'=>'#e53935'];
$statusBg    = ['menunggu'=>'#fff8e1','diterima'=>'#e8f5e9','ditolak'=>'#fde8e8'];

$berkasLabel = [
    'berkas_ijazah'     => ['📄','Ijazah/SKL'],
    'berkas_rapor'      => ['📚','Rapor Sem. 1–5'],
    'berkas_kk'         => ['🏠','Kartu Keluarga'],
    'berkas_akte'       => ['📋','Akte Kelahiran'],
    'berkas_foto'       => ['📷','Pas Foto'],
    'berkas_sertifikat' => ['🏆','Sertifikat'],
    'berkas_bukti_bayar'=> ['💳','Bukti Bayar'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard – SMK FBAK</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
<style>
  :root{
    --teal:#0d7377;--teal-dk:#0a5c5f;--teal-lt:#14a2a8;
    --gold:#e8a020;--gold-lt:#f5c35a;
    --navy:#0d1f2d;--navy2:#122535;
    --white:#ffffff;--bg:#f0f6f7;
    --text:#1a2e3b;--muted:#5a7080;--border:#cde4e5;
    --err:#e53935;--ok:#2e7d32;
    --sidebar-w:270px;
  }
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}

  /* NAVBAR */
  .navbar{
    background:linear-gradient(90deg,var(--navy) 0%,var(--navy2) 50%,#0d3d42 100%);
    height:64px;display:flex;align-items:center;padding:0 28px;gap:16px;
    box-shadow:0 2px 16px rgba(0,0,0,.3);position:sticky;top:0;z-index:100;
  }
  .nav-logo{
    width:42px;height:42px;border-radius:50%;
    background:linear-gradient(135deg,var(--teal),var(--teal-lt));
    display:flex;align-items:center;justify-content:center;
    font-family:'Sora',sans-serif;font-weight:800;font-size:14px;color:var(--white);
    box-shadow:0 4px 12px rgba(13,115,119,.5);flex-shrink:0;letter-spacing:-1px;
  }
  .nav-title{font-family:'Sora',sans-serif;font-weight:800;font-size:18px;color:var(--white);}
  .nav-sub{font-size:11px;color:var(--gold-lt);}
  .nav-spacer{flex:1;}
  .nav-user{display:flex;align-items:center;gap:10px;color:rgba(255,255,255,.85);font-size:14px;}
  .nav-avatar{
    width:36px;height:36px;border-radius:50%;
    background:linear-gradient(135deg,var(--gold),var(--gold-lt));
    display:flex;align-items:center;justify-content:center;
    font-weight:700;font-size:14px;color:var(--navy);flex-shrink:0;
  }
  .nav-logout{
    padding:7px 14px;border-radius:8px;background:rgba(255,255,255,.1);
    color:rgba(255,255,255,.85);text-decoration:none;font-size:13px;font-weight:600;
    transition:background .2s;
  }
  .nav-logout:hover{background:rgba(255,255,255,.18);}

  /* LAYOUT */
  .layout{display:flex;min-height:calc(100vh - 64px);}

  /* SIDEBAR */
  .sidebar{
    width:var(--sidebar-w);flex-shrink:0;
    background:var(--white);border-right:1px solid var(--border);
    padding:24px 16px;display:flex;flex-direction:column;gap:4px;
  }
  .profile-card{
    background:linear-gradient(135deg,var(--teal),var(--teal-lt));
    border-radius:14px;padding:20px 16px;text-align:center;margin-bottom:18px;
    position:relative;overflow:hidden;
  }
  .profile-card::before{
    content:'';position:absolute;top:-28px;right:-28px;
    width:90px;height:90px;border-radius:50%;background:rgba(255,255,255,.08);
  }
  .avatar-big{
    width:60px;height:60px;border-radius:50%;
    background:rgba(255,255,255,.2);border:3px solid rgba(255,255,255,.4);
    display:flex;align-items:center;justify-content:center;
    margin:0 auto 10px;
    font-family:'Sora',sans-serif;font-weight:800;font-size:22px;color:var(--white);
  }
  .profile-name{font-family:'Sora',sans-serif;font-weight:700;font-size:14px;color:var(--white);margin-bottom:3px;}
  .profile-email{font-size:11px;color:rgba(255,255,255,.7);}

  .nav-item{
    display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;
    font-size:14px;font-weight:500;color:var(--muted);cursor:pointer;
    transition:all .2s;border:none;background:none;width:100%;text-align:left;text-decoration:none;
  }
  .nav-item:hover{background:rgba(13,115,119,.08);color:var(--teal);}
  .nav-item.active{background:rgba(13,115,119,.13);color:var(--teal);font-weight:600;}
  .nav-item .icon{font-size:16px;width:22px;text-align:center;flex-shrink:0;}
  .nav-item .badge{
    margin-left:auto;padding:2px 7px;border-radius:10px;font-size:11px;
    font-weight:700;background:var(--err);color:var(--white);
  }
  .nav-divider{height:1px;background:var(--border);margin:8px 0;}
  .nav-section-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);padding:4px 12px;margin-top:6px;}

  /* MAIN */
  .main{flex:1;padding:32px 36px;overflow-y:auto;}
  .page-title{font-family:'Sora',sans-serif;font-weight:800;font-size:22px;color:var(--navy);margin-bottom:4px;}
  .page-sub{color:var(--muted);font-size:14px;margin-bottom:26px;}

  /* SECTION */
  .section{display:none;}
  .section.active{display:block;}

  /* CARDS */
  .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;}
  .info-card{
    background:var(--bg);border-radius:12px;padding:16px 18px;
    border:1px solid var(--border);
  }
  .info-card.full{grid-column:1/-1;}
  .info-card .label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:5px;}
  .info-card .value{font-size:15px;font-weight:600;color:var(--text);}

  /* FORMULIR CTA */
  .cta-card{
    background:linear-gradient(135deg,var(--teal) 0%,var(--teal-lt) 100%);
    border-radius:16px;padding:28px 32px;color:var(--white);
    display:flex;align-items:center;gap:24px;margin-bottom:24px;
    box-shadow:0 8px 28px rgba(13,115,119,.3);
  }
  .cta-icon{font-size:44px;flex-shrink:0;}
  .cta-title{font-family:'Sora',sans-serif;font-size:18px;font-weight:800;margin-bottom:6px;}
  .cta-desc{font-size:14px;opacity:.88;margin-bottom:16px;line-height:1.5;}
  .btn-cta{
    display:inline-block;padding:11px 22px;
    background:var(--white);color:var(--teal);
    border-radius:10px;font-family:'Sora',sans-serif;font-size:14px;font-weight:700;
    text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,.15);
    transition:transform .15s;
  }
  .btn-cta:hover{transform:translateY(-2px);}

  /* CHECKLIST BERKAS */
  .berkas-checklist{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-top:16px;}
  .berkas-item{
    background:var(--bg);border:1px solid var(--border);border-radius:10px;
    padding:10px 12px;display:flex;align-items:center;gap:8px;font-size:12px;
  }
  .berkas-item .b-icon{font-size:18px;}
  .berkas-item .b-name{font-weight:600;color:var(--text);}
  .berkas-item .b-ok{color:var(--ok);font-size:11px;}
  .berkas-item .b-no{color:var(--err);font-size:11px;}

  /* STATUS */
  .status-card{
    background:var(--white);border-radius:16px;border:1px solid var(--border);
    box-shadow:0 2px 12px rgba(0,0,0,.04);overflow:hidden;margin-bottom:20px;
  }
  .status-header{
    padding:18px 24px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;
    border-bottom:1px solid var(--border);
  }
  .status-badge{
    display:inline-flex;align-items:center;gap:6px;
    padding:7px 16px;border-radius:20px;font-weight:700;font-size:13px;
  }
  .status-body{padding:20px 24px;}

  /* FORM UBAH */
  .form-card{background:var(--white);border-radius:14px;padding:26px;border:1px solid var(--border);box-shadow:0 2px 10px rgba(0,0,0,.04);}
  .field{margin-bottom:16px;}
  label.flabel{display:block;font-weight:600;font-size:12px;color:var(--text);margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px;}
  input[type=text],input[type=tel]{
    width:100%;padding:11px 14px;border:1.5px solid var(--border);
    border-radius:10px;font-size:14px;font-family:'DM Sans',sans-serif;
    color:var(--text);background:#fafdfd;
    transition:border-color .2s,box-shadow .2s;outline:none;
  }
  input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(13,115,119,.12);background:var(--white);}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
  .btn-save{
    padding:11px 26px;
    background:linear-gradient(135deg,var(--teal),var(--teal-lt));
    color:var(--white);border:none;border-radius:10px;
    font-family:'Sora',sans-serif;font-size:14px;font-weight:700;
    cursor:pointer;box-shadow:0 4px 12px rgba(13,115,119,.3);
    transition:transform .15s;
  }
  .btn-save:hover{transform:translateY(-2px);}
  .alert{border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:14px;}
  .alert-err{background:#fde8e8;border-left:4px solid var(--err);color:var(--err);}
  .alert-err ul{margin:6px 0 0 18px;}
  .alert-ok{background:#e8f5e9;border-left:4px solid var(--ok);color:var(--ok);}

  /* CETAK */
  .btn-print{
    padding:11px 26px;
    background:linear-gradient(135deg,var(--gold),var(--gold-lt));
    color:var(--navy);border:none;border-radius:10px;
    font-family:'Sora',sans-serif;font-size:14px;font-weight:700;
    cursor:pointer;box-shadow:0 4px 12px rgba(232,160,32,.35);
    transition:transform .15s;margin-bottom:20px;
  }
  .btn-print:hover{transform:translateY(-2px);}
  #print-area{background:var(--white);border-radius:14px;padding:32px;border:1px solid var(--border);}
  .print-header{text-align:center;margin-bottom:24px;border-bottom:2px solid var(--teal);padding-bottom:16px;}
  .print-header h2{font-family:'Sora',sans-serif;font-size:18px;color:var(--navy);}
  .print-header p{color:var(--muted);font-size:12px;margin-top:4px;}
  table.print-table{width:100%;border-collapse:collapse;font-size:13px;}
  table.print-table tr{border-bottom:1px solid #e8f0f1;}
  table.print-table td{padding:9px 8px;vertical-align:top;}
  table.print-table td:first-child{color:var(--muted);font-weight:600;width:40%;}
  .print-footer{margin-top:32px;text-align:right;font-size:12px;color:var(--muted);}

  @media print{
    body *{visibility:hidden;}
    #print-area,#print-area *{visibility:visible;}
    #print-area{position:fixed;top:0;left:0;width:100%;padding:40px;}
    .no-print{display:none!important;}
  }

  @media(max-width:860px){
    .sidebar{display:none;}
    .main{padding:20px 16px;}
    .info-grid,.row2{grid-template-columns:1fr;}
    .cta-card{flex-direction:column;text-align:center;}
  }
</style>
</head>
<body>

<nav class="navbar">
  <div class="nav-logo">FB</div>
  <div>
    <div class="nav-title">SMK FBAK</div>
    <div class="nav-sub">Portal Siswa</div>
  </div>
  <div class="nav-spacer"></div>
  <div class="nav-user">
    <div class="nav-avatar"><?= strtoupper(substr($akun['nama_lengkap'], 0, 1)) ?></div>
    <span><?= htmlspecialchars(explode(' ', $akun['nama_lengkap'])[0]) ?></span>
  </div>
  <a href="?logout=1" class="nav-logout no-print">Keluar</a>
</nav>

<div class="layout">
  <aside class="sidebar no-print">
    <div class="profile-card">
      <div class="avatar-big"><?= strtoupper(substr($akun['nama_lengkap'], 0, 1)) ?></div>
      <div class="profile-name"><?= htmlspecialchars($akun['nama_lengkap']) ?></div>
      <div class="profile-email"><?= htmlspecialchars($akun['email']) ?></div>
    </div>

    <div class="nav-section-label">Menu Utama</div>

    <button class="nav-item <?= $activeSec==='profil'?'active':'' ?>" id="btn-profil" onclick="showSection('profil',this)">
      <span class="icon">👤</span> Profil Akun
    </button>

    <button class="nav-item <?= $activeSec==='formulir'?'active':'' ?>" id="btn-formulir" onclick="showSection('formulir',this)">
      <span class="icon">📝</span> Formulir Pendaftaran
      <?php if (!$pendaftaran): ?><span class="badge">!</span><?php endif; ?>
    </button>

    <?php if ($pendaftaran): ?>
    <button class="nav-item <?= $activeSec==='status'?'active':'' ?>" id="btn-status" onclick="showSection('status',this)">
      <span class="icon">📊</span> Status Pendaftaran
    </button>
    <button class="nav-item <?= $activeSec==='cetak'?'active':'' ?>" id="btn-cetak" onclick="showSection('cetak',this)">
      <span class="icon">🖨️</span> Cetak Formulir
    </button>
    <?php endif; ?>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Pengaturan</div>
    <button class="nav-item <?= $activeSec==='ubah'?'active':'' ?>" id="btn-ubah" onclick="showSection('ubah',this)">
      <span class="icon">⚙️</span> Ubah Data Akun
    </button>
  </aside>

  <main class="main">

    <!-- ═══ PROFIL ═══ -->
    <div class="section <?= $activeSec==='profil'?'active':'' ?>" id="sec-profil">
      <div class="page-title">Profil Akun</div>
      <p class="page-sub">Informasi akun dan ringkasan pendaftaran Anda.</p>

      <div class="info-grid">
        <div class="info-card full">
          <div class="label">Nama Lengkap</div>
          <div class="value"><?= htmlspecialchars($akun['nama_lengkap']) ?></div>
        </div>
        <div class="info-card">
          <div class="label">Email</div>
          <div class="value"><?= htmlspecialchars($akun['email']) ?></div>
        </div>
        <div class="info-card">
          <div class="label">Nomor HP</div>
          <div class="value"><?= htmlspecialchars($akun['nomor_hp']) ?></div>
        </div>
        <div class="info-card">
          <div class="label">Bergabung Sejak</div>
          <div class="value"><?= date('d M Y', strtotime($akun['created_at'])) ?></div>
        </div>
        <div class="info-card">
          <div class="label">Status Pendaftaran</div>
          <div class="value">
            <?php if ($pendaftaran): ?>
              <span style="color:<?= $statusColor[$pendaftaran['status']] ?>;font-weight:700;">
                <?= $statusLabel[$pendaftaran['status']] ?>
              </span>
            <?php else: ?>
              <span style="color:var(--muted);">Belum mengisi formulir</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if (!$pendaftaran): ?>
      <!-- CTA formulir di profil -->
      <div class="cta-card">
        <div class="cta-icon">📋</div>
        <div>
          <div class="cta-title">Lengkapi Formulir Pendaftaran!</div>
          <div class="cta-desc">Anda belum mengisi formulir pendaftaran siswa baru. Siapkan berkas persyaratan dan isi sekarang untuk melanjutkan proses pendaftaran ke SMK FBAK.</div>
          <button class="btn-cta" onclick="showSection('formulir', document.getElementById('btn-formulir'))">Isi Formulir Sekarang →</button>
        </div>
      </div>
      <?php else: ?>
      <!-- Ringkasan pendaftaran di profil -->
      <div class="status-card">
        <div class="status-header" style="background:<?= $statusBg[$pendaftaran['status']] ?>">
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Status Pendaftaran</div>
            <span class="status-badge" style="background:<?= $statusColor[$pendaftaran['status']] ?>;color:#fff;">
              <?= $statusLabel[$pendaftaran['status']] ?>
            </span>
          </div>
          <?php if ($pendaftaran['catatan_admin']): ?>
          <div style="flex:1;">
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:4px;">Catatan Admin</div>
            <div style="font-size:14px;"><?= htmlspecialchars($pendaftaran['catatan_admin']) ?></div>
          </div>
          <?php endif; ?>
        </div>
        <div class="status-body">
          <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <div style="font-size:13px;color:var(--muted);">NISN: <strong style="color:var(--text);"><?= htmlspecialchars($pendaftaran['nisn']) ?></strong></div>
            <div style="font-size:13px;color:var(--muted);">Jurusan: <strong style="color:var(--text);"><?= htmlspecialchars($pendaftaran['jurusan']) ?></strong></div>
            <div style="font-size:13px;color:var(--muted);">Terdaftar: <strong style="color:var(--text);"><?= date('d M Y', strtotime($pendaftaran['created_at'])) ?></strong></div>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ═══ FORMULIR PENDAFTARAN ═══ -->
    <div class="section <?= $activeSec==='formulir'?'active':'' ?>" id="sec-formulir">
      <div class="page-title">Formulir Pendaftaran Siswa Baru</div>
      <p class="page-sub">Isi data dan upload berkas persyaratan untuk mendaftar ke SMK FBAK.</p>

      <?php if ($pendaftaran): ?>
        <div class="cta-card" style="background:linear-gradient(135deg,#2e7d32,#388e3c);">
          <div class="cta-icon">✅</div>
          <div>
            <div class="cta-title">Formulir Sudah Terkirim</div>
            <div class="cta-desc">Anda telah mengisi dan mengirimkan formulir pendaftaran. Pantau hasilnya di menu "Status Pendaftaran". Hubungi sekolah jika perlu perubahan data.</div>
            <button class="btn-cta" onclick="showSection('status', document.getElementById('btn-status'))">Lihat Status →</button>
          </div>
        </div>
      <?php else: ?>
        <!-- Daftar berkas yang perlu disiapkan -->
        <div style="background:var(--white);border-radius:14px;padding:24px 26px;border:1px solid var(--border);margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,.04);">
          <div style="font-family:'Sora',sans-serif;font-weight:700;font-size:15px;color:var(--navy);margin-bottom:6px;">📎 Siapkan Berkas Berikut Sebelum Mengisi Formulir</div>
          <p style="font-size:13px;color:var(--muted);margin-bottom:16px;">Format berkas yang diterima: JPG, PNG, atau PDF.</p>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;">
            <?php
            $berkasInfo = [
              ['📄','Ijazah / SKL','Wajib','badge-req'],
              ['📊','Rapor Semester 1–5','Wajib','badge-req'],
              ['🏠','Kartu Keluarga','Wajib','badge-req'],
              ['📋','Akte Kelahiran','Wajib','badge-req'],
              ['📷','Pas Foto','Wajib','badge-req'],
              ['🏆','Sertifikat Pendukung','Opsional','badge-opt'],
              ['💳','Bukti Pembayaran','Wajib','badge-req'],
            ];
            foreach ($berkasInfo as [$ic,$nm,$st,$cls]): ?>
            <div style="display:flex;align-items:center;gap:8px;padding:10px 12px;background:var(--bg);border-radius:10px;border:1px solid var(--border);">
              <span style="font-size:20px;"><?= $ic ?></span>
              <div>
                <div style="font-weight:600;font-size:13px;color:var(--text);"><?= $nm ?></div>
                <div style="font-size:11px;color:<?= $cls==='badge-req'?'var(--err)':'var(--teal)' ?>;font-weight:600;"><?= $st ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="cta-card">
          <div class="cta-icon">📝</div>
          <div>
            <div class="cta-title">Buka Formulir Pendaftaran Lengkap</div>
            <div class="cta-desc">Isi data pribadi, akademik, orang tua, dan upload semua berkas persyaratan. Pastikan semua data sudah benar sebelum mengirimkan.</div>
            <a href="pendaftaran.php" class="btn-cta">Buka Formulir →</a>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- ═══ STATUS PENDAFTARAN ═══ -->
    <?php if ($pendaftaran): ?>
    <div class="section <?= $activeSec==='status'?'active':'' ?>" id="sec-status">
      <div class="page-title">Status Pendaftaran</div>
      <p class="page-sub">Detail dan status verifikasi formulir pendaftaran Anda.</p>

      <div class="status-card">
        <div class="status-header" style="background:<?= $statusBg[$pendaftaran['status']] ?>">
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Status Verifikasi</div>
            <span class="status-badge" style="background:<?= $statusColor[$pendaftaran['status']] ?>;color:#fff;font-size:14px;">
              <?= $statusLabel[$pendaftaran['status']] ?>
            </span>
          </div>
          <?php if ($pendaftaran['catatan_admin']): ?>
          <div style="flex:1;">
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:4px;">Catatan dari Admin</div>
            <div style="font-size:14px;"><?= htmlspecialchars($pendaftaran['catatan_admin']) ?></div>
          </div>
          <?php endif; ?>
        </div>
        <div class="status-body">
          <div style="font-weight:700;font-size:12px;color:var(--navy);margin-bottom:12px;text-transform:uppercase;letter-spacing:.5px;">Data Pendaftaran</div>
          <div class="info-grid">
            <div class="info-card full"><div class="label">Nama Lengkap</div><div class="value"><?= htmlspecialchars($pendaftaran['nama_lengkap']) ?></div></div>
            <div class="info-card"><div class="label">NISN</div><div class="value"><?= htmlspecialchars($pendaftaran['nisn']) ?></div></div>
            <div class="info-card"><div class="label">Pilihan Jurusan</div><div class="value"><?= htmlspecialchars($pendaftaran['jurusan']) ?></div></div>
            <div class="info-card"><div class="label">Asal Sekolah</div><div class="value"><?= htmlspecialchars($pendaftaran['asal_sekolah']) ?></div></div>
            <div class="info-card"><div class="label">Tahun Lulus</div><div class="value"><?= htmlspecialchars($pendaftaran['tahun_lulus']) ?></div></div>
            <div class="info-card"><div class="label">Tanggal Daftar</div><div class="value"><?= date('d M Y', strtotime($pendaftaran['created_at'])) ?></div></div>
            <div class="info-card"><div class="label">Tempat / Tgl Lahir</div><div class="value"><?= htmlspecialchars($pendaftaran['tempat_lahir']) ?>, <?= date('d M Y', strtotime($pendaftaran['tanggal_lahir'])) ?></div></div>
            <div class="info-card"><div class="label">Jenis Kelamin</div><div class="value"><?= htmlspecialchars($pendaftaran['jenis_kelamin']) ?></div></div>
            <div class="info-card"><div class="label">Nama Ayah</div><div class="value"><?= htmlspecialchars($pendaftaran['nama_ayah']) ?></div></div>
            <div class="info-card"><div class="label">Nama Ibu</div><div class="value"><?= htmlspecialchars($pendaftaran['nama_ibu']) ?></div></div>
          </div>

          <div style="font-weight:700;font-size:12px;color:var(--navy);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;">Berkas yang Diupload</div>
          <div class="berkas-checklist">
            <?php foreach ($berkasLabel as $k => [$ic, $lb]): ?>
            <div class="berkas-item">
              <span class="b-icon"><?= $ic ?></span>
              <div>
                <div class="b-name"><?= $lb ?></div>
                <?php if ($pendaftaran[$k]): ?>
                  <div class="b-ok">✓ Terupload</div>
                <?php else: ?>
                  <div class="b-no">✗ Tidak ada</div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ CETAK ═══ -->
    <div class="section <?= $activeSec==='cetak'?'active':'' ?>" id="sec-cetak">
      <div class="page-title no-print">Cetak Formulir</div>
      <p class="page-sub no-print">Pratinjau dan cetak bukti formulir pendaftaran Anda.</p>
      <button class="btn-print no-print" onclick="window.print()">🖨️ &nbsp;Cetak Sekarang</button>
      <div id="print-area">
        <div class="print-header">
          <h2>FORMULIR PENDAFTARAN SISWA BARU</h2>
          <p>SMK FBAK – Tahun Pelajaran <?= date('Y') ?>/<?= date('Y')+1 ?></p>
        </div>
        <table class="print-table">
          <tr><td>Nama Lengkap</td><td><?= htmlspecialchars($pendaftaran['nama_lengkap']) ?></td></tr>
          <tr><td>Tempat / Tgl Lahir</td><td><?= htmlspecialchars($pendaftaran['tempat_lahir']) ?>, <?= date('d M Y',strtotime($pendaftaran['tanggal_lahir'])) ?></td></tr>
          <tr><td>Jenis Kelamin</td><td><?= htmlspecialchars($pendaftaran['jenis_kelamin']) ?></td></tr>
          <tr><td>Agama</td><td><?= htmlspecialchars($pendaftaran['agama']) ?></td></tr>
          <tr><td>Nomor HP Siswa</td><td><?= htmlspecialchars($pendaftaran['nomor_hp']) ?></td></tr>
          <tr><td>Alamat</td><td><?= htmlspecialchars($pendaftaran['alamat']) ?></td></tr>
          <tr><td>NISN</td><td><?= htmlspecialchars($pendaftaran['nisn']) ?></td></tr>
          <tr><td>Asal Sekolah</td><td><?= htmlspecialchars($pendaftaran['asal_sekolah']) ?></td></tr>
          <tr><td>Tahun Lulus</td><td><?= htmlspecialchars($pendaftaran['tahun_lulus']) ?></td></tr>
          <tr><td>Pilihan Jurusan</td><td><?= htmlspecialchars($pendaftaran['jurusan']) ?></td></tr>
          <tr><td>Nama Ayah</td><td><?= htmlspecialchars($pendaftaran['nama_ayah']) ?></td></tr>
          <tr><td>Pekerjaan Ayah</td><td><?= htmlspecialchars($pendaftaran['pekerjaan_ayah'] ?: '-') ?></td></tr>
          <tr><td>Nama Ibu</td><td><?= htmlspecialchars($pendaftaran['nama_ibu']) ?></td></tr>
          <tr><td>Pekerjaan Ibu</td><td><?= htmlspecialchars($pendaftaran['pekerjaan_ibu'] ?: '-') ?></td></tr>
          <tr><td>Nomor HP Orang Tua</td><td><?= htmlspecialchars($pendaftaran['nomor_hp_ortu']) ?></td></tr>
          <tr><td>Tanggal Pendaftaran</td><td><?= date('d F Y', strtotime($pendaftaran['created_at'])) ?></td></tr>
          <tr><td>Status</td><td><?= $statusLabel[$pendaftaran['status']] ?></td></tr>
        </table>
        <div class="print-footer">
          <p>Dicetak pada: <?= date('d F Y, H:i') ?> WIB</p>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:40px;text-align:center;">
            <div>
              <p>Mengetahui,<br>Kepala SMK FBAK</p><br><br><br>
              <p>(________________________)</p>
            </div>
            <div>
              <p>Calon Siswa,</p><br><br><br>
              <p>(________________________)</p>
              <p><?= htmlspecialchars($pendaftaran['nama_lengkap']) ?></p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ═══ UBAH DATA AKUN ═══ -->
    <div class="section <?= $activeSec==='ubah'?'active':'' ?>" id="sec-ubah">
      <div class="page-title">Ubah Data Akun</div>
      <p class="page-sub">Perbarui nama dan nomor HP akun Anda.</p>

      <?php if (!empty($editErrors)): ?>
        <div class="alert alert-err"><ul><?php foreach ($editErrors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
      <?php elseif ($editSuccess): ?>
        <div class="alert alert-ok">✓ <?= htmlspecialchars($editSuccess) ?></div>
      <?php endif; ?>

      <div class="form-card">
        <form method="POST">
          <input type="hidden" name="aksi" value="ubah">
          <div class="field">
            <label class="flabel">Nama Lengkap</label>
            <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($akun['nama_lengkap']) ?>" required>
          </div>
          <div class="row2">
            <div class="field">
              <label class="flabel">Nomor HP</label>
              <input type="tel" name="nomor_hp" value="<?= htmlspecialchars($akun['nomor_hp']) ?>" required>
            </div>
            <div class="field">
              <label class="flabel">Email (tidak dapat diubah)</label>
              <input type="text" value="<?= htmlspecialchars($akun['email']) ?>" disabled style="opacity:.6;cursor:not-allowed;">
            </div>
          </div>
          <button type="submit" class="btn-save">Simpan Perubahan</button>
        </form>
      </div>
    </div>

  </main>
</div>

<script>
function showSection(name, btn) {
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(b => b.classList.remove('active'));
  document.getElementById('sec-' + name).classList.add('active');
  if (btn) btn.classList.add('active');
}
<?php if (!empty($editErrors) || $editSuccess): ?>
showSection('ubah', document.getElementById('btn-ubah'));
<?php endif; ?>
</script>
</body>
</html>
