<?php
error_reporting(0);
$baseDir = __DIR__;
$currentPath = $_GET['path'] ?? '';
$realPath = realpath($baseDir . '/' . $currentPath);

// Security check
if ($realPath === false || strpos($realPath, realpath($baseDir)) !== 0) {
    die("Access Denied");
}

// Logic paths to handle file vs directory operations safely
$workingDir = is_dir($realPath) ? $realPath : dirname($realPath);
$workingPath = is_dir($realPath) ? $currentPath : dirname($currentPath);

/* ========= HELPER FUNCTIONS ========= */
function rcopy($src, $dst) {
    if (is_dir($src)) {
        if(!file_exists($dst)) mkdir($dst);
        $files = scandir($src);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") rcopy("$src/$file", "$dst/$file");
        }
    } else if (file_exists($src)) {
        copy($src, $dst);
    }
}

function addFolderToZip($dir, $zipArchive, $zipdir = ''){
    if (is_dir($dir)) {
        if ($handle = opendir($dir)) {
            while (($file = readdir($handle)) !== false) {
                if ($file != '.' && $file != '..') {
                    $path = $dir . '/' . $file;
                    $localPath = ltrim($zipdir . '/' . $file, '/');
                    if (is_file($path)) {
                        $zipArchive->addFile($path, $localPath);
                    } elseif (is_dir($path)) {
                        $zipArchive->addEmptyDir($localPath);
                        addFolderToZip($path, $zipArchive, $localPath);
                    }
                }
            }
            closedir($handle);
        }
    }
}

/* ========= LOGIC ========= */

// 1. Upload File/Image
if(isset($_FILES['upload_file'])) {
    move_uploaded_file($_FILES['upload_file']['tmp_name'], $workingDir . '/' . $_FILES['upload_file']['name']);
    header("Location: ?path=" . $workingPath); exit;
}

