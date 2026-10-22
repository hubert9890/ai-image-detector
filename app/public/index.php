<?php

declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AI Image Detector</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            height: 100vh;             
            display: flex;             
            justify-content: center;   
            align-items: center;       
            background-color: ##FFFDD0;
        }
        h1{
            text-align: center;
        }
        .container{
            display: flex;
            justify-content: center; 
            align-items: center;    
        }
    </style>
</head>
<body>
    <div className="container">
        <h1>AI Image Detector</h1>

        <form action="result.php" method="post" enctype="multipart/form-data">
            <label for="image">Select an image:</label>
            <input
                id="image"
                type="file"
                name="image"
                accept="image/jpeg,image/png,image/webp"
                required
            >

            <button type="submit">Analyze Image</button>
        </form>

        <p>Maximum file size: 10 MB.</p>
    </div>
</body>
</html>

