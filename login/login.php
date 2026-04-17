<?php
 if (isset($_POST['submit']) && isset($_POST['Nama']) && isset($_POST['Pass'])) {
       if ($_POST['Nama']=="Admin" && $_POST['Pass']=="Login"){
        header('Location: admin.php');
       }
       else {
        $error = "User dan Pass salah";
    } 
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <h1>Login Admin</h1> 
      <?php if(isset($error)):?>
            <p style="color:red;"><?=$error?></p>
            <?php endif;?>
    <form action="" method="POST">
        <ul>
            <li>
            <label for="Username">Username</label>
            <input type="text" name="Nama" id="Username" required placeholder="Masukkan Nama">
            </li>
            <li>
            <label for="Password">Password</label>
            <input type="text" name="Pass" id="Password" required placeholder="Masukkan Password">
            </li>
            <button type="submit" name="submit">Kirim</button>
        </ul>
    </form>
</body>
</html>