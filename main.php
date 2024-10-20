<?php

define('THRESHOLD', 180);
define('KERNEL_SIZE', 3);

$requestUri = $_SERVER['REQUEST_URI'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (preg_match('/\/upload$/', $requestUri) && isset($_FILES['image'])) {
        handleUpload();
    } elseif (preg_match('/\/uploadnoise$/', $requestUri) && isset($_FILES['image'])) {
        handleUploadNoise();
    } else {
        echo json_encode(['error' => 'Invalid request']);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}

function handleUpload() {
    $inputFilePath = $_FILES['image']['tmp_name'];
    $outputFilePath = 'output/output_bg.png';

    $inputImage = loadImage($inputFilePath);
    if ($inputImage === false) {
        echo json_encode(['error' => 'Error loading image']);
        return;
    }

    $outputImage = removeBackground($inputImage);
    header('Content-Type: image/png');
    imagepng($outputImage);
    
    
    $result = saveImage($outputImage, $outputFilePath);
    if ($result === false) {
        echo json_encode(['error' => 'Error saving image']);
        return;
    }

    echo json_encode(['message' => 'Background removal completed', 'output' => $outputFilePath]);
    
    // exit;
    
}

function handleUploadNoise() {
    $inputFilePath = $_FILES['image']['tmp_name'];
    $outputFilePath = 'output/output_noise.jpg';

    $inputImage = loadImage($inputFilePath);
    if ($inputImage === false) {
        echo json_encode(['error' => 'Error loading image']);
        return;
    }

    $outputImage = removeNoise($inputImage);
    header('Content-Type: image/png');
    imagepng($outputImage);

    $result = saveImage($outputImage, $outputFilePath);
    if ($result === false) {
        echo json_encode(['error' => 'Error saving image']);
        return;
    }

    echo json_encode(['message' => 'Noise removal completed', 'output' => $outputFilePath]);
}

function loadImage($filePath) {
    $imageInfo = getimagesize($filePath);
    if ($imageInfo === false) {
        return false;
    }

    $mime = $imageInfo['mime'];

    switch ($mime) {
        case 'image/jpeg':
            return imagecreatefromjpeg($filePath);
        case 'image/png':
            return imagecreatefrompng($filePath);
        default:
            return false;
    }
}

function saveImage($image, $filePath) {
    $ext = getExtension($filePath);

    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            return imagejpeg($image, $filePath);
        case 'png':
            return imagepng($image, $filePath);
        default:
            return false;
    }
}

function getExtension($filePath) {
    $parts = explode('.', $filePath);
    if (count($parts) > 1) {
        return strtolower(end($parts));
    }
    return '';
}

function removeBackground($img) {
    $width = imagesx($img);
    $height = imagesy($img);

    $newImg = imagecreatetruecolor($width, $height);
    imagesavealpha($newImg, true);
    $transparency = imagecolorallocatealpha($newImg, 0, 0, 0, 127);
    imagefill($newImg, 0, 0, $transparency);

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgba = imagecolorat($img, $x, $y);
            $colors = imagecolorsforindex($img, $rgba);

            if ($colors['red'] > THRESHOLD && $colors['green'] > THRESHOLD && $colors['blue'] > THRESHOLD && $colors['alpha'] == 0) {
                imagesetpixel($newImg, $x, $y, $transparency);
            } else {
                $color = imagecolorallocatealpha($newImg, $colors['red'], $colors['green'], $colors['blue'], $colors['alpha']);
                imagesetpixel($newImg, $x, $y, $color);
            }
        }
    }

    return $newImg;
}

function removeNoise($inputImage) {
    $bounds = imagecreatetruecolor(imagesx($inputImage), imagesy($inputImage));
    $width = imagesx($inputImage);
    $height = imagesy($inputImage);

    $kernelSize = KERNEL_SIZE;
    $halfKernelSize = $kernelSize / 2;

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $totalR = $totalG = $totalB = 0;

            for ($ky = -$halfKernelSize; $ky <= $halfKernelSize; $ky++) {
                for ($kx = -$halfKernelSize; $kx <= $halfKernelSize; $kx++) {
                    $nx = $x + $kx;
                    $ny = $y + $ky;

                    if ($nx >= 0 && $nx < $width && $ny >= 0 && $ny < $height) {
                        $rgb = imagecolorat($inputImage, $nx, $ny);
                        $colors = imagecolorsforindex($inputImage, $rgb);

                        $totalR += $colors['red'];
                        $totalG += $colors['green'];
                        $totalB += $colors['blue'];
                    }
                }
            }

            $avgR = $totalR / ($kernelSize * $kernelSize);
            $avgG = $totalG / ($kernelSize * $kernelSize);
            $avgB = $totalB / ($kernelSize * $kernelSize);

            // Clamp the values to ensure they are within the 0-255 range
            $avgR = max(0, min(255, $avgR));
            $avgG = max(0, min(255, $avgG));
            $avgB = max(0, min(255, $avgB));

            $color = imagecolorallocate($bounds, $avgR, $avgG, $avgB);
            imagesetpixel($bounds, $x, $y, $color);
        }
    }

    return $bounds;
}
 
?>