// 2. Download File
if(isset($_GET['download'])){
    $file = $workingDir . '/' . basename($_GET['download']);
    if(is_file($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

// 3. Delete
if(isset($_GET['delete'])){
    $target = $workingDir . '/' . basename($_GET['delete']);
    if(is_file($target)) { unlink($target); }
    elseif(is_dir($target)) { 
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        rmdir($target); 
    }
    header("Location: ?path=" . $workingPath); exit;
}

// 4. Create File/Folder
if(isset($_POST['create_item'])){
    $name = basename($_POST['item_name']);
    $type = $_POST['item_type'];
    if(!empty($name)){
        ($type === 'file') ? file_put_contents($workingDir.'/'.$name, '') : mkdir($workingDir.'/'.$name);
    }
    header("Location: ?path=" . $workingPath); exit;
}

// 5. Save Edited File
if(isset($_POST['save'])){
    if(strpos(realpath($_POST['file']), realpath($baseDir)) === 0){
        file_put_contents($_POST['file'], $_POST['content']);
    }
    header("Location: ?path=" . $currentPath . "&edit=1"); exit;
}

// 6. Rename Item
if(isset($_POST['rename_item'])){
    $old = $workingDir . '/' . basename($_POST['old_name']);
    $new = $workingDir . '/' . basename($_POST['new_name']);
    if(file_exists($old)) rename($old, $new);
    header("Location: ?path=" . $workingPath); exit;
}

// 7. Move / Copy Item
if(isset($_POST['action_item'])){
    $source = $workingDir . '/' . basename($_POST['source_name']);
    $action = $_POST['action_type'];
    $destPath = trim($_POST['dest_path'], '/');
    $destDir = realpath($baseDir . '/' . $destPath);
    
    if($destDir !== false && strpos($destDir, realpath($baseDir)) === 0 && file_exists($source)){
        $target = $destDir . '/' . basename($_POST['source_name']);
        if($action === 'move') { rename($source, $target); } 
        elseif($action === 'copy') { rcopy($source, $target); }
    }
    header("Location: ?path=" . $workingPath); exit;
}

// 8. Extract (Unzip)
if(isset($_GET['unzip'])){
    $file = $workingDir . '/' . basename($_GET['unzip']);
    if(class_exists('ZipArchive') && is_file($file)) {
        $zip = new ZipArchive;
        if ($zip->open($file) === TRUE) {
            $extractPath = $workingDir . '/' . basename($file, '.zip');
            if(!file_exists($extractPath)) mkdir($extractPath);
            $zip->extractTo($extractPath);
            $zip->close();
        }
    }
    header("Location: ?path=" . $workingPath); exit;
}

// 9. Create Zip (Archive)
if(isset($_POST['zip_item'])){
    $source = $workingDir . '/' . basename($_POST['source_name']);
    $zipName = basename($_POST['zip_name']);
    if(!str_ends_with($zipName, '.zip')) $zipName .= '.zip';
    $targetZip = $workingDir . '/' . $zipName;

    if(class_exists('ZipArchive') && file_exists($source)) {
        $zip = new ZipArchive();
        if($zip->open($targetZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            if(is_file($source)){
                $zip->addFile($source, basename($source));
            } elseif(is_dir($source)){
                addFolderToZip($source, $zip, basename($source));
            }
            $zip->close();
        }
    }
    header("Location: ?path=" . $workingPath); exit;
}

// 10. Change Permissions (Chmod)
if(isset($_POST['chmod_item'])){
    $target = $workingDir . '/' . basename($_POST['chmod_name']);
    $perms = octdec($_POST['chmod_val']);
    if(file_exists($target)) chmod($target, $perms);
    header("Location: ?path=" . $workingPath); exit;
}

// 11. Duplicate File/Folder
if(isset($_GET['duplicate'])){
    $source = $workingDir . '/' . basename($_GET['duplicate']);
    if(file_exists($source)){
        $info = pathinfo($source);
        $newName = $info['filename'] . ' (copy)' . (isset($info['extension']) ? '.' . $info['extension'] : '');
        $target = $workingDir . '/' . $newName;
        if(is_dir($source)) rcopy($source, $target);
        else copy($source, $target);
    }
    header("Location: ?path=" . $workingPath); exit;
}

/* ========= UI GENERATION ========= */
function listFiles($dir, $relativePath=''){
    $files = array_diff(scandir($dir), array('.', '..'));
    usort($files, function($a, $b) use ($dir) {
        $aIsDir = is_dir($dir . '/' . $a);
        $bIsDir = is_dir($dir . '/' . $b);
        if ($aIsDir === $bIsDir) return strcasecmp($a, $b);
        return $aIsDir ? -1 : 1;
    });

    if(empty($files)) echo "<div class='empty-state'>Folder is empty</div>";
    
    $i = 0;
    foreach($files as $file){
        $i++;
        $full = $dir.'/'.$file;
        $path = ltrim($relativePath.'/'.$file,'/');
        $isDir = is_dir($full);
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $perms = substr(sprintf('%o', fileperms($full)), -4);
        $modTime = date("d M Y H:i", filemtime($full));
        $sizeRaw = $isDir ? "-" : round(filesize($full)/1024, 2);
        $sizeStr = $isDir ? "--" : $sizeRaw . " KB";
        $typeStr = $isDir ? "Folder" : "File";
        
        $imgExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        $isImg = in_array($ext, $imgExts);
        
        // Define primary click action based on type
        $clickLink = $isDir ? "?path=$path" : ($isImg ? "?path=$path&view=1" : "?path=$path&edit=1");
        
        echo "<div class='file-card' data-name='".strtolower($file)."'>";
        
        // Meta Info
        echo "<div class='f-meta'>";
        if($isDir){
            echo '<div class="f-icon folder"><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg></div>';
            echo "<div class='f-details'><a href='$clickLink' class='f-name'>$file</a><span class='f-sub'>Folder • $perms</span></div>";
        } else {
            // Icon logic
            if($isImg) {
                $iconSvg = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>';
            } elseif($ext == 'zip') {
                $iconSvg = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>';
            } else {
                $iconSvg = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>';
            }
            echo '<div class="f-icon file">'.$iconSvg.'</div>';
            echo "<div class='f-details'><a href='$clickLink' class='f-name'>$file</a><span class='f-sub'>$sizeStr • $perms</span></div>";
        }
        echo "</div>";
        
        // 3-Dot Menu
        echo "<div class='dropdown'>";
        echo "<button class='btn-icon dropdown-btn' onclick='toggleDropdown(\"menu-$i\", event)' title='Options'>⋮</button>";
        echo "<div class='dropdown-menu' id='menu-$i'>";
        
        // Menu Items with SVGs and <hr> lines
        echo "<a href='#' onclick='openInfo(\"$file\", \"$typeStr\", \"$sizeStr\", \"$modTime\", \"$perms\")' class='dropdown-item'><svg viewBox='0 0 24 24'><circle cx='12' cy='12' r='10'></circle><line x1='12' y1='16' x2='12' y2='12'></line><line x1='12' y1='8' x2='12.01' y2='8'></line></svg> File Info</a><hr>";
        
        if(!$isDir) echo "<a href='?path=$relativePath&download=$file' class='dropdown-item'><svg viewBox='0 0 24 24'><path d='M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4'></path><polyline points='7 10 12 15 17 10'></polyline><line x1='12' y1='15' x2='12' y2='3'></line></svg> Download</a><hr>";
        
        if(!$isDir && $ext == 'zip') echo "<a href='?path=$relativePath&unzip=$file' class='dropdown-item' style='color:#10b981;'><svg viewBox='0 0 24 24'><path d='M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z'></path><polyline points='3.27 6.96 12 12.01 20.73 6.96'></polyline><line x1='12' y1='22.08' x2='12' y2='12'></line></svg> Extract (Unzip)</a><hr>";
        
        if(!$isDir && $isImg) echo "<a href='?path=$path&view=1' class='dropdown-item'><svg viewBox='0 0 24 24'><rect x='3' y='3' width='18' height='18' rx='2' ry='2'></rect><circle cx='8.5' cy='8.5' r='1.5'></circle><polyline points='21 15 16 10 5 21'></polyline></svg> View Image</a><hr>";
        
        if(!$isDir && !$isImg) echo "<a href='?path=$path&edit=1' class='dropdown-item'><svg viewBox='0 0 24 24'><polyline points='16 18 22 12 16 6'></polyline><polyline points='8 6 2 12 8 18'></polyline></svg> Edit Code</a><hr>";
        
        echo "<a href='#' onclick='openRename(\"$file\")' class='dropdown-item'><svg viewBox='0 0 24 24'><path d='M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7'></path><path d='M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z'></path></svg> Rename</a><hr>";
        
        echo "<a href='?path=$relativePath&duplicate=$file' class='dropdown-item'><svg viewBox='0 0 24 24'><rect x='9' y='9' width='13' height='13' rx='2' ry='2'></rect><path d='M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1'></path></svg> Duplicate</a><hr>";
        
        echo "<a href='#' onclick='openAction(\"$file\", \"copy\", \"$relativePath\")' class='dropdown-item'><svg viewBox='0 0 24 24'><path d='M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2'></path><rect x='8' y='2' width='8' height='4' rx='1' ry='1'></rect></svg> Copy to...</a><hr>";
        
        echo "<a href='#' onclick='openAction(\"$file\", \"move\", \"$relativePath\")' class='dropdown-item'><svg viewBox='0 0 24 24'><polyline points='5 9 2 12 5 15'></polyline><polyline points='9 5 12 2 15 5'></polyline><polyline points='19 9 22 12 19 15'></polyline><polyline points='9 19 12 22 15 19'></polyline><line x1='2' y1='12' x2='22' y2='12'></line><line x1='12' y1='2' x2='12' y2='22'></line></svg> Move to...</a><hr>";
        
        echo "<a href='#' onclick='openZip(\"$file\")' class='dropdown-item'><svg viewBox='0 0 24 24'><path d='M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z'></path></svg> Compress (Zip)</a><hr>";
        
        echo "<a href='#' onclick='openChmod(\"$file\", \"$perms\")' class='dropdown-item'><svg viewBox='0 0 24 24'><rect x='3' y='11' width='18' height='11' rx='2' ry='2'></rect><path d='M7 11V7a5 5 0 0 1 10 0v4'></path></svg> Permissions</a><hr>";
        
        echo "<a href='?path=$relativePath&delete=$file' class='dropdown-item danger' onclick='return confirm(\"Are you sure you want to delete `$file`?\")'><svg viewBox='0 0 24 24'><polyline points='3 6 5 6 21 6'></polyline><path d='M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2'></path><line x1='10' y1='11' x2='10' y2='17'></line><line x1='14' y1='11' x2='14' y2='17'></line></svg> Delete</a>";
        
        echo "</div></div></div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pro Cloud Manager</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        :root { --p: #6366f1; --bg: #f8fafc; --txt: #1e293b; --border: #e2e8f0; }
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { margin: 0; background: var(--bg); color: var(--txt); }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        
        /* Header */
        .nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;}
        .breadcrumb { font-size: 0.9rem; font-weight: 500; color: #64748b; }
        .breadcrumb a { color: var(--p); text-decoration: none; }

        /* Search Bar */
        .search-box { position: relative; width: 100%; max-width: 300px; }
        .search-box input { width: 100%; padding: 10px 15px 10px 35px; border-radius: 10px; border: 1px solid var(--border); outline: none; }
        .search-box i { position: absolute; left: 12px; top: 10px; opacity: 0.4; }

        /* File List */
        .file-card { position: relative; z-index: 1; background: #fff; padding: 12px 16px; border-radius: 12px; border: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; transition: 0.2s; }
        .file-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-color: var(--p); }
        .file-card.active-card { z-index: 99 !important; }

        .f-meta { display: flex; align-items: center; gap: 12px; overflow: hidden; white-space: nowrap; width: 85%; }
        .f-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: var(--p); }
        .folder { background: #fff7ed; color: #f59e0b; }
        .file { background: #eef2ff; color: var(--p); }
        .f-name { font-weight: 600; text-decoration: none; color: var(--txt); font-size: 14px; text-overflow: ellipsis; overflow: hidden; }
        .f-sub { font-size: 11px; color: #94a3b8; display: block; margin-top: 2px;}

        /* Dropdown 3-Dot */
        .dropdown { position: relative; display: inline-block; }
        .dropdown-btn { font-size: 20px; font-weight: bold; background: none; border: none; padding: 5px 10px; cursor: pointer; color: #94a3b8; border-radius: 6px; }
        .dropdown-btn:hover { background: #f1f5f9; color: var(--txt); }
        
        .dropdown-menu { display: none; position: absolute; right: 0; top: 35px; background: white; min-width: 180px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); border-radius: 10px; border: 1px solid var(--border); z-index: 100; overflow: hidden; }
        .dropdown-menu.show { display: block; animation: fadeIn 0.15s ease-in-out; }
        
        /* Dropdown Items & HR */
        .dropdown-menu hr { margin: 0; border: none; border-bottom: 1px solid var(--border); }
        .dropdown-item { color: var(--txt); padding: 12px 16px; text-decoration: none; display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 500; transition: 0.2s; background: white;}
        .dropdown-item:hover { background-color: #f8fafc; color: var(--p); }
        .dropdown-item svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; opacity: 0.7; }
        .dropdown-item.danger { color: #ef4444; }
        .dropdown-item.danger:hover { background-color: #fee2e2; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        /* Floating Button & Modal */
        .fab { position: fixed; bottom: 30px; right: 30px; width: 56px; height: 56px; background: var(--p); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px rgba(99,102,241,0.4); border: none; cursor: pointer; font-size: 24px; transition: 0.3s; z-index: 100; }
        .fab:hover { transform: rotate(90deg) scale(1.1); }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); z-index: 200; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 25px; border-radius: 20px; width: 90%; max-width: 400px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
        .modal h3 { margin: 0 0 15px 0; font-size: 18px; }
        .modal input, .modal select { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px; margin-bottom: 15px; outline: none; }
        .btn-primary { background: var(--p); color: white; border: none; padding: 12px; border-radius: 10px; width: 100%; font-weight: 600; cursor: pointer; }

        /* Info Grid */
        .info-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 10px; font-size: 14px; margin-bottom: 20px;}
        .info-grid strong { color: #64748b; font-weight: 500; }

        /* Editor & Viewer */
        .editor-container { background: white; border-radius: 16px; border: 1px solid var(--border); padding: 20px; }
        textarea { width: 100%; height: 65vh; border: 1px solid #eee; border-radius: 10px; padding: 15px; font-family: monospace; resize: none; outline: none; margin: 15px 0; }
        
        .empty-state { text-align: center; padding: 60px; color: #94a3b8; font-size: 14px; }
    </style>
</head>
<body>

<div class="container">
    <div class="nav">
        <div class="breadcrumb">
            <a href="?">Root</a> <?php if($workingPath) echo " / " . str_replace('/', ' / ', $workingPath); ?>
        </div>
        
        <?php if(!isset($_GET['edit']) && !isset($_GET['view'])): ?>
        <div class="search-box">
            <i>🔍</i>
            <input type="text" id="mainSearch" placeholder="Search files...">
        </div>
        <?php endif; ?>
    </div>

    <?php if(isset($_GET['view'])): ?>
        <div class="editor-container">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <h3 style="margin:0; font-size:16px; display:flex; align-items:center; gap:10px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                    Viewing: <?php echo basename($realPath); ?>
                </h3>
                <div style="display:flex; gap:10px;">
                    <button class="btn-primary" onclick="openRename('<?php echo basename($realPath); ?>')" style="padding: 8px 15px; font-size: 13px; margin:0; width:auto; display:flex; align-items:center;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> Rename
                    </button>
                    <a href="?path=<?php echo $workingPath; ?>" style="color:#ef4444; text-decoration:none; display:flex; align-items:center; font-size:13px; font-weight:600; padding:8px 15px; background:#fee2e2; border-radius:10px;">✕ Close</a>
                </div>
            </div>
            <div style="text-align:center; padding: 30px 10px; background:#f8fafc; border-radius:10px; margin-top:20px; border:1px dashed var(--border);">
                <?php 
                $mime = mime_content_type($realPath);
                $b64 = base64_encode(file_get_contents($realPath));
                echo "<img src='data:$mime;base64,$b64' style='max-width:100%; max-height:60vh; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1);'>";
                ?>
            </div>
        </div>

    <?php elseif(isset($_GET['edit'])): ?>
        <div class="editor-container">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:16px;">Editing: <?php echo basename($realPath); ?></h3>
                <a href="?path=<?php echo $workingPath; ?>" style="color:#64748b; font-size:13px; font-weight:600;">✕ Cancel</a>
            </div>
            <form method="post">
                <input type="hidden" name="file" value="<?php echo $realPath; ?>">
                <textarea name="content"><?php echo htmlspecialchars(file_get_contents($realPath)); ?></textarea>
                <button name="save" class="btn-primary">Save Changes</button>
            </form>
        </div>

    <?php else: ?>
        <div id="fileList">
            <?php listFiles($workingDir, $workingPath); ?>
        </div>

        <button class="fab" onclick="toggleModal('createModal', true)">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        </button>
    <?php endif; ?>
</div>

<div class="modal" id="createModal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between;">
            <h3>Create / Upload</h3>
            <span onclick="toggleModal('createModal', false)" style="cursor:pointer; opacity:0.5;">✕</span>
        </div>
        <form method="post">
            <input type="text" name="item_name" placeholder="Item Name (e.g. index.php)" required>
            <select name="item_type">
                <option value="file">File</option>
                <option value="folder">Folder</option>
            </select>
            <button name="create_item" class="btn-primary">Create</button>
        </form>
        <div style="margin: 15px 0; text-align: center; font-size: 12px; color: #ccc;">OR</div>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="upload_file" id="up" hidden onchange="this.form.submit()">
            <label for="up" class="btn-primary" style="background:#10b981; display:flex; align-items:center; justify-content:center; gap:8px; padding:12px; margin:0;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg> 
                Upload Image / File
            </label>
        </form>
    </div>
</div>

<div class="modal" id="renameModal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between;">
            <h3>Rename</h3>
            <span onclick="toggleModal('renameModal', false)" style="cursor:pointer; opacity:0.5;">✕</span>
        </div>
        <form method="post">
            <input type="hidden" name="old_name" id="rn_old">
            <input type="text" name="new_name" id="rn_new" required>
            <button name="rename_item" class="btn-primary">Rename</button>
        </form>
    </div>
</div>

<div class="modal" id="actionModal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between;">
            <h3 id="actionTitle">Move/Copy</h3>
            <span onclick="toggleModal('actionModal', false)" style="cursor:pointer; opacity:0.5;">✕</span>
        </div>
        <form method="post">
            <input type="hidden" name="source_name" id="ac_source">
            <input type="hidden" name="action_type" id="ac_type">
            <label style="font-size:12px; color:#64748b;">Destination Path (blank for root)</label>
            <input type="text" name="dest_path" id="ac_dest" placeholder="e.g. assets/images">
            <button name="action_item" class="btn-primary" id="ac_btn">Confirm</button>
        </form>
    </div>
</div>

<div class="modal" id="zipModal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between;">
            <h3>Compress Archive</h3>
            <span onclick="toggleModal('zipModal', false)" style="cursor:pointer; opacity:0.5;">✕</span>
        </div>
        <form method="post">
            <input type="hidden" name="source_name" id="zp_source">
            <label style="font-size:12px; color:#64748b;">Archive Name</label>
            <input type="text" name="zip_name" id="zp_name" required>
            <button name="zip_item" class="btn-primary">Compress</button>
        </form>
    </div>
</div>

<div class="modal" id="chmodModal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between;">
            <h3>Change Permissions</h3>
            <span onclick="toggleModal('chmodModal', false)" style="cursor:pointer; opacity:0.5;">✕</span>
        </div>
        <form method="post">
            <input type="hidden" name="chmod_name" id="ch_source">
            <label style="font-size:12px; color:#64748b;">Permissions (e.g. 0644, 0755)</label>
            <input type="text" name="chmod_val" id="ch_val" required>
            <button name="chmod_item" class="btn-primary">Update</button>
        </form>
    </div>
</div>

<div class="modal" id="infoModal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between;">
            <h3>File Information</h3>
            <span onclick="toggleModal('infoModal', false)" style="cursor:pointer; opacity:0.5;">✕</span>
        </div>
        <div class="info-grid">
            <strong>Name:</strong> <span id="in_name"></span>
            <strong>Type:</strong> <span id="in_type"></span>
            <strong>Size:</strong> <span id="in_size"></span>
            <strong>Modified:</strong> <span id="in_mod"></span>
            <strong>Perms:</strong> <span id="in_perms"></span>
        </div>
        <button class="btn-primary" onclick="toggleModal('infoModal', false)">Close</button>
    </div>
</div>

<script>
    function toggleDropdown(id, event) {
        event.stopPropagation();
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            if(menu.id !== id) menu.classList.remove('show');
        });
        document.querySelectorAll('.file-card').forEach(card => {
            card.classList.remove('active-card');
        });

        let currentMenu = document.getElementById(id);
        currentMenu.classList.toggle('show');
        
        if(currentMenu.classList.contains('show')) {
            event.target.closest('.file-card').classList.add('active-card');
        }
    }

    function toggleModal(id, show) {
        document.getElementById(id).style.display = show ? 'flex' : 'none';
        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
        document.querySelectorAll('.file-card').forEach(c => c.classList.remove('active-card'));
    }

    function openRename(filename) {
        document.getElementById('rn_old').value = filename;
        document.getElementById('rn_new').value = filename;
        toggleModal('renameModal', true);
    }

    function openAction(filename, type, currentPath) {
        document.getElementById('ac_source').value = filename;
        document.getElementById('ac_type').value = type;
        document.getElementById('ac_dest').value = currentPath;
        let title = type === 'move' ? 'Move' : 'Copy';
        document.getElementById('actionTitle').innerText = title + ' Item';
        document.getElementById('ac_btn').innerText = title + ' Now';
        toggleModal('actionModal', true);
    }

    function openZip(filename) {
        document.getElementById('zp_source').value = filename;
        document.getElementById('zp_name').value = filename + '.zip';
        toggleModal('zipModal', true);
    }

    function openChmod(filename, perms) {
        document.getElementById('ch_source').value = filename;
        document.getElementById('ch_val').value = perms;
        toggleModal('chmodModal', true);
    }

    function openInfo(name, type, size, mod, perms) {
        document.getElementById('in_name').innerText = name;
        document.getElementById('in_type').innerText = type;
        document.getElementById('in_size').innerText = size;
        document.getElementById('in_mod').innerText = mod;
        document.getElementById('in_perms').innerText = perms;
        toggleModal('infoModal', true);
    }

    // Live Search
    const search = document.getElementById('mainSearch');
    if(search) {
        search.addEventListener('input', function(e) {
            let q = e.target.value.toLowerCase();
            document.querySelectorAll('.file-card').forEach(card => {
                let name = card.getAttribute('data-name');
                card.style.display = name.includes(q) ? 'flex' : 'none';
            });
        });
    }

    // Click Outside
    window.onclick = function(event) {
        if (!event.target.matches('.dropdown-btn')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                if (menu.classList.contains('show')) {
                    menu.classList.remove('show');
                }
            });
            document.querySelectorAll('.file-card').forEach(c => c.classList.remove('active-card'));
        }
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }
</script>

</body>
</html>
