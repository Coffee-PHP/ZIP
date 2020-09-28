<?php

/**
 * ZipCompressionMethod.php
 *
 * Copyright 2020 Danny Damsky
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @package coffeephp\zip
 * @author Danny Damsky <dannydamsky99@gmail.com>
 * @since 2020-09-23
 */

declare(strict_types=1);

namespace CoffeePhp\Zip;

use CoffeePhp\CompressionMethod\AbstractCompressionMethod;
use CoffeePhp\FileSystem\Contract\Data\Path\DirectoryInterface;
use CoffeePhp\FileSystem\Contract\Data\Path\FileInterface;
use CoffeePhp\FileSystem\Contract\Data\Path\PathNavigatorInterface;
use CoffeePhp\FileSystem\Data\Path\PathNavigator;
use CoffeePhp\Zip\Contract\ZipCompressionMethodInterface;
use CoffeePhp\Zip\Exception\ZipCompressException;
use CoffeePhp\Zip\Exception\ZipUncompressException;
use Throwable;
use ZipArchive;

use function array_shift;
use function strlen;

/**
 * Class ZipCompressionMethod
 * @package coffeephp\zip
 * @author Danny Damsky <dannydamsky99@gmail.com>
 * @since 2020-09-23
 */
final class ZipCompressionMethod extends AbstractCompressionMethod implements ZipCompressionMethodInterface
{
    /**
     * @inheritDoc
     */
    public function compressDirectory(DirectoryInterface $uncompressedDirectory): FileInterface
    {
        try {
            $absolutePath = (string)$uncompressedDirectory;
            if (!$uncompressedDirectory->exists()) {
                throw new ZipCompressException("The given directory does not exist: {$absolutePath}");
            }
            $fullPath = $this->getFullPath($absolutePath, self::EXTENSION);
            $pathNavigator = $this->getAvailablePath($fullPath);
            return $this->handleLowLevelCompression($uncompressedDirectory, $pathNavigator);
        } catch (ZipCompressException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ZipCompressException(
                "Unexpected Compression Exception: {$e->getMessage()}",
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * @param DirectoryInterface $directory
     * @param PathNavigatorInterface $destination
     * @return FileInterface
     * @throws ZipCompressException
     * @throws Throwable
     * @psalm-suppress MixedAssignment
     * @psalm-suppress PossiblyUndefinedMethod
     * @psalm-suppress MixedMethodCall
     * @psalm-suppress MixedArgument
     * @psalm-suppress UndefinedVariable
     */
    private function handleLowLevelCompression(
        DirectoryInterface $directory,
        PathNavigatorInterface $destination
    ): FileInterface {
        try {
            $zip = new ZipArchive();
            $result = $zip->open((string)$destination, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($result !== true && $result !== ZipArchive::ER_OK) {
                throw new ZipCompressException("Failed to create a ZIP archive. ZIP error: {$result}");
            }
            $relativePathOffset = strlen((string)$directory) + 1;
            $pendingDirectories = [$directory];
            while (!empty($pendingDirectories)) {
                $pendingDirectory = array_shift($pendingDirectories);
                $directoryPath = substr((string)$pendingDirectory, $relativePathOffset);
                if ($directoryPath !== false) {
                    $zip->addEmptyDir($directoryPath);
                }
                foreach ($pendingDirectory->readDirectory() as $path) {
                    if ($path->isDirectory()) {
                        $pendingDirectories[] = $path;
                        continue;
                    }
                    $relativeFilePath = (new PathNavigator($directoryPath))->down($path->getBaseName());
                    $zip->addFile((string)$path, (string)$relativeFilePath);
                }
            }
            $zip->close();
            unset($zip);
            return $this->fileManager->getFile($destination);
        } finally {
            if (isset($zip)) {
                $zip->close();
                unset($zip);
                if ($destination->exists()) {
                    $this->fileManager->getPath($destination)->delete();
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function uncompressDirectory(FileInterface $compressedDirectory): DirectoryInterface
    {
        try {
            $absolutePath = (string)$compressedDirectory;
            if (!$compressedDirectory->exists()) {
                throw new ZipUncompressException("The given archive does not exist: {$absolutePath}");
            }
            $extension = self::EXTENSION;
            if (!$this->isFullPath($absolutePath, $extension)) {
                throw new ZipUncompressException(
                    "Directory archive '{$absolutePath}' does not have the extension: {$extension}"
                );
            }
            $originalPath = $this->getOriginalPath($absolutePath, $extension);
            $pathNavigator = $this->getAvailablePath($originalPath);
            return $this->handleLowLevelUncompression($compressedDirectory, $pathNavigator);
        } catch (ZipUncompressException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ZipUncompressException(
                "Unexpected Uncompression Exception: {$e->getMessage()}",
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * @param FileInterface $file
     * @param PathNavigatorInterface $destination
     * @return DirectoryInterface
     * @throws ZipUncompressException
     * @throws Throwable
     * @psalm-suppress UndefinedVariable
     */
    private function handleLowLevelUncompression(
        FileInterface $file,
        PathNavigatorInterface $destination
    ): DirectoryInterface {
        try {
            $zip = new ZipArchive();
            $result = $zip->open((string)$file);
            if ($result !== true && $result !== ZipArchive::ER_OK) {
                throw new ZipUncompressException("Failed to open ZIP archive. Path: {$file} ; ZIP error: {$result}");
            }
            if (!$zip->extractTo((string)$destination)) {
                throw new ZipUncompressException(
                    "Failed to extract ZIP archive into folder. Archive: {$file} ; Destination: {$destination}"
                );
            }
            $zip->close();
            unset($zip);
            return $this->fileManager->getDirectory($destination);
        } finally {
            if (isset($zip) && $destination->exists()) {
                $this->fileManager->getPath($destination)->delete();
            }
        }
    }
}
