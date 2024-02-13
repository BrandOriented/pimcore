<?php
/**
 * This file is part of the Effiana package.
 * (c) Effiana, BrandOriented LTD
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Dominik Labudzinski <dominik@labudzinski.com>
 */
namespace Pimcore\Messenger;

/**
 * @internal
 */
class DocumentPreviewMessage
{
    public function __construct(protected int $id)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
