<?php

declare(strict_types=1);

/*
 * Contao Book Bundle for Contao Open Source CMS.
 * @copyright  Copyright (c) Erdmann & Freunde
 * @author     Erdmann & Freunde <https://erdmann-freunde.de>
 * @license    MIT
 * @link       http://github.com/erdmannfreunde/contao-book-bundle
 */

namespace ErdmannFreunde\BookBundle\EventListener\Navigation;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\PageModel;
use ErdmannFreunde\BookBundle\Models\BookArchiveModel;
use ErdmannFreunde\BookBundle\Models\BookModel;
use Terminal42\ChangeLanguage\EventListener\Navigation\AbstractNavigationListener;

/**
 * @Hook("changelanguageNavigation")
 */
class BookNavigationListener extends AbstractNavigationListener
{
    protected function getUrlKey(): string
    {
        return 'items';
    }

    protected function findCurrent(): ?BookModel
    {
        $alias = $this->getAutoItem();

        if ('' === $alias) {
            return null;
        }

        /** @var PageModel $objPage */
        global $objPage;

        if (null === ($archives = BookArchiveModel::findBy('jumpTo', $objPage->id))) {
            return null;
        }

        // Fix Contao bug that returns a collection (see contao-changelanguage#71)
        $options = ['limit' => 1, 'return' => 'Model'];

        return BookModel::findPublishedByParentAndIdOrAlias($alias, $archives->fetchEach('id'), $options);
    }

    protected function findPublishedBy(array $columns, array $values = [], array $options = []): ?BookModel
    {
        return BookModel::findOneBy(
            $this->addPublishedConditions($columns, BookModel::getTable()),
            $values,
            $options
        );
    }
}
