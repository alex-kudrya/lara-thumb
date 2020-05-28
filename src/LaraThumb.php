<?php


namespace Kudrya\LaraThumb;

use Illuminate\Http\UploadedFile;

/**
 * Class LaraThumb
 * @package Kudrya\LaraThumb;
 */
class LaraThumb
{
    const MODES = ['cover', 'contain'];

    /**
     * Changes the size of the image with a reference point
     * in the center of the image. If the proportions of original image are not
     * equal needle then the image is trimmed to the correct proportions
     *
     * @param UploadedFile $file
     * @param integer $width
     * @param integer $height
     * @param string [$mode] One of processing modes: "cover", "contain"
     * @author Alexander Kudrya <alexkudrya91@gmail.com>
     * @since 05.05.2018
     * @update 18.06.2018 Added contain mode
     * @update 03.03.2019 Fixed bug in cover mode
     *
     */
    public static function processing(UploadedFile $file, int $width, int $height, string $mode = 'cover')
    {
        $extension = strtolower($file->extension());
        $filename = $file->path();

        if (!in_array($mode, self::MODES)) {
            die ( "Invalid image processing mode." );
        }

        list ($src_width, $src_height) = self::getOriginalSizes($file);

        // Calculate new image sizes

        $dst_offset_X = 0;
        $dst_offset_Y = 0;
        $src_offset_X = 0;
        $src_offset_Y = 0;

        if ($mode === 'cover') { // Cover mode
            $proportions = $width / $height;

            if ($src_width < $width) {
                $dst_width = $src_width;
                $width = $dst_width;
                $dst_height = $width / $proportions;
                $height = $dst_height;
            }

            if ($src_height < $height) {
                $dst_height = $src_height;
                $height = $dst_height;
                $dst_width = $height * $proportions;
                $width = $dst_width;
            }

            if (($src_width / $width) > ($src_height / $height)) {
                $dst_width = $width;
                $dst_height = $height;
                $old_width_orig = $src_width;
                $src_width = $src_height * $proportions;
                $src_offset_X = ($old_width_orig - $src_width) / 2;
                $src_offset_Y = 0;
            } else {
                $dst_width = $width;
                $dst_height = $height;
                $old_height_orig = $src_height;
                $src_height = $src_width / $proportions;
                $src_offset_Y = ($old_height_orig - $src_height) / 2;
                $src_offset_X = 0;
            }

            $dst_offset_X = 0;
            $dst_offset_Y = 0;

        } elseif ($mode === 'contain') { // Сontain mode
            $srcProportions = $src_width / $src_height;
            $dst_width = $dst_height = 0;

            if ($src_width != $width) {
                $dst_width = $width;
                $dst_height = $width / $srcProportions;
                $dst_offset_Y = ($height - $dst_height) / 2;
                $dst_offset_X = 0;
            }

            if ($src_height != $height) {
                $dst_height = $height;
                $dst_width = $height * $srcProportions;
                $dst_offset_X = ($width - $dst_width) / 2;
                $dst_offset_Y = 0;
            }

            if ($dst_width > $width) {
                $dst_width = $width;
                $dst_height = $width / $srcProportions;
                $dst_offset_Y = ($height - $dst_height) / 2;
                $dst_offset_X = 0;
            }

            if ($dst_height > $height) {
                $dst_height = $height;
                $dst_width = $height * $srcProportions;
                $dst_offset_X = ($width - $dst_width) / 2;
                $dst_offset_Y = 0;
            }

            $src_offset_X = 0;
            $src_offset_Y = 0;
        }

        // Create new image
        $image_p = imagecreatetruecolor($width, $height);
        if ($mode === 'cover') {
            $black_p = imagecolorallocate($image_p, 0, 0, 0);
            imagecolortransparent($image_p, $black_p);
        } elseif ($mode === 'contain') {
            $white = imagecolorallocate($image_p, 255, 255, 255);
            imagefill($image_p, 0, 0, $white);
        }

        if ($extension == 'jpg' or $extension == 'jpeg') {
            $image = imagecreatefromjpeg($filename);
        } elseif ($extension == 'png') {
            $image = imagecreatefrompng($filename);
        } elseif ($extension == 'gif') {
            $image = imagecreatefromgif($filename);
        }

        if ($mode === 'cover') {
            $black = imagecolorallocate($image, 0, 0, 0);
            imagecolortransparent($image, $black);
        }

        imagecopyresampled(
            $image_p,
            $image,
            intval($dst_offset_X),
            intval($dst_offset_Y),
            intval($src_offset_X),
            intval($src_offset_Y),
            intval($dst_width),
            intval($dst_height),
            intval($src_width),
            intval($src_height)
        );

        // Replacing old image for new image
        if ($extension == 'jpg' or $extension == 'jpeg') {
            imagejpeg($image_p, $filename, 100);
        } elseif ($extension == 'png') {
            imagepng($image_p, $filename, 0);
        } elseif ($extension == 'gif') {
            imagegif($image_p, $filename);
        }
    }


    /**
     * @param UploadedFile $file
     * @return array
     */
    public static function getOriginalSizes(UploadedFile $file) :array
    {
        $extension = strtolower($file->extension());
        $filename = $file->path();

        $src_width = 0;
        $src_height = 0;

        if ($extension == 'png') { // Get image size of png
            $handle = fopen( $filename, "rb" ) or die ( "Invalid file stream." );

            if ( ! feof( $handle ) ) {
                $new_block = fread( $handle, 24 );
                if ( $new_block[0] == "\x89" &&
                    $new_block[1] == "\x50" &&
                    $new_block[2] == "\x4E" &&
                    $new_block[3] == "\x47" &&
                    $new_block[4] == "\x0D" &&
                    $new_block[5] == "\x0A" &&
                    $new_block[6] == "\x1A" &&
                    $new_block[7] == "\x0A" ) {
                    if ( $new_block[12] . $new_block[13] . $new_block[14] . $new_block[15] === "\x49\x48\x44\x52" ) {
                        $width  = unpack( 'H*', $new_block[16] . $new_block[17] . $new_block[18] . $new_block[19] );
                        $src_width = hexdec( $width[1] );
                        $height = unpack( 'H*', $new_block[20] . $new_block[21] . $new_block[22] . $new_block[23] );
                        $src_height = hexdec( $height[1] );
                    }
                }
            }
        } else { // Get image size of jpg or gif
            list ($src_width, $src_height) = getimagesize($filename);
        }

        return [$src_width, $src_height];
    }
}
