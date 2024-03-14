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

use Pimcore\Controller\Traits\JsonHelperTrait;
use Pimcore\Messenger\AssetPreviewImageMessage;
use Pimcore\Messenger\AssetThumbnailMessage;
use Pimcore\Model\Asset;
use Pimcore\Tool\Storage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;

/**
 * @internal
 */
class AssetThumbnailHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait, JsonHelperTrait;

    public function __construct(protected LoggerInterface $logger)
    {
    }

    public function __invoke(AssetPreviewImageMessage $message, Acknowledger $ack = null): mixed
    {
        return $this->handle($message, $ack);
    }

    // @phpstan-ignore-next-line
    private function process(array $jobs): void
    {
        /**
         * @var AssetThumbnailMessage $message
         * @var  $ack
         */
        foreach ($jobs as [$message, $ack]) {
            try {
                $asset = Asset::getById($message->getId());
                $request = $message->getRequest();

                if ($asset instanceof Asset\Image) {
                    if ($request->get('thumbnail')) {
                        $thumbnailConfig = $asset->getThumbnail($request->get('thumbnail'))->getConfig();
                    }
                    if (!$thumbnailConfig) {
                        if ($request->get('config')) {
                            $thumbnailConfig = $asset->getThumbnail($this->decodeJson($request->get('config')))->getConfig();
                        } else {
                            $thumbnailConfig = $asset->getThumbnail(array_merge($request->request->all(), $request->query->all()))->getConfig();
                        }
                    } else {
                        // no high-res images in admin mode (editmode)
                        // this is mostly because of the document's image editable, which doesn't know anything about the thumbnail
                        // configuration, so the dimensions would be incorrect (double the size)
                        $thumbnailConfig->setHighResolution(1);
                    }
                    $format = strtolower($thumbnailConfig->getFormat());
                    if ($format == 'source' || $format == 'print') {
                        $thumbnailConfig->setFormat('PNG');
                        $thumbnailConfig->setRasterizeSVG(true);
                    }

                    $cropPercent = $request->get('cropPercent');
                    if ($cropPercent && filter_var($cropPercent, FILTER_VALIDATE_BOOLEAN)) {
                        $thumbnailConfig->addItemAt(0, 'cropPercent', [
                            'width' => $request->get('cropWidth'),
                            'height' => $request->get('cropHeight'),
                            'y' => $request->get('cropTop'),
                            'x' => $request->get('cropLeft'),
                        ]);

                        $thumbnailConfig->generateAutoName();
                    }

                    $thumbnail = $asset->getThumbnail($thumbnailConfig)->generate();
                } elseif ($asset instanceof Asset\Document) {
                    $thumbnail = Asset\Image\Thumbnail\Config::getByAutoDetect(array_merge($request->request->all(), $request->query->all()));

                    $format = strtolower($thumbnail->getFormat());
                    if ($format == 'source') {
                        $thumbnail->setFormat('jpeg'); // default format for documents is JPEG not PNG (=too big)
                    }

                    if ($request->get('treepreview')) {
                        $thumbnail = Asset\Image\Thumbnail\Config::getPreviewConfig();
                    }

                    $page = 1;
                    if (is_numeric($request->get('page'))) {
                        $page = (int)$request->get('page');
                    }

                    $asset->getImageThumbnail($thumbnail, $page)->generate();
                } elseif ($asset instanceof Asset\Video) {
                    $thumbnail = array_merge($request->request->all(), $request->query->all());

                    if ($request->get('treepreview')) {
                        $thumbnail = Asset\Image\Thumbnail\Config::getPreviewConfig();
                    }

                    $time = null;
                    if (is_numeric($request->get('time'))) {
                        $time = (int)$request->get('time');
                    }

                    if ($request->get('settime')) {
                        $asset->removeCustomSetting('image_thumbnail_asset');
                        $asset->setCustomSetting('image_thumbnail_time', $time);
                        $asset->save();
                    }

                    $image = null;
                    if ($request->get('image')) {
                        $image = Asset\Image::getById((int)$request->get('image'));
                    }

                    if ($request->get('setimage') && $image) {
                        $asset->removeCustomSetting('image_thumbnail_time');
                        $asset->setCustomSetting('image_thumbnail_asset', $image->getId());
                        $asset->save();
                    }

                    $asset->getImageThumbnail($thumbnail, $time, $image)->generate();
                } elseif ($asset instanceof Asset\Folder) {
                    $asset->getPreviewImage(true);
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
        return true;
    }
}
