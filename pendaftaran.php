<?php
// pendaftaran.php — Formulir Pendaftaran Siswa Baru (terpisah dari registrasi akun)
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['akun_id']) || $_SESSION['role'] !== 'siswa') {
    header('Location: login.php');
    exit;
}

$akun_id = $_SESSION['akun_id'];

// Cek apakah sudah pernah mengisi pendaftaran
$cek = $pdo->prepare("SELECT id FROM pendaftaran WHERE akun_id = ? LIMIT 1");
$cek->execute([$akun_id]);
$sudahDaftar = $cek->fetch();

$errors  = [];
$success = false;

// ── HANDLE SUBMIT ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$sudahDaftar) {
    // Validasi field teks wajib
    $requiredFields = [
        'nama_lengkap'  => 'Nama Lengkap',
        'tempat_lahir'  => 'Tempat Lahir',
        'tanggal_lahir' => 'Tanggal Lahir',
        'jenis_kelamin' => 'Jenis Kelamin',
        'agama'         => 'Agama',
        'alamat'        => 'Alamat',
        'nomor_hp'      => 'Nomor HP Siswa',
        'nisn'          => 'NISN',
        'asal_sekolah'  => 'Asal Sekolah',
        'tahun_lulus'   => 'Tahun Lulus',
        'jurusan'       => 'Pilihan Jurusan',
        'nama_ayah'     => 'Nama Ayah',
        'nama_ibu'      => 'Nama Ibu',
        'nomor_hp_ortu' => 'Nomor HP Orang Tua',
    ];

    $data = [];
    foreach ($requiredFields as $field => $label) {
        $data[$field] = trim($_POST[$field] ?? '');
        if (empty($data[$field])) $errors[] = "$label wajib diisi.";
    }
    // Field opsional
    $data['pekerjaan_ayah'] = trim($_POST['pekerjaan_ayah'] ?? '');
    $data['pekerjaan_ibu']  = trim($_POST['pekerjaan_ibu'] ?? '');

    if (!empty($data['nisn']) && (!ctype_digit($data['nisn']) || strlen($data['nisn']) !== 10))
        $errors[] = 'NISN harus tepat 10 digit angka.';

    // Cek NISN duplikat
    if (!empty($data['nisn'])) {
        $cekNisn = $pdo->prepare("SELECT id FROM pendaftaran WHERE nisn = ?");
        $cekNisn->execute([$data['nisn']]);
        if ($cekNisn->fetch()) $errors[] = 'NISN sudah terdaftar.';
    }

    // ── Upload berkas ──
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $berkasConfig = [
        'berkas_ijazah'     => ['label' => 'Ijazah/SKL',           'required' => true,  'maxMB' => 5],
        'berkas_rapor'      => ['label' => 'Rapor Semester 1–5',   'required' => true,  'maxMB' => 10],
        'berkas_kk'         => ['label' => 'Kartu Keluarga',       'required' => true,  'maxMB' => 3],
        'berkas_akte'       => ['label' => 'Akte Kelahiran',       'required' => true,  'maxMB' => 3],
        'berkas_foto'       => ['label' => 'Pas Foto',             'required' => true,  'maxMB' => 2],
        'berkas_sertifikat' => ['label' => 'Sertifikat Pendukung', 'required' => false, 'maxMB' => 5],
        'berkas_bukti_bayar'=> ['label' => 'Bukti Pembayaran',     'required' => true,  'maxMB' => 3],
    ];

    $berkasPath  = [];
    $allowedExt  = ['jpg', 'jpeg', 'png', 'pdf'];

    foreach ($berkasConfig as $key => $cfg) {
        $noFile = !isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE;
        if ($noFile) {
            if ($cfg['required']) $errors[] = $cfg['label'] . ' wajib diupload.';
            $berkasPath[$key] = null;
            continue;
        }
        $file = $_FILES[$key];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Gagal mengupload ' . $cfg['label'] . '.';
            $berkasPath[$key] = null;
            continue;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) {
            $errors[] = $cfg['label'] . ' harus berformat JPG, PNG, atau PDF.';
            $berkasPath[$key] = null;
            continue;
        }
        if ($file['size'] > $cfg['maxMB'] * 1024 * 1024) {
            $errors[] = $cfg['label'] . " maksimal {$cfg['maxMB']}MB.";
            $berkasPath[$key] = null;
            continue;
        }
        $namaFile = $key . '_' . $akun_id . '_' . time() . '.' . $ext;
        $berkasPath[$key] = $namaFile;
    }

    if (empty($errors)) {
        // Pindahkan semua file
        foreach ($berkasConfig as $key => $cfg) {
            if (!empty($berkasPath[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
                move_uploaded_file($_FILES[$key]['tmp_name'], $uploadDir . $berkasPath[$key]);
            }
        }
        // Simpan ke database
        $sql = "INSERT INTO pendaftaran
            (akun_id,nama_lengkap,tempat_lahir,tanggal_lahir,jenis_kelamin,agama,alamat,nomor_hp,
             nisn,asal_sekolah,tahun_lulus,jurusan,nama_ayah,nama_ibu,pekerjaan_ayah,pekerjaan_ibu,
             nomor_hp_ortu,berkas_ijazah,berkas_rapor,berkas_kk,berkas_akte,berkas_foto,
             berkas_sertifikat,berkas_bukti_bayar)
            VALUES
            (:akun_id,:nama_lengkap,:tempat_lahir,:tanggal_lahir,:jenis_kelamin,:agama,:alamat,:nomor_hp,
             :nisn,:asal_sekolah,:tahun_lulus,:jurusan,:nama_ayah,:nama_ibu,:pekerjaan_ayah,:pekerjaan_ibu,
             :nomor_hp_ortu,:berkas_ijazah,:berkas_rapor,:berkas_kk,:berkas_akte,:berkas_foto,
             :berkas_sertifikat,:berkas_bukti_bayar)";
        $ins = $pdo->prepare($sql);
        $ins->execute(array_merge(
            ['akun_id' => $akun_id],
            $data,
            [
                'berkas_ijazah'     => $berkasPath['berkas_ijazah']     ?? null,
                'berkas_rapor'      => $berkasPath['berkas_rapor']      ?? null,
                'berkas_kk'         => $berkasPath['berkas_kk']         ?? null,
                'berkas_akte'       => $berkasPath['berkas_akte']       ?? null,
                'berkas_foto'       => $berkasPath['berkas_foto']       ?? null,
                'berkas_sertifikat' => $berkasPath['berkas_sertifikat'] ?? null,
                'berkas_bukti_bayar'=> $berkasPath['berkas_bukti_bayar']?? null,
            ]
        ));
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Formulir Pendaftaran – SMK FBAK</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{
    --teal:#0d7377;--teal-dk:#0a5c5f;--teal-lt:#14a2a8;
    --gold:#e8a020;--gold-lt:#f5c35a;
    --navy:#0d1f2d;--navy2:#122535;--white:#ffffff;--bg:#f0f6f7;
    --text:#1a2e3b;--muted:#5a7080;--border:#cde4e5;
    --err:#e53935;--ok:#2e7d32;
  }
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}

  /* NAVBAR */
  .navbar{
    background:linear-gradient(90deg,var(--navy),var(--navy2) 50%,#0d3d42);
    height:64px;display:flex;align-items:center;padding:0 28px;gap:14px;
    box-shadow:0 2px 16px rgba(0,0,0,.3);position:sticky;top:0;z-index:100;
  }
  .nav-logo{
    width:40px;height:40px;border-radius:50%;
    background:linear-gradient(135deg,var(--teal),var(--teal-lt));
    display:flex;align-items:center;justify-content:center;
    font-family:'Sora',sans-serif;font-weight:800;font-size:13px;color:var(--white);
    box-shadow:0 4px 12px rgba(13,115,119,.5);flex-shrink:0;letter-spacing:-1px;
  }
  .nav-title{font-family:'Sora',sans-serif;font-weight:800;font-size:17px;color:var(--white);}
  .nav-sub{font-size:11px;color:var(--gold-lt);}
  .nav-spacer{flex:1;}
  .nav-back{
    padding:8px 16px;border-radius:8px;background:rgba(255,255,255,.12);
    color:rgba(255,255,255,.85);text-decoration:none;font-size:13px;font-weight:600;
    transition:background .2s;
  }
  .nav-back:hover{background:rgba(255,255,255,.2);}

  /* CONTAINER */
  .container{max-width:860px;margin:0 auto;padding:32px 20px 60px;}
  .page-header{margin-bottom:28px;}
  .page-title{font-family:'Sora',sans-serif;font-weight:800;font-size:24px;color:var(--navy);}
  .page-sub{color:var(--muted);font-size:14px;margin-top:4px;}

  /* ALREADY REGISTERED */
  .already-card{
    background:var(--white);border-radius:16px;padding:40px;text-align:center;
    border:1px solid var(--border);box-shadow:0 4px 16px rgba(0,0,0,.05);
  }
  .already-icon{font-size:56px;margin-bottom:16px;}
  .already-title{font-family:'Sora',sans-serif;font-size:20px;font-weight:800;color:var(--navy);margin-bottom:8px;}
  .already-sub{color:var(--muted);font-size:14px;line-height:1.6;margin-bottom:24px;}
  .btn-back{
    display:inline-block;padding:12px 28px;
    background:linear-gradient(135deg,var(--teal),var(--teal-lt));
    color:var(--white);border-radius:12px;font-family:'Sora',sans-serif;
    font-size:14px;font-weight:700;text-decoration:none;
    box-shadow:0 6px 18px rgba(13,115,119,.3);transition:transform .15s;
  }
  .btn-back:hover{transform:translateY(-2px);}

  /* SUCCESS */
  .success-card{
    background:var(--white);border-radius:16px;padding:40px;text-align:center;
    border:2px solid #a5d6a7;box-shadow:0 4px 24px rgba(46,125,50,.1);
  }
  .success-icon{font-size:64px;margin-bottom:16px;}
  .success-title{font-family:'Sora',sans-serif;font-size:22px;font-weight:800;color:var(--ok);margin-bottom:10px;}
  .success-sub{color:var(--muted);font-size:14px;line-height:1.7;margin-bottom:24px;}

  /* ALERT */
  .alert-err{
    background:#fde8e8;border-left:4px solid var(--err);color:var(--err);
    border-radius:10px;padding:14px 16px;margin-bottom:24px;font-size:14px;
  }
  .alert-err ul{margin:8px 0 0 20px;line-height:1.8;}

  /* SECTION CARD */
  .section-card{
    background:var(--white);border-radius:14px;padding:28px 28px;
    border:1px solid var(--border);box-shadow:0 2px 10px rgba(0,0,0,.04);
    margin-bottom:20px;
  }
  .section-title{
    font-family:'Sora',sans-serif;font-weight:700;font-size:15px;color:var(--navy);
    margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid var(--border);
    display:flex;align-items:center;gap:8px;
  }
  .section-title .icon{
    width:32px;height:32px;border-radius:8px;
    background:linear-gradient(135deg,var(--teal),var(--teal-lt));
    display:flex;align-items:center;justify-content:center;font-size:15px;
  }

  /* FORM */
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
  .grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;}
  .field{margin-bottom:0;}
  .field.full{grid-column:1/-1;}
  label.flabel{
    display:block;font-weight:600;font-size:12px;color:var(--text);
    margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px;
  }
  label.flabel .req{color:var(--err);}
  input[type=text],input[type=tel],input[type=date],input[type=number],
  select,textarea{
    width:100%;padding:11px 14px;border:1.5px solid var(--border);
    border-radius:10px;font-size:14px;font-family:'DM Sans',sans-serif;
    color:var(--text);background:#fafdfd;
    transition:border-color .2s,box-shadow .2s;outline:none;
    appearance:none;-webkit-appearance:none;
  }
  select{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath d='M0 0l6 8 6-8z' fill='%235a7080'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:36px;}
  textarea{resize:vertical;min-height:80px;}
  input:focus,select:focus,textarea:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(13,115,119,.12);background:var(--white);}

  /* BERKAS UPLOAD */
  .berkas-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
  .upload-item{
    border:2px dashed var(--border);border-radius:12px;padding:16px;
    transition:border-color .2s,background .2s;cursor:pointer;position:relative;
  }
  .upload-item:hover{border-color:var(--teal);background:#f0f8f9;}
  .upload-item.wajib{border-color:#b3dfe1;}
  .upload-header{display:flex;align-items:center;gap:10px;margin-bottom:10px;}
  .upload-icon{font-size:22px;}
  .upload-name{font-family:'Sora',sans-serif;font-weight:700;font-size:13px;color:var(--navy);}
  .upload-badge{
    margin-left:auto;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;
  }
  .badge-req{background:#fde8e8;color:var(--err);}
  .badge-opt{background:#e8f7f8;color:var(--teal);}
  .upload-hint{font-size:11px;color:var(--muted);margin-bottom:8px;}
  input[type=file]{
    width:100%;padding:6px;border:1.5px solid var(--border);border-radius:8px;
    font-size:12px;background:var(--white);cursor:pointer;
  }
  input[type=file]::file-selector-button{
    padding:5px 12px;border:none;border-radius:6px;
    background:var(--teal);color:var(--white);font-weight:600;font-size:11px;
    cursor:pointer;margin-right:8px;font-family:'DM Sans',sans-serif;
  }

  /* SUBMIT BUTTON */
  .btn-submit{
    width:100%;padding:15px;
    background:linear-gradient(135deg,var(--teal),var(--teal-lt));
    color:var(--white);border:none;border-radius:14px;
    font-family:'Sora',sans-serif;font-size:16px;font-weight:700;
    cursor:pointer;margin-top:8px;
    box-shadow:0 8px 24px rgba(13,115,119,.35);
    transition:transform .15s,box-shadow .15s;
  }
  .btn-submit:hover{transform:translateY(-2px);box-shadow:0 12px 32px rgba(13,115,119,.45);}
  .submit-note{text-align:center;font-size:12px;color:var(--muted);margin-top:10px;}

  @media(max-width:700px){
    .grid2,.grid3,.berkas-grid{grid-template-columns:1fr;}
    .container{padding:20px 12px 40px;}
    .section-card{padding:20px 16px;}
  }
</style>
</head>
<body>

<nav class="navbar">
  <div class="nav-logo">FB</div>
  <div>
    <div class="nav-title">SMK FBAK</div>
    <div class="nav-sub">Formulir Pendaftaran Siswa Baru</div>
  </div>
  <div class="nav-spacer"></div>
  <a href="index.php" class="nav-back">← Kembali ke Dashboard</a>
</nav>

<div class="container">
  <div class="page-header">
    <div class="page-title">📝 Formulir Pendaftaran Siswa Baru</div>
    <p class="page-sub">Isi semua data dengan lengkap dan benar, lalu upload berkas yang diperlukan.</p>
  </div>

  <?php if ($sudahDaftar && !$success): ?>
    <!-- Sudah pernah mendaftar -->
    <div class="already-card">
      <div class="already-icon">✅</div>
      <div class="already-title">Formulir Sudah Dikirimkan</div>
      <p class="already-sub">Anda sudah mengisi formulir pendaftaran. Pantau status pendaftaran Anda melalui dashboard.<br>Hubungi pihak sekolah jika ingin melakukan perubahan data.</p>
      <a href="index.php?sec=status" class="btn-back">Lihat Status Pendaftaran</a>
    </div>

  <?php elseif ($success): ?>
    <!-- Sukses -->
    <div class="success-card">
      <div class="success-icon">🎉</div>
      <div class="success-title">Pendaftaran Berhasil Dikirimkan!</div>
      <p class="success-sub">
        Formulir pendaftaran Anda telah kami terima dan sedang dalam proses verifikasi oleh tim admin SMK FBAK.<br>
        Pantau status pendaftaran Anda di dashboard. Kami akan menginformasikan hasilnya secepatnya.
      </p>
      <a href="index.php?sec=status" class="btn-back">Lihat Status Pendaftaran</a>
    </div>

  <?php else: ?>
    <!-- Form pendaftaran -->

    <?php if (!empty($errors)): ?>
      <div class="alert-err">
        <strong>⚠ Harap perbaiki kesalahan berikut:</strong>
        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

      <!-- 1. Data Pribadi -->
      <div class="section-card">
        <div class="section-title">
          <div class="icon">👤</div> Data Pribadi Siswa
        </div>
        <div class="grid2" style="gap:16px;">
          <div class="field full">
            <label class="flabel">Nama Lengkap <span class="req">*</span></label>
            <input type="text" name="nama_lengkap" placeholder="Sesuai ijazah/akte kelahiran"
                   value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label class="flabel">Tempat Lahir <span class="req">*</span></label>
            <input type="text" name="tempat_lahir" placeholder="Kota/Kabupaten"
                   value="<?= htmlspecialchars($_POST['tempat_lahir'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label class="flabel">Tanggal Lahir <span class="req">*</span></label>
            <input type="date" name="tanggal_lahir"
                   value="<?= htmlspecialchars($_POST['tanggal_lahir'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label class="flabel">Jenis Kelamin <span class="req">*</span></label>
            <select name="jenis_kelamin" required>
              <option value="">-- Pilih --</option>
              <option value="Laki-laki" <?= (($_POST['jenis_kelamin']??'')==='Laki-laki')?'selected':'' ?>>Laki-laki</option>
              <option value="Perempuan" <?= (($_POST['jenis_kelamin']??'')==='Perempuan')?'selected':'' ?>>Perempuan</option>
            </select>
          </div>
          <div class="field">
            <label class="flabel">Agama <span class="req">*</span></label>
            <select name="agama" required>
              <option value="">-- Pilih --</option>
              <?php foreach(['Islam','Kristen Protestan','Kristen Katolik','Hindu','Buddha','Konghucu'] as $ag): ?>
              <option value="<?= $ag ?>" <?= (($_POST['agama']??'')===$ag)?'selected':'' ?>><?= $ag ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label class="flabel">Nomor HP Siswa <span class="req">*</span></label>
            <input type="tel" name="nomor_hp" placeholder="08xxxxxxxxxx"
                   value="<?= htmlspecialchars($_POST['nomor_hp'] ?? '') ?>" required>
          </div>
          <div class="field full">
            <label class="flabel">Alamat Lengkap <span class="req">*</span></label>
            <textarea name="alamat" placeholder="Jalan, RT/RW, Desa/Kelurahan, Kecamatan, Kab/Kota"><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- 2. Data Akademik -->
      <div class="section-card">
        <div class="section-title">
          <div class="icon">🎓</div> Data Akademik
        </div>
        <div class="grid2" style="gap:16px;">
          <div class="field">
            <label class="flabel">NISN <span class="req">*</span></label>
            <input type="text" name="nisn" placeholder="10 digit angka" maxlength="10"
                   value="<?= htmlspecialchars($_POST['nisn'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label class="flabel">Tahun Lulus SMP <span class="req">*</span></label>
            <input type="number" name="tahun_lulus" placeholder="<?= date('Y') ?>"
                   min="2010" max="<?= date('Y')+1 ?>"
                   value="<?= htmlspecialchars($_POST['tahun_lulus'] ?? '') ?>" required>
          </div>
          <div class="field full">
            <label class="flabel">Asal Sekolah (SMP/MTs) <span class="req">*</span></label>
            <input type="text" name="asal_sekolah" placeholder="Nama sekolah asal"
                   value="<?= htmlspecialchars($_POST['asal_sekolah'] ?? '') ?>" required>
          </div>
          <div class="field full">
            <label class="flabel">Pilihan Jurusan <span class="req">*</span></label>
            <select name="jurusan" required>
              <option value="">-- Pilih Jurusan --</option>
              <?php foreach(['TKJ'=>'TKJ – Teknik Komputer & Jaringan','Multimedia'=>'Multimedia','RPL'=>'RPL – Rekayasa Perangkat Lunak','Informatika'=>'Informatika','Mesin'=>'Teknik Mesin','Sipil'=>'Teknik Sipil'] as $val=>$lbl): ?>
              <option value="<?= $val ?>" <?= (($_POST['jurusan']??'')===$val)?'selected':'' ?>><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- 3. Data Orang Tua -->
      <div class="section-card">
        <div class="section-title">
          <div class="icon">👨‍👩‍👧</div> Data Orang Tua / Wali
        </div>
        <div class="grid2" style="gap:16px;">
          <div class="field">
            <label class="flabel">Nama Ayah <span class="req">*</span></label>
            <input type="text" name="nama_ayah" placeholder="Nama lengkap ayah"
                   value="<?= htmlspecialchars($_POST['nama_ayah'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label class="flabel">Nama Ibu <span class="req">*</span></label>
            <input type="text" name="nama_ibu" placeholder="Nama lengkap ibu"
                   value="<?= htmlspecialchars($_POST['nama_ibu'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label class="flabel">Pekerjaan Ayah</label>
            <input type="text" name="pekerjaan_ayah" placeholder="Opsional"
                   value="<?= htmlspecialchars($_POST['pekerjaan_ayah'] ?? '') ?>">
          </div>
          <div class="field">
            <label class="flabel">Pekerjaan Ibu</label>
            <input type="text" name="pekerjaan_ibu" placeholder="Opsional"
                   value="<?= htmlspecialchars($_POST['pekerjaan_ibu'] ?? '') ?>">
          </div>
          <div class="field full">
            <label class="flabel">Nomor HP Orang Tua/Wali <span class="req">*</span></label>
            <input type="tel" name="nomor_hp_ortu" placeholder="08xxxxxxxxxx"
                   value="<?= htmlspecialchars($_POST['nomor_hp_ortu'] ?? '') ?>" required>
          </div>
        </div>
      </div>

      <!-- 4. Upload Berkas -->
      <div class="section-card">
        <div class="section-title">
          <div class="icon">📎</div> Upload Berkas Persyaratan
        </div>
        <p style="font-size:13px;color:var(--muted);margin-bottom:18px;">
          Format yang diterima: <strong>JPG, PNG, PDF</strong>. Pastikan berkas terbaca dengan jelas.
          <span style="color:var(--err);font-weight:600;">*</span> = Wajib diupload.
        </p>
        <div class="berkas-grid">

          <div class="upload-item wajib">
            <div class="upload-header">
              <span class="upload-icon">📄</span>
              <span class="upload-name">Ijazah / SKL</span>
              <span class="upload-badge badge-req">Wajib</span>
            </div>
            <p class="upload-hint">Ijazah SMP atau Surat Keterangan Lulus. Maks. 5MB.</p>
            <input type="file" name="berkas_ijazah" accept=".jpg,.jpeg,.png,.pdf">
          </div>

          <div class="upload-item wajib">
            <div class="upload-header">
              <span class="upload-icon">📊</span>
              <span class="upload-name">Rapor Semester 1–5</span>
              <span class="upload-badge badge-req">Wajib</span>
            </div>
            <p class="upload-hint">Scan rapor dari semester 1 sampai 5 (bisa digabung PDF). Maks. 10MB.</p>
            <input type="file" name="berkas_rapor" accept=".jpg,.jpeg,.png,.pdf">
          </div>

          <div class="upload-item wajib">
            <div class="upload-header">
              <span class="upload-icon">🏠</span>
              <span class="upload-name">Kartu Keluarga (KK)</span>
              <span class="upload-badge badge-req">Wajib</span>
            </div>
            <p class="upload-hint">Scan Kartu Keluarga yang masih berlaku. Maks. 3MB.</p>
            <input type="file" name="berkas_kk" accept=".jpg,.jpeg,.png,.pdf">
          </div>

          <div class="upload-item wajib">
            <div class="upload-header">
              <span class="upload-icon">📋</span>
              <span class="upload-name">Akte Kelahiran</span>
              <span class="upload-badge badge-req">Wajib</span>
            </div>
            <p class="upload-hint">Scan akte kelahiran siswa. Maks. 3MB.</p>
            <input type="file" name="berkas_akte" accept=".jpg,.jpeg,.png,.pdf">
          </div>

          <div class="upload-item wajib">
            <div class="upload-header">
              <span class="upload-icon">🖼️</span>
              <span class="upload-name">Pas Foto</span>
              <span class="upload-badge badge-req">Wajib</span>
            </div>
            <p class="upload-hint">Foto formal terbaru, latar merah/biru, ukuran 3×4 atau 4×6. Maks. 2MB.</p>
            <input type="file" name="berkas_foto" accept=".jpg,.jpeg,.png">
          </div>

          <div class="upload-item">
            <div class="upload-header">
              <span class="upload-icon">🏆</span>
              <span class="upload-name">Sertifikat Pendukung</span>
              <span class="upload-badge badge-opt">Opsional</span>
            </div>
            <p class="upload-hint">Sertifikat prestasi, olimpiade, atau keahlian (jika ada). Maks. 5MB.</p>
            <input type="file" name="berkas_sertifikat" accept=".jpg,.jpeg,.png,.pdf">
          </div>

          <div class="upload-item wajib" style="grid-column:1/-1;">
            <div class="upload-header">
              <span class="upload-icon">💳</span>
              <span class="upload-name">Bukti Pembayaran Pendaftaran</span>
              <span class="upload-badge badge-req">Wajib</span>
            </div>
            <p class="upload-hint">Bukti Pembayaran VA. Maks. 3MB.</p>
            <input type="file" name="berkas_bukti_bayar" accept=".jpg,.jpeg,.png,.pdf">
          </div>

        </div>
      </div>

      <!-- Submit -->
      <div class="section-card">
        <div style="background:#fff8e1;border:1px solid #ffe082;border-radius:10px;padding:14px 16px;margin-bottom:20px;font-size:13px;color:#5d4037;line-height:1.6;">
          ⚠️ <strong>Perhatian:</strong> Pastikan semua data yang diisi sudah benar. Formulir yang sudah dikirimkan <strong>tidak dapat diubah</strong> tanpa menghubungi pihak sekolah.
        </div>
        <button type="submit" class="btn-submit">📨 Kirim Formulir Pendaftaran</button>
        <p class="submit-note">Dengan mengklik tombol di atas, Anda menyatakan bahwa semua data yang diisikan adalah benar dan dapat dipertanggungjawabkan.</p>
      </div>

    </form>
  <?php endif; ?>
</div>

</body>
</html>
