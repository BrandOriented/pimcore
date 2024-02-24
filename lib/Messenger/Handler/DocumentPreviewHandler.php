<?php
/**
 * This file is part of the Effiana package.
 * (c) Effiana, BrandOriented LTD
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Dominik Labudzinski <dominik@labudzinski.com>
 */
namespace Pimcore\Messenger\Handler;

use Exception;
use Pimcore\Document\Adapter;
use Pimcore\Messenger\DocumentPreviewMessage;
use Pimcore\Model\Asset;
use Pimcore\Tool;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;
use Throwable;

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

    private function process(array $jobs): void
    {
        /**
         * @var DocumentPreviewMessage $message
         * @var Acknowledger $ack
         */
        foreach ($jobs as [$message, $ack]) {
            try {
                $asset = Asset\Document::getById($message->getId());
                if($asset instanceof Asset\Document) {
                    $this->getDefaultAdapter()?->getPdf($asset);
                    $ack->ack($message);
                }
            } catch (Throwable $e) {
                $ack->nack($e);
            }
        }
    }

    /**
     * @throws Exception
     */
    private function getDefaultAdapter(): ?Adapter
    {
        foreach (['Gotenberg', 'LibreOffice', 'Ghostscript'] as $adapter) {
            $adapterClass = '\\Pimcore\\Document\\Adapter\\' . $adapter;
            if (Tool::classExists($adapterClass)) {
                try {
                    $adapter = new $adapterClass();
                    if ($adapter->isAvailable()) {
                        return $adapter;
                    }
                } catch (Exception $e) {
                    $this->logger->error((string) $e);
                }
            }
        }

        return null;
    }
}
