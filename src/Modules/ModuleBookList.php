<?php

declare(strict_types=1);

/*
 * Contao Book Bundle for Contao Open Source CMS.
 * @copyright  Copyright (c) Erdmann & Freunde
 * @author     Erdmann & Freunde <https://erdmann-freunde.de>
 * @license    MIT
 * @link       http://github.com/erdmannfreunde/contao-book-bundle
 */

namespace ErdmannFreunde\BookBundle\Modules;

use Contao\BackendTemplate;
use Contao\Config;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\Environment;
use Contao\Input;
use Contao\Model\Collection;
use Contao\Pagination;
use Contao\StringUtil;
use Contao\System;
use ErdmannFreunde\BookBundle\Models\BookCategoryModel;
use ErdmannFreunde\BookBundle\Models\BookModel;

/**
 * Class ModuleBookList.
 *
 * Front end module "book list".
 */
class ModuleBookList extends ModuleBook
{
    /**
     * Template.
     *
     * @var string
     */
    protected $strTemplate = 'mod_booklist';

    /**
     * Display a wildcard in the back end.
     */
    public function generate(): string
    {
        if (TL_MODE === 'BE') {
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### '.$GLOBALS['TL_LANG']['FMD']['booklist'][0].' ###';
            $objTemplate->headline = $this->title;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = StringUtil::specialcharsUrl(System::getContainer()->get('router')->generate('contao_backend', ['do' => 'themes', 'table' => 'tl_module', 'act' => 'edit', 'id' => $this->id]));

            return $objTemplate->parse();
        }

        return parent::generate();
    }

    /**
     * Generate the module.
     *
     * @throws \Exception
     */
    protected function compile(): void
    {
        // Add the "reset categories" link
        if ($this->book_filter_reset) {
            $this->Template->book_filter_reset = $GLOBALS['TL_LANG']['MSC']['filter_reset'];
        }

        $objCategories = BookCategoryModel::findAll([
            'column' => 'published',
            'value' => 1,
            'order' => 'sorting ASC',
        ]);

        if (null !== $objCategories && $this->book_filter) {
            $this->Template->categories = $objCategories;
        }

        $limit = null;
        $offset = (int) $this->skipFirst;

        // Maximum number of items
        if ($this->numberOfItems > 0) {
            $limit = $this->numberOfItems;
        }

        // Handle featured book-items
        if ('featured' === $this->book_featured) {
            $blnFeatured = true;
        } elseif ('unfeatured' === $this->book_featured) {
            $blnFeatured = false;
        } else {
            $blnFeatured = null;
        }

        $arrColumns = ['tl_book.published=?'];
        $arrValues = ['1'];
        $arrOptions = [
            'order' => 'tl_book.endDate DESC',
        ];

        if (!$this->filter_categories && !empty($limit)) {
            $arrOptions['limit'] = $limit;
        }

        // Handle featured/unfeatured items
        if ('featured' === $this->book_featured || 'unfeatured' === $this->book_featured) {
            $arrColumns[] = 'tl_book.featured=?';
            $arrValues[] = 'featured' === $this->book_featured ? '1' : '';
        }

        $arrPids = StringUtil::deserialize($this->book_archives);
        $arrColumns[] = 'tl_book.pid IN('.implode(',', array_map('\intval', $arrPids)).')';

        $arrCategoryIds = [];

        // Pre-filter items based on filter_categories
        if ($this->filter_categories) {
            $arrCategoryIds = StringUtil::deserialize($this->filter_categories);
        }

        // add book pagination
        // Get the total number of items
        $intTotal = $this->countItems($arrPids, $blnFeatured, $arrCategoryIds);

        if ($intTotal < 1) {
            return;
        }

        $total = $intTotal - $offset;

        // Split the results
        if ($this->perPage > 0 && (!isset($limit) || $this->numberOfItems > $this->perPage)) {
            // Adjust the overall limit
            if (isset($limit)) {
                $total = min($limit, $total);
            }

            // Get the current page
            $id = 'page_n'.$this->id;
            $page = Input::get($id) ?? 1;

            // Do not index or cache the page if the page number is outside the range
            if ($page < 1 || $page > max(ceil($total / $this->perPage), 1)) {
                throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
            }

            // Set limit and offset
            $limit = (int) $this->perPage;
            $offset += (max($page, 1) - 1) * $this->perPage;
            $skip = (int) $this->skipFirst;

            // Overall limit
            if ($offset + $limit > $total + $skip) {
                $limit = $total + $skip - $offset;
            }

            // Add the pagination menu
            $objPagination = new Pagination($total, $this->perPage, Config::get('maxPaginationLinks'), $id);
            $this->Template->pagination = $objPagination->generate("\n  ");
        }

        $objItems = $this->fetchItems($arrPids, $blnFeatured, ($limit ?: 0), $offset, $arrCategoryIds);

        if (null !== $objItems) {
            $this->Template->items = $this->parseItems($objItems);
        }
    }

    /**
     * Count the total matching items.
     *
     * @param array $bookArchives
     * @param bool  $blnFeatured
     *
     * @return int
     */
    protected function countItems($bookArchives, $blnFeatured, $arrCategories)
    {
        return BookModel::countPublishedByPids($bookArchives, $blnFeatured, $arrCategories);
    }

    /**
     * Fetch the matching items.
     *
     * @param array $bookArchives
     * @param bool  $blnFeatured
     * @param int   $limit
     * @param int   $offset
     *
     * @return Collection|array<BookModel>|BookModel|null
     */
    protected function fetchItems($bookArchives, $blnFeatured, $limit, $offset, $arrCategories)
    {
        $order = 'tl_book.endDate IS NOT NULL, tl_book.endDate DESC';

        return BookModel::findPublishedByPids($bookArchives, $blnFeatured, $limit, $offset, ['order' => $order], $arrCategories);
    }
}
