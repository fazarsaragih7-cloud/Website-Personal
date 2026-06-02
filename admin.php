<?php
// admin.php — Panel Admin SMK FBAK
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['akun_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Handle ubah status pendaftaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'ubah_status') {
    $pid    = (int)($_POST['pendaftaran_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $catatan = trim($_POST['catatan_admin'] ?? '');
    $allowed = ['menunggu','diterima','ditolak'];
    if ($pid > 0 && in_array($status, $allowed)) {
        $upd = $pdo->prepare("UPDATE pendaftaran SET status=?, catatan_admin=? WHERE id=?");
        $upd->execute([$status, $catatan ?: null, $pid]);
        header('Location: admin.php?updated=1&tab=' . ($_POST['tab'] ?? 'semua'));
        exit;
    }
}

// Filter & search
$tab    = $_GET['tab'] ?? 'semua';
$search = trim($_GET['q'] ?? '');
$where  = '';
$params = [];

if ($tab !== 'semua') {
    $where    = 'WHERE p.status = ?';
    $params[] = $tab;
}
if ($search) {
    $where  = $where ? $where . ' AND' : 'WHERE';
    $where .= ' (p.nama_lengkap LIKE ? OR p.nisn LIKE ? OR p.asal_sekolah LIKE ? OR a.email LIKE ?)';
    $like    = "%$search%";
    array_push($params, $like, $like, $like, $like);
}

$sql  = "SELECT p.*, a.email, a.nomor_hp AS hp_akun FROM pendaftaran p
         JOIN akun a ON a.id = p.akun_id
         $where
         ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Statistik
$stats = $pdo->query("SELECT
    COUNT(*) AS total,
    SUM(status='menunggu') AS menunggu,
    SUM(status='diterima') AS diterima,
    SUM(status='ditolak')  AS ditolak
    FROM pendaftaran")->fetch();

$statusLabel = ['menunggu'=>'Menunggu','diterima'=>'Diterima','ditolak'=>'Ditolak'];
$statusColor = ['menunggu'=>'#e8a020','diterima'=>'#2e7d32','ditolak'=>'#e53935'];
$statusBg    = ['menunggu'=>'#fff8e1','diterima'=>'#e8f5e9','ditolak'=>'#fde8e8'];

$berkasLabel = [
  'berkas_ijazah'=>'Ijazah','berkas_rapor'=>'Rapor',
  'berkas_kk'=>'KK','berkas_akte'=>'Akte',
  'berkas_foto'=>'Foto','berkas_sertifikat'=>'Sertifikat',
  'berkas_bukti_bayar'=>'Bukti Bayar',
];

// Data detail untuk modal
$detail = null;
if (isset($_GET['detail'])) {
    $ds = $pdo->prepare("SELECT p.*, a.email FROM pendaftaran p JOIN akun a ON a.id=p.akun_id WHERE p.id=? LIMIT 1");
    $ds->execute([(int)$_GET['detail']]);
    $detail = $ds->fetch();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel – SMK FBAK</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{
    --teal:#0d7377;--teal-lt:#14a2a8;--gold:#e8a020;--gold-lt:#f5c35a;
    --navy:#0d1f2d;--navy2:#122535;--white:#fff;--bg:#f0f6f7;
    --text:#1a2e3b;--muted:#5a7080;--border:#cde4e5;
    --err:#e53935;--ok:#2e7d32;--warn:#e8a020;
    --sidebar-w:260px;
  }
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}

  /* NAVBAR */
  .navbar{
    background:linear-gradient(90deg,#0a1929 0%,var(--navy) 50%,#0d3d42 100%);
    height:64px;display:flex;align-items:center;padding:0 28px;gap:16px;
    box-shadow:0 2px 16px rgba(0,0,0,.35);position:sticky;top:0;z-index:200;
  }
  .nav-logo{
    width:42px;height:42px;border-radius:10px;
    background:linear-gradient(135deg,var(--gold),var(--gold-lt));
    display:flex;align-items:center;justify-content:center;
    font-family:'Sora',sans-serif;font-weight:800;font-size:14px;color:var(--navy);
    flex-shrink:0;letter-spacing:-1px;
  }
  .nav-title{font-family:'Sora',sans-serif;font-weight:800;font-size:17px;color:var(--white);}
  .nav-sub{font-size:11px;color:var(--gold-lt);font-weight:500;}
  .nav-spacer{flex:1;}
  .nav-admin{
    background:rgba(232,160,32,.2);border:1px solid rgba(232,160,32,.4);
    padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;
    color:var(--gold-lt);letter-spacing:.5px;
  }
  .nav-logout{
    padding:7px 14px;border-radius:8px;background:rgba(255,255,255,.1);
    color:rgba(255,255,255,.8);text-decoration:none;font-size:13px;font-weight:600;
    transition:background .2s;
  }
  .nav-logout:hover{background:rgba(255,255,255,.18);}

  /* LAYOUT */
  .layout{display:flex;min-height:calc(100vh - 64px);}

  /* SIDEBAR */
  .sidebar{
    width:var(--sidebar-w);flex-shrink:0;
    background:var(--white);border-right:1px solid var(--border);
    padding:24px 16px;
  }
  .nav-item{
    display:flex;align-items:center;gap:10px;padding:10px 13px;border-radius:10px;
    font-size:14px;font-weight:500;color:var(--muted);cursor:pointer;
    transition:all .2s;border:none;background:none;width:100%;text-align:left;
    text-decoration:none;margin-bottom:3px;
  }
  .nav-item:hover{background:rgba(13,115,119,.08);color:var(--teal);}
  .nav-item.active{background:rgba(13,115,119,.12);color:var(--teal);font-weight:600;}
  .nav-item .icon{font-size:17px;width:22px;text-align:center;}
  .nav-count{
    margin-left:auto;min-width:22px;height:22px;border-radius:11px;
    background:var(--gold);color:var(--navy);font-size:11px;font-weight:800;
    display:flex;align-items:center;justify-content:center;padding:0 6px;
  }
  .sidebar-title{
    font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:1px;
    color:var(--muted);padding:8px 13px 6px;margin-top:8px;
  }

  /* MAIN */
  .main{flex:1;padding:28px 32px;overflow-y:auto;}

  /* STAT CARDS */
  .stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:28px;}
  .stat-card{
    background:var(--white);border-radius:14px;padding:20px 22px;
    border:1px solid var(--border);transition:box-shadow .2s;
    display:flex;flex-direction:column;gap:4px;
  }
  .stat-card:hover{box-shadow:0 4px 16px rgba(13,115,119,.1);}
  .stat-label{font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);}
  .stat-val{font-family:'Sora',sans-serif;font-size:32px;font-weight:800;color:var(--navy);}
  .stat-card.warn .stat-val{color:var(--warn);}
  .stat-card.ok .stat-val{color:var(--ok);}
  .stat-card.err .stat-val{color:var(--err);}

  /* TOOLBAR */
  .toolbar{display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;}
  .page-title{font-family:'Sora',sans-serif;font-weight:800;font-size:20px;color:var(--navy);}
  .search-wrap{position:relative;margin-left:auto;}
  .search-wrap input{
    padding:9px 14px 9px 36px;border:1.5px solid var(--border);
    border-radius:10px;font-size:14px;font-family:'DM Sans',sans-serif;
    color:var(--text);background:var(--white);outline:none;width:240px;
    transition:border-color .2s;
  }
  .search-wrap input:focus{border-color:var(--teal);}
  .search-icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:15px;color:var(--muted);}

  /* TABS */
  .tabs{display:flex;gap:4px;margin-bottom:18px;background:var(--white);border:1px solid var(--border);border-radius:12px;padding:4px;}
  .tab{
    padding:8px 16px;border-radius:9px;font-size:13px;font-weight:600;
    color:var(--muted);cursor:pointer;text-decoration:none;transition:all .2s;
    display:flex;align-items:center;gap:6px;
  }
  .tab:hover{background:rgba(13,115,119,.08);color:var(--teal);}
  .tab.active{background:var(--teal);color:var(--white);}
  .tab .tc{
    min-width:18px;height:18px;border-radius:9px;font-size:10px;font-weight:800;
    display:inline-flex;align-items:center;justify-content:center;padding:0 5px;
    background:rgba(255,255,255,.3);
  }
  .tab:not(.active) .tc{background:var(--border);color:var(--text);}

  /* TABLE */
  .table-wrap{background:var(--white);border-radius:14px;border:1px solid var(--border);overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.04);}
  table{width:100%;border-collapse:collapse;}
  thead{background:linear-gradient(90deg,var(--navy),var(--navy2));}
  thead th{padding:13px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:rgba(255,255,255,.7);}
  tbody tr{border-bottom:1px solid var(--border);transition:background .15s;}
  tbody tr:last-child{border-bottom:none;}
  tbody tr:hover{background:#f7fbfc;}
  td{padding:13px 16px;font-size:13px;vertical-align:middle;}
  .no-data{text-align:center;padding:40px;color:var(--muted);}

  /* STATUS PILL */
  .pill{
    display:inline-block;padding:4px 12px;border-radius:20px;
    font-size:11px;font-weight:700;white-space:nowrap;
  }

  /* ACTION BUTTONS */
  .btn-detail{
    padding:6px 12px;border-radius:8px;font-size:12px;font-weight:600;
    background:rgba(13,115,119,.1);color:var(--teal);border:none;cursor:pointer;
    text-decoration:none;transition:background .2s;
  }
  .btn-detail:hover{background:rgba(13,115,119,.2);}

  /* BERKAS CHECK */
  .berkas-wrap{display:flex;flex-wrap:wrap;gap:4px;}
  .b-chip{
    padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;
  }
  .b-chip.ok{background:#e8f5e9;color:var(--ok);}
  .b-chip.no{background:#fde8e8;color:var(--err);}

  /* ── MODAL ── */
  .modal-overlay{
    position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:500;
    display:flex;align-items:flex-start;justify-content:center;padding:20px;
    overflow-y:auto;
  }
  .modal{
    background:var(--white);border-radius:20px;width:100%;max-width:700px;
    margin:auto;overflow:hidden;
    box-shadow:0 32px 80px rgba(0,0,0,.3);
  }
  .modal-head{
    background:linear-gradient(90deg,var(--navy),#0d3d42);
    padding:20px 28px;display:flex;align-items:center;gap:12px;
  }
  .modal-head h2{font-family:'Sora',sans-serif;font-weight:800;font-size:18px;color:var(--white);flex:1;}
  .modal-close{
    background:rgba(255,255,255,.1);border:none;color:var(--white);
    width:32px;height:32px;border-radius:8px;cursor:pointer;font-size:18px;
    display:flex;align-items:center;justify-content:center;
    transition:background .2s;
  }
  .modal-close:hover{background:rgba(255,255,255,.2);}
  .modal-body{padding:24px 28px;}
  .modal-section{margin-bottom:22px;}
  .modal-section-title{
    font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;
    color:var(--muted);margin-bottom:12px;padding-bottom:6px;
    border-bottom:1px solid var(--border);
  }
  .modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
  .modal-field .mf-label{font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;}
  .modal-field .mf-val{font-size:14px;font-weight:600;color:var(--text);}
  .modal-field.full{grid-column:1/-1;}

  .berkas-modal{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;}
  .bm-item{
    border:1px solid var(--border);border-radius:10px;padding:10px;
    display:flex;flex-direction:column;align-items:center;gap:6px;text-align:center;
  }
  .bm-item .bm-icon{font-size:22px;}
  .bm-item .bm-name{font-size:11px;font-weight:600;color:var(--text);}
  .bm-item .bm-status{font-size:11px;font-weight:600;}
  .bm-item.has{border-color:#a5d6a7;background:#f1f8e9;}
  .bm-item.has .bm-status{color:var(--ok);}
  .bm-item.none .bm-status{color:var(--err);}
  .bm-item a{font-size:10px;color:var(--teal);text-decoration:none;font-weight:600;}
  .bm-item a:hover{text-decoration:underline;}

  /* STATUS FORM */
  .status-form{
    background:var(--bg);border:1px solid var(--border);border-radius:12px;
    padding:18px 20px;margin-top:4px;
  }
  .status-form select,.status-form textarea{
    width:100%;padding:10px 12px;border:1.5px solid var(--border);
    border-radius:9px;font-size:14px;font-family:'DM Sans',sans-serif;
    color:var(--text);background:var(--white);outline:none;
    transition:border-color .2s;margin-bottom:12px;
  }
  .status-form select:focus,.status-form textarea:focus{border-color:var(--teal);}
  .status-form textarea{resize:vertical;min-height:70px;}
  .btn-update{
    padding:10px 22px;
    background:linear-gradient(135deg,var(--teal),var(--teal-lt));
    color:var(--white);border:none;border-radius:9px;
    font-family:'Sora',sans-serif;font-size:14px;font-weight:700;
    cursor:pointer;box-shadow:0 4px 12px rgba(13,115,119,.3);
    transition:transform .15s;
  }
  .btn-update:hover{transform:translateY(-2px);}

  .alert-ok{background:#e8f5e9;border-left:4px solid var(--ok);color:var(--ok);border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:14px;}

  @media(max-width:900px){
    .sidebar{display:none;}
    .main{padding:18px 14px;}
    .stat-grid{grid-template-columns:1fr 1fr;}
    .modal-grid{grid-template-columns:1fr;}
  }
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <div class="nav-logo">FB</div>
  <div>
    <div class="nav-title">SMK FBAK – Admin Panel</div>
    <div class="nav-sub">Sistem Manajemen Pendaftaran</div>
  </div>
  <div class="nav-spacer"></div>
  <span class="nav-admin">ADMIN</span>

<a href="ubah_password.php" class="nav-logout">
  Ubah Password
</a>

<a href="?logout=1" class="nav-logout">
  Keluar
</a>
</nav>

<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-title">Menu Utama</div>
    <a href="admin.php" class="nav-item active"><span class="icon">📋</span> Data Pendaftar</a>
    <a href="admin.php?tab=menunggu" class="nav-item"><span class="icon">⏳</span> Menunggu Verifikasi <span class="nav-count"><?= $stats['menunggu'] ?></span></a>
    <a href="admin.php?tab=diterima" class="nav-item"><span class="icon">✅</span> Diterima <span class="nav-count"><?= $stats['diterima'] ?></span></a>
    <a href="admin.php?tab=ditolak"  class="nav-item"><span class="icon">❌</span> Ditolak <span class="nav-count"><?= $stats['ditolak'] ?></span></a>
  </aside>

  <main class="main">

    <?php if (isset($_GET['updated'])): ?>
      <div class="alert-ok">✓ Status pendaftaran berhasil diperbarui.</div>
    <?php endif; ?>

    <!-- STATISTIK -->
    <div class="stat-grid">
      <div class="stat-card"><div class="stat-label">Total Pendaftar</div><div class="stat-val"><?= $stats['total'] ?></div></div>
      <div class="stat-card warn"><div class="stat-label">Menunggu</div><div class="stat-val"><?= $stats['menunggu'] ?></div></div>
      <div class="stat-card ok"><div class="stat-label">Diterima</div><div class="stat-val"><?= $stats['diterima'] ?></div></div>
      <div class="stat-card err"><div class="stat-label">Ditolak</div><div class="stat-val"><?= $stats['ditolak'] ?></div></div>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar">
      <div class="page-title">Data Pendaftar</div>
      <form class="search-wrap" method="GET">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <span class="search-icon">🔍</span>
        <input type="text" name="q" placeholder="Cari nama, NISN, email…" value="<?= htmlspecialchars($search) ?>">
      </form>
    </div>

    <!-- TABS -->
    <div class="tabs">
      <?php
      $tabDefs = [
        'semua'    => ['Semua', $stats['total']],
        'menunggu' => ['Menunggu', $stats['menunggu']],
        'diterima' => ['Diterima', $stats['diterima']],
        'ditolak'  => ['Ditolak',  $stats['ditolak']],
      ];
      foreach ($tabDefs as $k=>[$lbl,$cnt]): ?>
        <a class="tab <?= $tab===$k?'active':'' ?>" href="?tab=<?= $k ?><?= $search ? '&q='.urlencode($search) : '' ?>">
          <?= $lbl ?> <span class="tc"><?= $cnt ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- TABEL -->
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Nama Lengkap</th>
            <th>NISN</th>
            <th>Jurusan</th>
            <th>Asal Sekolah</th>
            <th>Berkas</th>
            <th>Status</th>
            <th>Tanggal Daftar</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="9" class="no-data">Tidak ada data pendaftar.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $i=>$r): ?>
              <?php
              $berkasItems = [
                'berkas_ijazah'=>'Ijazah','berkas_rapor'=>'Rapor',
                'berkas_kk'=>'KK','berkas_akte'=>'Akte',
                'berkas_foto'=>'Foto','berkas_sertifikat'=>'Sertif.',
                'berkas_bukti_bayar'=>'Bayar',
              ];
              $allOk = true;
              foreach (['berkas_ijazah','berkas_rapor','berkas_kk','berkas_akte','berkas_foto','berkas_bukti_bayar'] as $b)
                if (!$r[$b]) $allOk = false;
              ?>
              <tr>
                <td style="color:var(--muted);font-weight:600;"><?= $i+1 ?></td>
                <td>
                  <div style="font-weight:600;"><?= htmlspecialchars($r['nama_lengkap']) ?></div>
                  <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($r['email']) ?></div>
                </td>
                <td style="font-family:monospace;font-size:12px;"><?= htmlspecialchars($r['nisn']) ?></td>
                <td><span style="font-weight:600;"><?= htmlspecialchars($r['jurusan']) ?></span></td>
                <td style="font-size:12px;"><?= htmlspecialchars($r['asal_sekolah']) ?></td>
                <td>
                  <span class="b-chip <?= $allOk?'ok':'no' ?>">
                    <?= $allOk ? '✓ Lengkap' : '⚠ Kurang' ?>
                  </span>
                </td>
                <td>
                  <span class="pill" style="background:<?= $statusBg[$r['status']] ?>;color:<?= $statusColor[$r['status']] ?>;">
                    <?= $statusLabel[$r['status']] ?>
                  </span>
                </td>
                <td style="font-size:12px;color:var(--muted);"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                <td>
                  <a href="?tab=<?= $tab ?>&detail=<?= $r['id'] ?><?= $search?'&q='.urlencode($search):'' ?>" class="btn-detail">Detail</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </main>
</div>

<!-- MODAL DETAIL -->
<?php if ($detail): ?>
<div class="modal-overlay" onclick="if(event.target===this)window.location='?tab=<?= $tab ?>'">
  <div class="modal">
    <div class="modal-head">
      <h2>Detail Pendaftar</h2>
      <button class="modal-close" onclick="window.location='?tab=<?= $tab ?><?= $search?'&q='.urlencode($search):'' ?>'">✕</button>
    </div>
    <div class="modal-body">

      <!-- Status saat ini -->
      <div class="modal-section">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
          <span class="pill" style="background:<?= $statusBg[$detail['status']] ?>;color:<?= $statusColor[$detail['status']] ?>;font-size:13px;padding:6px 14px;">
            <?= $statusLabel[$detail['status']] ?>
          </span>
          <span style="font-size:13px;color:var(--muted);">Daftar: <?= date('d M Y', strtotime($detail['created_at'])) ?></span>
        </div>
        <?php if ($detail['catatan_admin']): ?>
          <div style="background:#fff8e1;border:1px solid #ffe082;border-radius:8px;padding:10px 12px;font-size:13px;color:#795500;">
            <strong>Catatan:</strong> <?= htmlspecialchars($detail['catatan_admin']) ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Data Pribadi -->
      <div class="modal-section">
        <div class="modal-section-title">👤 Data Pribadi</div>
        <div class="modal-grid">
          <div class="modal-field full"><div class="mf-label">Nama Lengkap</div><div class="mf-val"><?= htmlspecialchars($detail['nama_lengkap']) ?></div></div>
          <div class="modal-field"><div class="mf-label">Tempat / Tgl Lahir</div><div class="mf-val"><?= htmlspecialchars($detail['tempat_lahir']) ?>, <?= date('d M Y',strtotime($detail['tanggal_lahir'])) ?></div></div>
          <div class="modal-field"><div class="mf-label">Jenis Kelamin / Agama</div><div class="mf-val"><?= htmlspecialchars($detail['jenis_kelamin']) ?> / <?= htmlspecialchars($detail['agama']) ?></div></div>
          <div class="modal-field"><div class="mf-label">Email</div><div class="mf-val"><?= htmlspecialchars($detail['email']) ?></div></div>
          <div class="modal-field"><div class="mf-label">Nomor HP</div><div class="mf-val"><?= htmlspecialchars($detail['nomor_hp']) ?></div></div>
          <div class="modal-field full"><div class="mf-label">Alamat</div><div class="mf-val"><?= nl2br(htmlspecialchars($detail['alamat'])) ?></div></div>
        </div>
      </div>

      <!-- Data Akademik -->
      <div class="modal-section">
        <div class="modal-section-title">🎓 Data Akademik</div>
        <div class="modal-grid">
          <div class="modal-field"><div class="mf-label">NISN</div><div class="mf-val" style="font-family:monospace;"><?= htmlspecialchars($detail['nisn']) ?></div></div>
          <div class="modal-field"><div class="mf-label">Jurusan</div><div class="mf-val"><?= htmlspecialchars($detail['jurusan']) ?></div></div>
          <div class="modal-field"><div class="mf-label">Asal Sekolah</div><div class="mf-val"><?= htmlspecialchars($detail['asal_sekolah']) ?></div></div>
          <div class="modal-field"><div class="mf-label">Tahun Lulus</div><div class="mf-val"><?= htmlspecialchars($detail['tahun_lulus']) ?></div></div>
        </div>
      </div>

      <!-- Data Orang Tua -->
      <div class="modal-section">
        <div class="modal-section-title">👨‍👩‍👧 Data Orang Tua</div>
        <div class="modal-grid">
          <div class="modal-field"><div class="mf-label">Nama Ayah</div><div class="mf-val"><?= htmlspecialchars($detail['nama_ayah']) ?></div></div>
          <div class="modal-field"><div class="mf-label">Nama Ibu</div><div class="mf-val"><?= htmlspecialchars($detail['nama_ibu']) ?></div></div>
          <div class="modal-field"><div class="mf-label">Pekerjaan Ayah</div><div class="mf-val"><?= htmlspecialchars($detail['pekerjaan_ayah'] ?: '-') ?></div></div>
          <div class="modal-field"><div class="mf-label">Pekerjaan Ibu</div><div class="mf-val"><?= htmlspecialchars($detail['pekerjaan_ibu'] ?: '-') ?></div></div>
          <div class="modal-field"><div class="mf-label">HP Orang Tua</div><div class="mf-val"><?= htmlspecialchars($detail['nomor_hp_ortu']) ?></div></div>
        </div>
      </div>

      <!-- Berkas -->
      <div class="modal-section">
        <div class="modal-section-title">📎 Berkas Persyaratan</div>
        <div class="berkas-modal">
          <?php
          $bConfig = [
            'berkas_ijazah'    =>['📄','Ijazah/SKL'],
            'berkas_rapor'     =>['📚','Rapor Sem. 1-5'],
            'berkas_kk'        =>['🏠','Kartu Keluarga'],
            'berkas_akte'      =>['📋','Akte Kelahiran'],
            'berkas_foto'      =>['📷','Pas Foto'],
            'berkas_sertifikat'=>['🏆','Sertifikat'],
            'berkas_bukti_bayar'=>['💳','Bukti Bayar'],
          ];
          foreach ($bConfig as $k=>[$ic,$lb]): ?>
            <div class="bm-item <?= $detail[$k]?'has':'none' ?>">
              <div class="bm-icon"><?= $ic ?></div>
              <div class="bm-name"><?= $lb ?></div>
              <?php if ($detail[$k]): ?>
                <div class="bm-status">✓ Ada</div>
                <a href="uploads/<?= htmlspecialchars($detail[$k]) ?>" target="_blank">Lihat File</a>
              <?php else: ?>
                <div class="bm-status">✗ Tidak ada</div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- UBAH STATUS -->
      <div class="modal-section">
        <div class="modal-section-title">⚙️ Ubah Status Pendaftaran</div>
        <div class="status-form">
          <form method="POST">
            <input type="hidden" name="aksi" value="ubah_status">
            <input type="hidden" name="pendaftaran_id" value="<?= $detail['id'] ?>">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <div style="margin-bottom:10px;">
              <label style="display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;">Status</label>
              <select name="status">
                <?php foreach (['menunggu','diterima','ditolak'] as $s): ?>
                  <option value="<?= $s ?>" <?= $detail['status']===$s?'selected':'' ?>><?= $statusLabel[$s] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div style="margin-bottom:10px;">
              <label style="display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;">Catatan untuk Siswa (opsional)</label>
              <textarea name="catatan_admin" placeholder="Contoh: Berkas rapor kurang jelas, harap upload ulang."><?= htmlspecialchars($detail['catatan_admin'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn-update">Simpan Perubahan</button>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>
<?php endif; ?>

</body>
</html>
