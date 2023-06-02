<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

 namespace ErdmannFreunde\BookBundle\Modules;

use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\System;
use Contao\StringUtil;
use Contao\Config;
use Contao\Input;
use Contao\Date;
#use Contao\ContentModel;

#use Contao\FilesModel;
#use Contao\FrontendTemplate;
#use Contao\FrontendUser;
use Contao\Module;

use ErdmannFreunde\BookBundle\Classes\Book;
use ErdmannFreunde\BookBundle\Models\BookArchiveModel;
use ErdmannFreunde\BookBundle\Models\BookCategoryModel;

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
    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_bookarchive';

    /**
     * Display a wildcard in the back end
     *
     * @return string
     */
    public function generate()
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request))
        {
            $objTemplate = new BackendTemplate('be_wildcard');
            $objTemplate->wildcard = '### ' . $GLOBALS['TL_LANG']['FMD']['bookarchive'][0] . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = StringUtil::specialcharsUrl(System::getContainer()->get('router')->generate('contao_backend', array('do'=>'themes', 'table'=>'tl_module', 'act'=>'edit', 'id'=>$this->id)));

            return $objTemplate->parse();
        }

        $this->book_archives = $this->sortOutProtected(StringUtil::deserialize($this->book_archives));

        // No book archives available
        if (empty($this->book_archives) || !\is_array($this->book_archives))
        {
            return '';
        }

        // Show the book reader if an item has been selected
        if ($this->book_readerModule > 0 && (isset($_GET['items']) || (Config::get('useAutoItem') && isset($_GET['auto_item']))))
        {
            return $this->getFrontendModule($this->book_readerModule, $this->strColumn);
        }

        // Hide the module if no period has been selected
        if ($this->book_jumpToCurrent == 'hide_module' && !isset($_GET['year']) && !isset($_GET['month']) && !isset($_GET['day']))
        {
            return '';
        }

        // Tag the book archives (see #2137)
        if (System::getContainer()->has('fos_http_cache.http.symfony_response_tagger'))
        {
            $responseTagger = System::getContainer()->get('fos_http_cache.http.symfony_response_tagger');
            $responseTagger->addTags(array_map(static function ($id) { return 'contao.db.tl_book_archive.' . $id; }, $this->book_archives));
        }

        return parent::generate();
    }

    /**
     * Generate the module
     */
    protected function compile()
    {
        /** @var PageModel $objPage */
        global $objPage;

        $limit = null;
        $offset = 0;
        $intBegin = 0;
        $intEnd = 0;

        $intYear = (int) Input::get('year');
        $intMonth = (int) Input::get('month');
        $intDay = (int) Input::get('day');

        // Jump to the current period
        if (!isset($_GET['year']) && !isset($_GET['month']) && !isset($_GET['day']) && $this->book_jumpToCurrent != 'all_items')
        {
            switch ($this->book_format)
            {
                case 'book_year':
                    $intYear = date('Y');
                    break;

                default:
                case 'book_month':
                    $intMonth = date('Ym');
                    break;

                case 'book_day':
                    $intDay = date('Ymd');
                    break;
            }
        }

        // Create the date object
        try
        {
            if ($intYear)
            {
                $strDate = $intYear;
                $objDate = new Date($strDate, 'Y');
                $intBegin = $objDate->yearBegin;
                $intEnd = $objDate->yearEnd;
                $this->headline .= ' ' . date('Y', $objDate->tstamp);
            }
            elseif ($intMonth)
            {
                $strDate = $intMonth;
                $objDate = new Date($strDate, 'Ym');
                $intBegin = $objDate->monthBegin;
                $intEnd = $objDate->monthEnd;
                $this->headline .= ' ' . Date::parse('F Y', $objDate->tstamp);
            }
            elseif ($intDay)
            {
                $strDate = $intDay;
                $objDate = new Date($strDate, 'Ymd');
                $intBegin = $objDate->dayBegin;
                $intEnd = $objDate->dayEnd;
                $this->headline .= ' ' . Date::parse($objPage->dateFormat, $objDate->tstamp);
            }
            elseif ($this->book_jumpToCurrent == 'all_items')
            {
                $intBegin = 0; // 1970-01-01 00:00:00
                $intEnd = min(4294967295, PHP_INT_MAX); // 2106-02-07 07:28:15
            }
        }
        catch (\OutOfBoundsException $e)
        {
            throw new PageNotFoundException('Page not found: ' . Environment::get('uri'));
        }

        $this->Template->articles = array();

        // Split the result
        if ($this->perPage > 0)
        {
            // Get the total number of items
            $intTotal = BookModel::countPublishedFromToByPids($intBegin, $intEnd, $this->book_archives);

            if ($intTotal > 0)
            {
                $total = $intTotal;

                // Get the current page
                $id = 'page_a' . $this->id;
                $page = (int) (Input::get($id) ?? 1);

                // Do not index or cache the page if the page number is outside the range
                if ($page < 1 || $page > max(ceil($total/$this->perPage), 1))
                {
                    throw new PageNotFoundException('Page not found: ' . Environment::get('uri'));
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
        $arrOptions = array();

        switch ($this->book_order)
        {
            case 'order_headline_asc':
                $arrOptions['order'] = "$t.headline";
                break;

            case 'order_headline_desc':
                $arrOptions['order'] = "$t.headline DESC";
                break;

            case 'order_random':
                $arrOptions['order'] = "RAND()";
                break;

            case 'order_date_asc':
                $arrOptions['order'] = "$t.endDate";
                break;

            default:
                $arrOptions['order'] = "$t.endDate DESC";
        }

        // Get the book items
        if (isset($limit))
        {
            $objArticles = BookModel::findPublishedFromToByPids($intBegin, $intEnd, $this->book_archives, $limit, $offset, $arrOptions);
        }
        else
        {
            $objArticles = BookModel::findPublishedFromToByPids($intBegin, $intEnd, $this->book_archives, 0, 0, $arrOptions);
        }

        // Add the articles
        if ($objArticles !== null)
        {
            $this->Template->articles = $this->parseArticles($objArticles);
        }

        $this->Template->headline = trim($this->headline);
        $this->Template->back = $GLOBALS['TL_LANG']['MSC']['goBack'];
        $this->Template->empty = $GLOBALS['TL_LANG']['MSC']['empty'];
    }
}

class_alias(ModuleBookArchive::class, 'ModuleBookArchive');