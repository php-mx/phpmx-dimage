<?php

namespace PhpMx;

use Error;
use Exception;
use GdImage;

/** Classe de manipulação de imagens com suporte a múltiplos formatos. */
class DImage
{
    protected ?GdImage $gd = null;
    protected array $color = ['255', '255', '255'];
    protected array $size = [1, 1];

    protected int $imageType = IMAGETYPE_JPEG;
    protected int $quality = 75;

    protected string $name;
    protected string $path = '.';

    function __destruct()
    {
        unset($this->gd);
    }

    function __toString()
    {
        return $this->getBin();
    }

    /** Cria um objeto Image monocromatica */
    static function _color(string|array $color = 'fff', int|array $size = 1): DImage
    {
        $object = new DImage;

        $object->size = self::normalizeSize($size);

        $object->color = self::normalizeColor($color);

        $object->name = implode('-', [
            'color',
            self::colorHex(implode(',', $object->color)),
            ...$object->size
        ]);

        $object->gd = imagecreatetruecolor(...$object->size);

        imagefill($object->gd, 0, 0, imagecolorallocate($object->gd, ...$object->color));

        return $object;
    }

    /** Cria um objeto Image com base em uma URL de arquivo */
    static function _url(string $url): DImage
    {
        $object = new DImage();

        $object->name = basename($url);

        $object->imageType = exif_imagetype($url);

        $object->gd = match ($object->imageType) {
            IMAGETYPE_BMP => imagecreatefrombmp($url),
            IMAGETYPE_JPEG => imagecreatefromjpeg($url),
            IMAGETYPE_GIF => imagecreatefromgif($url),
            IMAGETYPE_PNG => imagecreatefrompng($url),
            IMAGETYPE_WEBP => imagecreatefromwebp($url),
            default => throw new Exception('Image type not suported')
        };

        $object->size = [imagesx($object->gd), imagesy($object->gd)];

        return $object;
    }

    /** Cria um objeto Image com base em um arquivo */
    static function _file(string $path): DImage
    {
        if (!File::check($path))
            throw new Exception('File not found');

        $object = new DImage();

        $object->rename(File::getOnly($path));

        $object->imageType = exif_imagetype($path);

        $object->path = Dir::getOnly($path);

        $object->gd = match ($object->imageType) {
            IMAGETYPE_BMP => imagecreatefrombmp($path),
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_GIF => imagecreatefromgif($path),
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            default => throw new Exception('Image type not suported')
        };

        $object->size = [imagesx($object->gd), imagesy($object->gd)];

        if ($object->imageType == IMAGETYPE_JPEG) {
            match (exif_read_data($path)['Orientation'] ?? 1) {
                2 => $object->flipH(),
                3 => $object->rotate(180, true),
                4 => $object->rotate(180, true)->flipH(),
                5 => $object->rotate(-90, true)->flipH(),
                6 => $object->rotate(-90, true),
                7 => $object->rotate(90, true)->flipH(),
                8 => $object->rotate(90, true),
                default => null
            };
        }

        return $object;
    }

    /** Ajusta a imagem para um aspect-ratio */
    function ratio(?float $ratio = null, int $position = 0): static
    {
        if ($ratio) {
            list($original_width, $original_height) = $this->size;
            list($target_width, $target_height) = explode('.', "$ratio");

            $target_ratio = $target_width / $target_height;

            $original_ratio = $original_width / $original_height;

            if ($original_ratio > $target_ratio) {
                $new_height = $original_height;
                $new_width = intval($original_height * $target_ratio);
            } else {
                $new_width = $original_width;
                $new_height = intval($original_width / $target_ratio);
            }

            $this->crop([$new_width, $new_height], $position);
        }

        return $this;
    }

    /** Converte o arquivo de saida para outro formato */
    function convert(string $ex): static
    {
        $ex = strtolower($ex);
        $ex = trim($ex, ' .');
        $newImageType = match ($ex) {
            'bmp', => IMAGETYPE_BMP,
            'png', => IMAGETYPE_PNG,
            'gif', => IMAGETYPE_GIF,
            'webp', => IMAGETYPE_WEBP,
            'jpg', 'jpe', 'jpeg' => IMAGETYPE_JPEG,
            default => throw new Exception('Image type not suported')
        };

        if ($newImageType != $this->imageType) {
            switch ($newImageType) {
                case IMAGETYPE_PNG:
                case IMAGETYPE_GIF:
                case IMAGETYPE_WEBP:
                    $c = $this->color;
                    $tmp = imagecreatetruecolor(...$this->size);
                    imagefill($tmp, 0, 0, imagecolorallocatealpha($tmp, $c[0], $c[1], $c[2], 127));
                    imagecopyresampled($tmp, $this->gd, 0, 0, 0, 0, $this->size[0], $this->size[1], ...$this->size);
                    imagealphablending($tmp, true);
                    imagesavealpha($tmp, true);
                    $this->gd = $tmp;
                    break;
            }
            $this->imageType = $newImageType;
        }
        return $this;
    }

