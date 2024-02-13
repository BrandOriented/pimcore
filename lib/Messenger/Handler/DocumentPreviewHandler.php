<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Messenger\Handler;

use Gotenberg\Exceptions\GotenbergApiErroed;
use Gotenberg\Gotenberg as GotenbergAPI;
use Gotenberg\Stream;
use Pimcore\Config;
use Pimcore\Logger;
use Pimcore\Messenger\AssetPreviewImageMessage;
use Pimcore\Messenger\DocumentPreviewMessage;
use Pimcore\Model\Asset;
use Pimcore\Tool\Storage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;

/**
 * @internal
 */
class DocumentPreviewHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    public function __construct(protected LoggerInterface $logger)
    {
    }

    public function __invoke(DocumentPreviewMessage $message, Acknowledger $ack = null): mixed
    {
        return $this->handle($message, $ack);
    }

    // @phpstan-ignore-next-line
    private function process(array $jobs): void
    {
        foreach ($jobs as [$message, $ack]) {
            try {
                $asset = Asset::getById($message->getId());
                $storage = Storage::get('asset_cache');
                $storagePath = sprintf(
                    '%s/%s/pdf-thumb__%s__libreoffice-document.png',
                    rtrim($asset->getRealPath(), '/'),
                    $asset->getId(),
                    $asset->getId(),
                );
                if (!$storage->fileExists($storagePath)) {
                    $localAssetTmpPath = $asset->getLocalFile();

                    try {
                        $request = GotenbergAPI::libreOffice(Config::getSystemConfiguration('gotenberg')['base_url'])
                            ->convert(
                                Stream::path($localAssetTmpPath)
                            );

                        $response = GotenbergAPI::send($request);
                        $fileContent = $response->getBody()->getContents();
                        $storage->write($storagePath, $fileContent);

                        $stream = fopen('php://memory', 'r+');
                        fwrite($stream, $fileContent);
                        rewind($stream);
                    } catch (GotenbergApiErroed $e) {
                        $message = "Couldn't convert document to PDF: " . $asset->getRealFullPath() . ' with Gotenberg: ';
                        Logger::error($message. $e->getMessage());

                        throw $e;
                    }
                }

                $ack->ack($message);
            } catch (\Throwable $e) {
                $ack->nack($e);
            }
        }
    }

    // @phpstan-ignore-next-line
    private function shouldFlush(): bool
    {
        return 5 <= \count($this->jobs);
    }
}
