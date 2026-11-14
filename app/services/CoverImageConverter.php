<?php

final class CoverImageConverter
{
    public const MAX_SOURCE_SIZE = 25 * 1024 * 1024;

    private const MAX_EDGE = 1800;
    private const MAX_PIXELS = 80_000_000;
    private const MAX_OUTPUT_SIZE = 5 * 1024 * 1024;

    public static function convertToWebp(string $sourcePath, string $destinationPath): void
    {
        if (!class_exists('Imagick')) {
            throw new ApiException('Конвертація зображень тимчасово недоступна на сервері', 503);
        }

        $sourceFormat = self::detectSupportedFormat($sourcePath);
        if (Imagick::queryFormats('WEBP') === [] || Imagick::queryFormats($sourceFormat) === []) {
            throw new ApiException('Сервер не має кодека для цього формату зображення', 503);
        }

        $probe = new Imagick();
        $image = null;
        try {
            self::setResourceLimits($probe);
            $probe->pingImage($sourcePath);
            $probe->setIteratorIndex(0);

            $width = $probe->getImageWidth();
            $height = $probe->getImageHeight();
            if ($width < 1 || $height < 1 || $width > 30_000 || $height > 30_000 || $width * $height > self::MAX_PIXELS) {
                throw new ApiException('Зображення має неприпустимі розміри', 400);
            }
            $probe->clear();

            $source = new Imagick();
            self::setResourceLimits($source);
            $source->readImage($sourcePath);
            $source->setIteratorIndex(0);
            $image = $source->getImage();
            $source->clear();

            if (method_exists($image, 'autoOrient')) {
                $image->autoOrient();
            } elseif (method_exists($image, 'autoOrientImage')) {
                $image->autoOrientImage();
            } elseif (method_exists($image, 'autoOrientate')) {
                $image->autoOrientate();
            } else {
                throw new RuntimeException('Installed Imagick version cannot apply image orientation');
            }
            $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
            if ($image->getImageColorspace() !== Imagick::COLORSPACE_SRGB) {
                $image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
            }

            $width = $image->getImageWidth();
            $height = $image->getImageHeight();
            $scale = min(1, self::MAX_EDGE / max($width, $height));
            if ($scale < 1) {
                $image->resizeImage(
                    max(1, (int) round($width * $scale)),
                    max(1, (int) round($height * $scale)),
                    Imagick::FILTER_LANCZOS,
                    1
                );
            }

            $image->setImagePage(0, 0, 0, 0);
            $image->stripImage();
            $image->setImageFormat('webp');
            $image->setOption('webp:method', '6');
            $image->setOption('webp:lossless', 'false');

            $webp = '';
            foreach ([86, 78, 70] as $quality) {
                $image->setImageCompressionQuality($quality);
                $webp = $image->getImageBlob();
                if (strlen($webp) <= self::MAX_OUTPUT_SIZE) {
                    break;
                }
            }
            if ($webp === '' || strlen($webp) > self::MAX_OUTPUT_SIZE) {
                throw new ApiException('Не вдалося стиснути обкладинку до 5 МБ', 422);
            }

            if (file_put_contents($destinationPath, $webp, LOCK_EX) !== strlen($webp)) {
                throw new RuntimeException('Could not write converted cover');
            }
        } catch (ApiException $error) {
            throw $error;
        } catch (ImagickException $error) {
            error_log('Cover conversion failed: ' . $error->getMessage());
            throw new ApiException('Не вдалося прочитати або конвертувати це зображення', 415);
        } finally {
            $probe->clear();
            if ($image instanceof Imagick) {
                $image->clear();
            }
        }
    }

    private static function detectSupportedFormat(string $path): string
    {
        $header = file_get_contents($path, false, null, 0, 64);
        if (!is_string($header)) {
            throw new ApiException('Не вдалося прочитати завантажене зображення', 400);
        }

        if (str_starts_with($header, "\xFF\xD8\xFF")) {
            return 'JPEG';
        }
        if (str_starts_with($header, "\x89PNG\r\n\x1A\n")) {
            return 'PNG';
        }
        if (strlen($header) >= 12 && substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WEBP') {
            return 'WEBP';
        }
        if (strlen($header) >= 12 && substr($header, 4, 4) === 'ftyp') {
            $brands = substr($header, 8);
            if (preg_match('/(?:avif|avis)/', $brands) === 1) {
                return 'AVIF';
            }
            if (preg_match('/(?:heic|heix|hevc|hevx|mif1|msf1)/', $brands) === 1) {
                return 'HEIC';
            }
        }

        throw new ApiException('Підтримуються лише JPEG, PNG, WebP, AVIF, HEIC та HEIF', 415);
    }

    private static function setResourceLimits(Imagick $image): void
    {
        $image->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 128 * 1024 * 1024);
        $image->setResourceLimit(Imagick::RESOURCETYPE_MAP, 256 * 1024 * 1024);
        $image->setResourceLimit(Imagick::RESOURCETYPE_DISK, 512 * 1024 * 1024);
    }
}
