<?php

declare(strict_types=1);

/*
 * Contao Book Bundle for Contao Open Source CMS.
 * @copyright  Copyright (c) Erdmann & Freunde
 * @author     Erdmann & Freunde <https://erdmann-freunde.de>
 * @license    MIT
 * @link       http://github.com/erdmannfreunde/contao-book-bundle
 */

namespace ErdmannFreunde\BookBundle\Migration\Version410;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class ImgSizeMigration extends AbstractMigration
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_book'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_book');

        return isset($columns['imgsize']) && !isset($columns['size']);
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $this->connection->executeStatement("ALTER TABLE tl_book CHANGE imgSize size VARCHAR(64) NOT NULL default ''");

        return $this->createResult(true);
    }
}
