<?php

/**
 * ZipCompressionMethodTest.php
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

namespace CoffeePhp\Zip\Test\Unit;

use CoffeePhp\FileSystem\Contract\Data\Path\DirectoryInterface;
use CoffeePhp\FileSystem\Contract\Data\Path\FileInterface;
use CoffeePhp\FileSystem\Data\Path\PathNavigator;
use CoffeePhp\FileSystem\FileManager;
use CoffeePhp\Zip\ZipCompressionMethod;
use Faker\Factory;
use Faker\Generator;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

/**
 * Class ZipCompressionMethodTest
 * @package coffeephp\zip
 * @author Danny Damsky <dannydamsky99@gmail.com>
 * @since 2020-09-23
 * @see ZipCompressionMethod
 */
final class ZipCompressionMethodTest extends TestCase
{
    private DirectoryInterface $testDirectory;
    private FileInterface $testFile;
    private FileManager $fileManager;
    private Generator $faker;
    private ZipCompressionMethod $zip;
    private string $uniqueString;

    /**
     * ZipCompressionMethodTest constructor.
     * @param string|null $name
     * @param array $data
     * @param string $dataName
     */
    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->faker = Factory::create();
        $this->fileManager = new FileManager();
        $this->zip = new ZipCompressionMethod($this->fileManager);
    }

    /**
     * @inheritDoc
     * @noinspection PhpUndefinedMethodInspection
     */
    protected function setUp(): void
    {
        parent::setUp();
        $testDirectoryPath = (new PathNavigator(__DIR__))->abc();
        $testFilePath = (clone $testDirectoryPath)
            ->def()->ghi()->jkl()->mno()->pqr()->stu()->vwx()->yz()->down('file.txt');
        $this->testDirectory = $this->fileManager->createDirectory($testDirectoryPath);
        $this->testFile = $this->fileManager->createFile($testFilePath);

        // Generate unique string.
        $uniqueString = '';
        for ($i = 0; $i < $this->faker->numberBetween(50, 9000); ++$i) {
            $uniqueString .= $this->faker->realText();
            $uniqueString .= $this->faker->md5;
            $uniqueString .= $this->faker->regexify('[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}');
        }
        $this->uniqueString = $uniqueString;

        $this->testFile->write($this->uniqueString);
    }

    /**
     * @see ZipCompressionMethod::compressDirectory()
     * @see ZipCompressionMethod::uncompressDirectory()
     */
    public function testPathCompressionMethod(): void
    {
        $zipFile = $this->zip->compressDirectory($this->testDirectory);

        assertSame(
            "{$this->testDirectory}.zip",
            (string)$zipFile
        );

        $this->testDirectory->delete();

        assertFalse($this->testDirectory->exists() || $this->testFile->exists());

        $this->zip->uncompressDirectory($zipFile);

        assertTrue($this->testDirectory->exists() && $this->testFile->read() === $this->uniqueString);

        $zipFile->delete();
        $this->testDirectory->delete();
    }
}