    /** Redimensiona a imagem respeitando a proporção */
    function resize(int|array $size): static
    {
        if (is_int($size) && $size < 0) {
            $size = $this->calcSizeMin($size * -1);
        } else {
            $size = $this->calcSizeMax($size);
        }
        $this->ensureResizeArray($size);
        $this->resizeFree($size);
        return $this;
    }

    /** Redimensiona a imagem não respeitando a proporção */
    function resizeFree(int|array $size): static
    {
        $this->ensureResizeArray($size);

        list($nw, $nh) = $size;
        list($w, $h) = $this->size;

        $nw = intval($nw ? $nw : $w);
        $nh = intval($nh ? $nh : $h);

        switch ($this->imageType) {
            case IMAGETYPE_JPEG:
            case IMAGETYPE_BMP:
                $tmp = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($tmp, $this->gd, 0, 0, 0, 0, $nw, $nh, $w, $h);
                break;
            case IMAGETYPE_GIF:
            case IMAGETYPE_PNG:
            case IMAGETYPE_WEBP:
                $tmp = imagecreatetruecolor($nw, $nh);
                imagealphablending($tmp, false);
                imagesavealpha($tmp, true);
                imagecopyresampled($tmp, $this->gd, 0, 0, 0, 0, $nw, $nh, $w, $h);
                break;
        }

        $this->gd = $tmp;
        $this->size = [$nw, $nh];

        return $this;
    }

    /** Rotaciona uma imagem. */
    function rotate(int $graus, bool $transparent = true): static
    {
        $graus = $graus < 0 ? 360 + $graus : $graus;

        $c = $this->color;

        if ($transparent)
            $this->convert('webp');

        switch ($this->imageType) {
            case IMAGETYPE_JPEG:
            case IMAGETYPE_BMP:
                $this->gd = imagerotate($this->gd, $graus, imagecolorallocate($this->gd, $c[0], $c[1], $c[2]));
                break;
            case IMAGETYPE_GIF:
            case IMAGETYPE_PNG:
            case IMAGETYPE_WEBP:
                $this->gd = imagerotate($this->gd, $graus, imagecolorallocatealpha($this->gd, $c[0], $c[1], $c[2], 127));
                imagealphablending($this->gd, true);
                imagesavealpha($this->gd, true);
                break;
        }

        $this->size = [imagesx($this->gd), imagesy($this->gd)];

        return $this;
    }

    /** Inverte a imagem na horizontal */
    function flipH(): static
    {
        imageflip($this->gd, IMG_FLIP_HORIZONTAL);
        return $this;
    }

    /** Inverte a imagem na vertical */
    function flipV(): static
    {
        imageflip($this->gd, IMG_FLIP_VERTICAL);
        return $this;
    }

    /** Adiciona uma imagem DImage em uma posição da imagem atual */
    function stamp(DImage $imgSpamt, int $position = 0): static
    {
        $imgSpamt->resize(min(...$this->size));
        $stamp = $imgSpamt->getGd();
        imageAlphaBlending($stamp, true);
        imageSaveAlpha($stamp, true);

        $position = $this->calcPosition($position, imagesx($stamp), imagesy($stamp));

        imagecopy($this->gd, $stamp, $position[0], $position[1], 0, 0, imagesx($stamp), imagesy($stamp));
        return $this;
    }

