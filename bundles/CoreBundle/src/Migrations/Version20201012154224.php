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

namespace Pimcore\Bundle\CoreBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * @internal
 */
final class Version20201012154224 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        if ($schema->hasTable('glossary') && $schema->getTable('glossary')->hasColumn('acronym')) {
            $this->addSql('ALTER TABLE glossary DROP COLUMN acronym');
        }
    }

    public function down(Schema $schema): void
    {
        if($schema->hasTable('glossary')) {
            $this->addSql('ALTER TABLE glossary ADD COLUMN `acronym` varchar(255) DEFAULT NULL');
        }
    }
}
