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
use Contao\Date;
use Contao\Environment;
use Contao\Input;
use Contao\Pagination;
use Contao\StringUtil;
use Contao\System;
use ErdmannFreunde\BookBundle\Models\BookModel;

/**
 * Front end module "book archive".
 *
 * @property array  $book_archives
 * @property string $book_jumpToCurrent
 * @property string $book_format
 * @property string $book_order
 * @property int    $book_readerModule
 */
class ModuleBookArchive extends ModuleBook
{
    protected $strTemplate = 'mod_bookarchive';

    public function generate(): string
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
            $objTemplate = new BackendTemplate('be_wildcard');
            $objTemplate->wildcard = '### '.mb_strtoupper($GLOBALS['TL_LANG']['FMD']['bookarchive'][0]).' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = StringUtil::specialcharsUrl(System::getContainer()->get('router')->generate('contao_backend', ['do' => 'themes', 'table' => 'tl_module', 'act' => 'edit', 'id' => $this->id]));

            return $objTemplate->parse();
        }

        $this->book_archives = $this->sortOutProtected(StringUtil::deserialize($this->book_archives));

        // No book archives available
        if (empty($this->book_archives)) {
            return '';
        }

        // Show the book reader if an item has been selected
        if ($this->book_readerModule > 0 && (isset($_GET['items']) || (Config::get('useAutoItem') && isset($_GET['auto_item'])))) {
            return $this->getFrontendModule($this->book_readerModule, $this->strColumn);
        }

        // Hide the module if no period has been selected
        if ('hide_module' === $this->book_jumpToCurrent && !isset($_GET['year']) && !isset($_GET['month']) && !isset($_GET['day'])) {
            return '';
        }

        // Tag the book archives (see #2137)
        if (System::getContainer()->has('fos_http_cache.http.symfony_response_tagger')) {
            $responseTagger = System::getContainer()->get('fos_http_cache.http.symfony_response_tagger');
            $responseTagger->addTags(array_map(static fn ($id) => 'contao.db.tl_book_archive.'.$id, $this->book_archives));
        }

        return parent::generate();
    }

    protected function compile(): void
    {
        global $objPage;

        $limit = null;
        $offset = 0;
        $intBegin = 0;
        $intEnd = 0;

        $intYear = (int) Input::get('year');
        $intMonth = (int) Input::get('month');
        $intDay = (int) Input::get('day');

        // Jump to the current period
        if (!isset($_GET['year']) && !isset($_GET['month']) && !isset($_GET['day']) && 'all_items' !== $this->book_jumpToCurrent) {
            switch ($this->book_format) {
                case 'book_year':
                    $intYear = (int) date('Y');
                    break;

                case 'book_day':
                    $intDay = (int) date('Ymd');
                    break;

                default:
                case 'book_month':
                    $intMonth = (int) date('Ym');
                    break;
            }
        }

        // Create the date object
        try {
            if ($intYear) {
                $objDate = new Date((string) $intYear, 'Y');
                $intBegin = $objDate->yearBegin;
                $intEnd = $objDate->yearEnd;
                $this->headline .= ' '.date('Y', $objDate->tstamp);
            } elseif ($intMonth) {
                $objDate = new Date((string) $intMonth, 'Ym');
                $intBegin = $objDate->monthBegin;
                $intEnd = $objDate->monthEnd;
                $this->headline .= ' '.Date::parse('F Y', $objDate->tstamp);
            } elseif ($intDay) {
                $objDate = new Date((string) $intDay, 'Ymd');
                $intBegin = $objDate->dayBegin;
                $intEnd = $objDate->dayEnd;
                $this->headline .= ' '.Date::parse($objPage->dateFormat, $objDate->tstamp);
            } elseif ('all_items' === $this->book_jumpToCurrent) {
                $intBegin = 0; // 1970-01-01 00:00:00
                $intEnd = min(4294967295, PHP_INT_MAX); // 2106-02-07 07:28:15
            }
        } catch (\OutOfBoundsException) {
            throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
        }

        $this->Template->articles = [];

        // Split the result
        if ($this->perPage > 0) {
            // Get the total number of items
            $intTotal = BookModel::countPublishedFromToByPids($intBegin, $intEnd, $this->book_archives);

            if ($intTotal > 0) {
                $total = $intTotal;

                // Get the current page
                $id = 'page_a'.$this->id;
                $page = (int) (Input::get($id) ?? 1);

                // Do not index or cache the page if the page number is outside the range
                if ($page < 1 || $page > max(ceil($total / $this->perPage), 1)) {
                    throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
                }

                // Set limit and offset
                $limit = $this->perPage;
                $offset = (max($page, 1) - 1) * $this->perPage;

                // Add the pagination menu
                $objPagination = new Pagination($total, $this->perPage, Config::get('maxPaginationLinks'), $id);
                $this->Template->pagination = $objPagination->generate("\n  ");
            }
        }

        // Determine sorting
        $t = BookModel::getTable();
        $arrOptions = [];

        $arrOptions['order'] = match ($this->book_order) {
            'order_headline_asc' => "$t.headline",
            'order_headline_desc' => "$t.headline DESC",
            'order_random' => 'RAND()',
            'order_date_asc' => "$t.endDate",
            default => "$t.endDate DESC",
        };

        // Get the book items
        $objArticles = BookModel::findPublishedFromToByPids($intBegin, $intEnd, $this->book_archives, $limit ?? 0, $offset, $arrOptions);

        // Add the articles
        if (null !== $objArticles) {
            $this->Template->articles = $this->parseItems($objArticles);
        }

        $this->Template->headline = trim($this->headline);
        $this->Template->back = $GLOBALS['TL_LANG']['MSC']['goBack'];
        $this->Template->empty = $GLOBALS['TL_LANG']['MSC']['empty'];
    }
}