    /** Corta uma parte da imagem */
    function crop(int|array $size, int $position = 0): static
    {
        $size = is_array($size) ? $size : [$size];
        $size[] = $size[0];
        if ($size[1] == 0) {
            $size[1] = $size[0];
        }
        if ($size[0] == 0) {
            $size[0] = $size[1];
        }
        $size = [array_shift($size), array_shift($size)];
        if ($size == [0, 0]) {
            $size[0] = [1, 1];
        }

        list($width, $height) = $this->size;

        if ($size[0] > $width) {
            $size[1] = ($size[1] * $width) / $size[0];
            $size[0] = $width;
        }
        if ($size[1] > $height) {
            $size[0] = ($size[0] * $height) / $size[1];
            $size[1] = $height;
        }

        list($nw, $nh) = $size;
        $color = $this->color;

        $quadro = imagecreatetruecolor($nw, $nh);
        imagefill($quadro, 0, 0, imagecolorallocatealpha($this->gd, $color[0], $color[1], $color[2], 127));

        $w = $width;
        $h = $height;

        $this->size = [$nw, $nh];

        list($px, $py) = $this->calcPosition($position, $w, $h);

        $px = round($px, 0);
        $py = round($py, 0);

        imagecopyresampled($quadro, $this->gd, $px, $py, 0, 0, $w, $h, $w, $h);
        $this->gd = $quadro;
        imagealphablending($this->gd, true);
        imagesavealpha($this->gd, true);
        return $this;
    }

    /** Enquadra imagem */
    function framing(int|array $size): static
    {
        $this->resize(is_array($size) ? $size : $size);

        $size = is_array($size) ? $size : [$size];
        $size[] = $size[0];
        if ($size[1] == 0) {
            $size[1] = $size[0];
        }
        if ($size[0] == 0) {
            $size[0] = $size[1];
        }
        $size = [array_shift($size), array_shift($size)];
        if ($size == [0, 0]) {
            $size[0] = [1, 1];
        }

        list($width, $height) = $this->size;

        $quadro = imagecreatetruecolor($size[0], $size[1]);
        imagefill($quadro, 0, 0, imagecolorallocate($quadro, ...$this->color));
        $px = ($size[0] / 2) - ($width / 2);
        $py = ($size[1] / 2) - ($height / 2);
        imagecopyresampled($quadro, $this->gd, $px, $py, 0, 0, $width, $height, $width, $height);
        $this->gd = $quadro;
        return $this;
    }

    /** Aplica um filtro GD a imagem */
    function filter(int $filter): static
    {
        imagefilter($this->gd, ...func_get_args());
        return $this;
    }
    protected static function colorHex(string $color): string
    {
        if (strpos($color, ',') === false) {
            return str_replace('#', '', $color);
        }

        $color = explode(',', $color);
        $r = array_shift($color) ?? '225';
        $g = array_shift($color) ?? '225';
        $b = array_shift($color) ?? '225';

        return str_pad(dechex($r), 2, 0) . str_pad(dechex($g), 2, 0) . str_pad(dechex($b), 2, 0);
    }

    protected static function colorRGB(string $color): string
    {
        if (count(explode(',', $color)) == 3) {
            return $color;
        }

        $color = str_replace('#', '', $color);
        $c = ['R' => '', 'G' => '', 'B' => ''];
        if (strlen($color) == 6) {
            list($c['R'], $c['G'], $c['B']) = str_split($color, 2);
        } elseif (strlen($color) == 3) {
            list($c['R'], $c['G'], $c['B']) = str_split($color, 1);
            foreach ($c as $var => $value) {
                $c[$var] = str_repeat($value, 2);
            }
        } elseif (strlen($color) == 1) {
            foreach ($c as $var => $value) {
                $c[$var] = str_repeat($color, 2);
            }
        }
        foreach ($c as $var => $value) {
            $c[$var] = hexdec($value);
        }

        return implode(',', $c);
    }

    protected function ensureResizeArray(int|array &$size): void
    {
        list($width, $height) = $this->size;
        if (!is_array($size)) {
            switch (($width <=> $height) * -1) {
                case -1:
                    $size = [$size, 0];
                    break;
                case 0:
                    $size = [$size, $size];
                    break;
                case 1:
                    $size = [0, $size];
                    break;
            }
        }
    }

    protected function calcSizeMax(int|array $size): array
    {
        $this->ensureResizeArray($size);
        list($width, $height) = $this->size;
        list($modWidth, $modHeight) = $size;

        if ($modWidth && $width > $modWidth) {
            $height = $height / ($width / $modWidth);
            $width = $modWidth;
        }

        if ($modHeight && $height > $modHeight) {
            $width = $width / ($height / $modHeight);
            $height = $modHeight;
        }

        return [$width, $height];
    }

    protected function calcSizeMin(int|array $size): array
    {
        list($width, $height) = $this->size;
        if ($width <= $height) {
            if ($width > $size) {
                $height = $height / ($width / $size);
                $width = $size;
            }
        } else {
            if ($height > $size) {
                $width = $width / ($height / $size);
                $height = $size;
            }
        }
        return [$width, $height];
    }

