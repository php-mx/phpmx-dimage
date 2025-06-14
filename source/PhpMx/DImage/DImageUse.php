<?php

namespace PhpMx\Dimage;

use Error;
use PhpMx\DImage;
use PhpMx\Dir;
use PhpMx\File;

trait DImageUse
{
    /** Salva a imagem em um arquivo */
    function save(?string $path = null): static
    {
        $path = $path ?? $this->path;

        if (is_null($path))
            throw new Error('Set a path to save the file');

        $this->path = $path;

        Dir::create($path);

        $file = path($path, $this->getName());

        $file = File::setEx($file, $this->getExtension());

        match ($this->imageType) {
            IMAGETYPE_BMP => imagebmp($this->gd, $file, true),
            IMAGETYPE_JPEG => imagejpeg($this->gd, $file, $this->quality),
            IMAGETYPE_GIF => imagegif($this->gd, $file),
            IMAGETYPE_PNG => imagepng($this->gd, $file),
            IMAGETYPE_WEBP => imagewebp($this->gd, $file, $this->quality),
        };

        return $this;
    }

    /** Retorna uma copia do objeto de imagem atual*/
    function copy(): DImage
    {
        return clone ($this);
    }
}