    protected function calcPosition(int|array $position, int $dx = 0, int $dy = 0): array
    {
        if (is_array($position)) {
            $x = intval(array_shift($position) ?? 0);
            $y = intval(array_shift($position) ?? 0);
        } else {
            list($width, $height) = $this->size;

            $position = num_interval($position, 0, 8);

            $x =  match ($position) {
                0, 3, 7 => ($width / 2) - ($dx / 2),
                1, 2, 8 => 0,
                4, 5, 6 => $width - $dx,
            };

            $y =  match ($position) {
                0, 1, 5 => ($height / 2) - ($dy / 2),
                2, 3, 4 => 0,
                6, 7, 8 => $height - $dy,
            };
        }
        return [$x, $y];
    }

    protected static function normalizeColor(string|array $color): array
    {
        if (!is_array($color)) {
            $color = self::colorRGB($color);
            $color = explode(',', $color);
            $color = [
                $color[0],
                $color[1],
                $color[2],
            ];
        }
        return $color;
    }

    protected static function normalizeSize(int|array $size): array
    {
        $size = is_array($size) ? $size : [$size];

        $size[] = $size[0];

        if ($size[1] == 0)
            $size[1] = $size[0];

        if ($size[0] == 0)
            $size[0] = $size[1];

        $size = [array_shift($size), array_shift($size)];

        $size = array_map(fn($v) => max($v, 0), $size);

        return $size;
    }
    /** Retorna o nome da imagem */
    function getName(bool $ex = false): string
    {
        $name = $this->name;

        if ($ex)
            $name .= '.' . $this->getExtension();

        return $name;
    }

    /** Retorna o caminho da imagem no disco */
    function getPath(): ?string
    {
        return $this->path;
    }

    /** Retorna a imagem GD gerada pela classe */
    function getGd(): GdImage
    {
        return $this->gd;
    }

    /** Retorna o array de dimensão da imagem */
    function getSize(): array
    {
        return $this->size;
    }

    /** Retorna a largura da imagem */
    function getWidth(): int
    {
        return $this->size[0];
    }

    /** Retorna a altura da imagem */
    function getHeight(): int
    {
        return $this->size[1];
    }

    /** Retorna a extensao da imagem */
    function getExtension(): string
    {
        return match ($this->imageType) {
            IMAGETYPE_BMP => 'bmp',
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
        };
    }

    /** Retorna o tamanho do arquivo da imagem */
    function getFileSize(): float
    {
        return num_format(strlen($this->getBin()) / (1024 * 1024), 3, 1);
    }

    /** Captura o Hash Md5 gerado pelo binario da imagem */
    function getHash(): string
    {
        return md5($this->getBin());
    }

    /** Retorna o binario da imagem */
    function getBin(): string
    {
        ob_start();

        match ($this->imageType) {
            IMAGETYPE_BMP => imagebmp($this->gd, null, true),
            IMAGETYPE_JPEG => imagejpeg($this->gd, null, $this->quality),
            IMAGETYPE_GIF => imagegif($this->gd, null),
            IMAGETYPE_PNG => imagepng($this->gd, null),
            IMAGETYPE_WEBP => imagewebp($this->gd, null, $this->quality),
        };

        $bin = ob_get_contents();

        ob_end_clean();

        return $bin;
    }

    /** Retorna a imagem codificada em base64 */
    function getB64(): string
    {
        $type = Mime::getMimeEx($this->getExtension());
        $b64 = base64_encode($this->getBin());
        return "data:$type;base64,$b64";
    }
    /** Define a qualidade da imagem para arquivos exportados */
    function quality(int $quality): static
    {
        $this->quality = num_interval($quality, 0, 100);
        return $this;
    }

    /** Defeine um nome para o arquivo */
    function rename(string $name): static
    {
        if (!str_starts_with($name, '.') && str_contains($name, '.')) {
            $name = explode('.', $name);
            array_pop($name);
            $name = implode('.', $name);
        }

        $this->name = $name;

        return $this;
    }

    /** Define um caminho onde o arquivo será armazenado */
    function path(): static
    {
        $this->path = path(...func_get_args());
        return $this;
    }

    /** Define a cor base da imagem */
    function color(string|array $color): static
    {
        $this->color = $this->normalizeColor($color);
        return $this;
    }

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
        return clone($this);
    }
}
