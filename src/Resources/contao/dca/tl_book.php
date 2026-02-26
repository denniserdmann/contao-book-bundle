<?php

declare(strict_types=1);

/*
 * Contao Book Bundle for Contao Open Source CMS.
 * @copyright  Copyright (c) Erdmann & Freunde
 * @author     Erdmann & Freunde <https://erdmann-freunde.de>
 * @license    MIT
 * @link       http://github.com/erdmannfreunde/contao-book-bundle
 */

use Contao\Backend;
use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\DataContainer;
use Contao\DC_Table;
use Contao\Input;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use ErdmannFreunde\BookBundle\Classes\Book;
use ErdmannFreunde\BookBundle\Models\BookArchiveModel;
use ErdmannFreunde\BookBundle\Models\BookModel;

$GLOBALS['TL_DCA']['tl_book'] = [
    // Config
    'config' => [
        'dataContainer' => DC_Table::class,
        'ptable' => 'tl_book_archive',
        'enableVersioning' => true,
        'oninvalidate_cache_tags_callback' => [
            ['tl_book', 'addSitemapCacheInvalidationTag'],
        ],
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'alias' => 'index',
                'pid,start,stop,published' => 'index',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_PARENT,
            'fields' => ['endDate DESC'],
            'headerFields' => ['title'],
            'panelLayout' => 'search,filter,sort,limit',
            'defaultSearchField' => 'title',
        ],
        'label' => [
            'fields' => ['title', 'endDate'],
            'format' => '%s <span class="label-info">[%s]</span>',
        ],
        'operations' => [
            'edit',
            'copy',
            'cut',
            'delete',
            'toggle' => [
                'href' => 'act=toggle&amp;field=published',
                'icon' => 'visible.svg',
                'showInHeader' => true,
            ],
            'feature' => [
                'href' => 'act=toggle&amp;field=featured',
                'icon' => 'featured.svg',
            ],
            'show',
        ],
    ],

    // Palettes
    'palettes' => [
        '__selector__' => ['addImage', 'source', 'overwriteMeta'],
        'default' => '{title_legend},title,alias,categories,author;{date_legend},endDate;{meta_legend},pageTitle,robots,description,serpPreview;{teaser_legend},teaser;{image_legend},addImage;{source_legend:hide},source;{expert_legend:hide},cssClass,noComments,featured;{publish_legend},published,start,stop',
    ],

    // Subpalettes
    'subpalettes' => [
        'addImage' => 'singleSRC,size,floating,fullsize,overwriteMeta',
        'source_internal' => 'jumpTo',
        'source_article' => 'articleId',
        'source_external' => 'url,target',
        'overwriteMeta' => 'alt,imageTitle,imageUrl,caption',
    ],

    // Fields
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid' => [
            'foreignKey' => 'tl_book_archive.title',
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'sorting' => [
            'label' => &$GLOBALS['TL_LANG']['MSC']['sorting'],
            'sorting' => true,
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'title' => [
            'label' => &$GLOBALS['TL_LANG']['tl_book']['title'],
            'exclude' => true,
            'search' => true,
            'sorting' => true,
            'flag' => 1,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'alias' => [
            'exclude' => true,
            'search' => false,
            'inputType' => 'text',
            'eval' => ['rgxp' => 'alias', 'unique' => true, 'maxlength' => 128, 'tl_class' => 'w50'],
            'save_callback' => [
                ['tl_book', 'generateAlias'],
            ],
            'sql' => "varchar(255) BINARY NOT NULL default ''",
        ],
        'categories' => [
            'exclude' => true,
            'filter' => true,
            'inputType' => 'select',
            'foreignKey' => 'tl_book_category.title',
            'eval' => ['multiple' => true, 'chosen' => true, 'tl_class' => 'clr w50'],
            'sql' => 'blob NULL',
        ],
        'author' => [
            'exclude' => true,
            'search' => true,
            'flag' => 1,
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'endDate' => [
            'default' => time(),
            'exclude' => true,
            'filter' => true,
            'sorting' => true,
            'flag' => 8,
            'inputType' => 'text',
            'eval' => ['rgxp' => 'date', 'doNotCopy' => true, 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql' => 'int(10) unsigned NULL',
        ],
        'endTime' => [
            'default' => time(),
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['rgxp' => 'time', 'doNotCopy' => true, 'tl_class' => 'w50'],
            'load_callback' => [
                ['tl_calendar_events', 'loadEndTime'],
            ],
            'save_callback' => [
                ['tl_calendar_events', 'setEmptyEndTime'],
            ],
            'sql' => 'int(10) NULL',
        ],
        'pageTitle' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'robots' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'select',
            'options' => ['index,follow', 'index,nofollow', 'noindex,follow', 'noindex,nofollow'],
            'eval' => ['tl_class' => 'w50', 'includeBlankOption' => true],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'description' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'textarea',
            'eval' => ['style' => 'height:60px', 'decodeEntities' => true, 'tl_class' => 'clr'],
            'sql' => 'text NULL',
        ],
        'serpPreview' => [
            'label' => &$GLOBALS['TL_LANG']['MSC']['serpPreview'],
            'exclude' => true,
            'inputType' => 'serpPreview',
            'eval' => ['url_callback' => ['tl_book', 'getSerpUrl'], 'title_tag_callback' => ['tl_book', 'getTitleTag'], 'titleFields' => ['pageTitle', 'tite'], 'descriptionFields' => ['description', 'teaser']],
            'sql' => null,
        ],
        'teaser' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'textarea',
            'eval' => ['rte' => 'tinyMCE', 'helpwizard' => true, 'tl_class' => 'clr'],
            'explanation' => 'insertTags',
            'sql' => 'mediumtext NULL',
        ],
        'addImage' => [
            'exclude' => true,
            'inputType' => 'checkbox',
            'eval' => ['submitOnChange' => true],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'overwriteMeta' => [
            'exclude' => true,
            'inputType' => 'checkbox',
            'eval' => ['submitOnChange' => true, 'tl_class' => 'w50 clr'],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'singleSRC' => [
            'exclude' => true,
            'inputType' => 'fileTree',
            'eval' => ['fieldType' => 'radio', 'filesOnly' => true, 'extensions' => Config::get('validImageTypes'), 'mandatory' => true],
            'sql' => 'binary(16) NULL',
        ],
        'alt' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'imageTitle' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'size' => [
            'exclude' => true,
            'inputType' => 'imageSize',
            'reference' => &$GLOBALS['TL_LANG']['MSC'],
            'eval' => ['rgxp' => 'natural', 'includeBlankOption' => true, 'nospace' => true, 'helpwizard' => true, 'tl_class' => 'w50'],
            'options_callback' => static fn () => System::getContainer()->get('contao.image.sizes')->getOptionsForUser(BackendUser::getInstance()),
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'imageUrl' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['rgxp' => 'url', 'decodeEntities' => true, 'maxlength' => 255, 'dcaPicker' => true, 'tl_class' => 'w50 wizard'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'fullsize' => [
            'exclude' => true,
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50 m12'],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'caption' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'allowHtml' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'floating' => [
            'default' => 'above',
            'exclude' => true,
            'inputType' => 'radioTable',
            'options' => ['above', 'left', 'right', 'below'],
            'eval' => ['cols' => 4, 'tl_class' => 'w50'],
            'reference' => &$GLOBALS['TL_LANG']['MSC'],
            'sql' => "varchar(12) NOT NULL default ''",
        ],
        'source' => [
            'default' => 'default',
            'exclude' => true,
            'filter' => true,
            'inputType' => 'radio',
            'options_callback' => ['tl_book', 'getSourceOptions'],
            'reference' => &$GLOBALS['TL_LANG']['tl_book'],
            'eval' => ['submitOnChange' => true, 'helpwizard' => true],
            'sql' => "varchar(12) NOT NULL default ''",
        ],
        'jumpTo' => [
            'exclude' => true,
            'inputType' => 'pageTree',
            'foreignKey' => 'tl_page.title',
            'eval' => ['mandatory' => true, 'fieldType' => 'radio'],
            'sql' => "int(10) unsigned NOT NULL default '0'",
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'articleId' => [
            'exclude' => true,
            'inputType' => 'select',
            'options_callback' => ['tl_book', 'getArticleAlias'],
            'eval' => ['chosen' => true, 'mandatory' => true],
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'url' => [
            'exclude' => true,
            'search' => false,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'decodeEntities' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'target' => [
            'exclude' => true,
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50 m12'],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'cssClass' => [
            'exclude' => true,
            'inputType' => 'text',
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'published' => [
            'exclude' => true,
            'filter' => true,
            'flag' => 1,
            'inputType' => 'checkbox',
            'eval' => ['doNotCopy' => true],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'start' => [
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql' => "varchar(10) NOT NULL default ''",
        ],
        'stop' => [
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql' => "varchar(10) NOT NULL default ''",
        ],
        'featured' => [
            'exclude' => true,
            'filter' => true,
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50'],
            'sql' => "char(1) NOT NULL default ''",
        ],
    ],
];

/**
 * Class tl_book.
 */
class tl_book extends Backend
{
    /**
     * Import the back end user object.
     */
    public function __construct()
    {
        parent::__construct();
        $this->import(BackendUser::class, 'User');
    }

    /**
     * Check permissions to edit table tl_book.
     *
     * @throws AccessDeniedException
     */
    public function checkPermission(): void
    {
        if ($this->User->isAdmin) {
            return;
        }

        // Set the root IDs
        if (empty($this->User->book) || !is_array($this->User->book)) {
            $root = [0];
        } else {
            $root = $this->User->book;
        }

        $id = Input::get('id');

        // Check current action
        switch (Input::get('act')) {
            case 'paste':
            case 'select':
                if (!in_array($id, $root, true)) {
                    throw new AccessDeniedException('Not enough permissions to access book archive ID '.$id.'.');
                }
                break;

            case 'create':
                if (!Input::get('pid') || !in_array(Input::get('pid'), $root, true)) {
                    throw new AccessDeniedException('Not enough permissions to create book items in book archive ID '.Input::get('pid').'.');
                }
                break;

            case 'cut':
            case 'copy':
                if ('cut' === Input::get('act') && 1 === Input::get('mode')) {
                    $objArchive = $this->Database->prepare('SELECT pid FROM tl_book WHERE id=?')
                        ->limit(1)
                        ->execute(Input::get('pid'))
                    ;

                    if ($objArchive->numRows < 1) {
                        throw new AccessDeniedException('Invalid book item ID '.Input::get('pid').'.');
                    }

                    $pid = $objArchive->pid;
                } else {
                    $pid = Input::get('pid');
                }

                if (!in_array($pid, $root, true)) {
                    throw new AccessDeniedException('Not enough permissions to '.Input::get('act').' book item ID '.$id.' to book archive ID '.$pid.'.');
                }
                // no break

            case 'edit':
            case 'show':
            case 'delete':
            case 'toggle':
            case 'feature':
                $objArchive = $this->Database->prepare('SELECT pid FROM tl_book WHERE id=?')
                    ->limit(1)
                    ->execute($id)
                ;

                if ($objArchive->numRows < 1) {
                    throw new AccessDeniedException('Invalid book item ID '.$id.'.');
                }

                if (!in_array($objArchive->pid, $root, true)) {
                    throw new AccessDeniedException('Not enough permissions to '.Input::get('act').' book item ID '.$id.' of book archive ID '.$objArchive->pid.'.');
                }
                break;

            case 'editAll':
            case 'deleteAll':
            case 'overrideAll':
            case 'cutAll':
            case 'copyAll':
                if (!in_array($id, $root, true)) {
                    throw new AccessDeniedException('Not enough permissions to access book archive ID '.$id.'.');
                }

                $objArchive = $this->Database->prepare('SELECT id FROM tl_book WHERE pid=?')
                    ->execute($id)
                ;

                $objSession = System::getContainer()->get('request_stack')->getSession();

                $session = $objSession->all();
                $session['CURRENT']['IDS'] = array_intersect((array) $session['CURRENT']['IDS'], $objArchive->fetchEach('id'));
                $objSession->replace($session);
                break;

            default:
                if (Input::get('act')) {
                    throw new AccessDeniedException('Invalid command "'.Input::get('act').'".');
                }

                if (!in_array($id, $root, true)) {
                    throw new AccessDeniedException('Not enough permissions to access book archive ID '.$id.'.');
                }
                break;
        }
    }

    /**
     * Auto-generate the book alias if it has not been set yet.
     *
     * @return string
     *
     * @throws Exception
     */
    public function generateAlias($varValue, DataContainer $dc)
    {
        $aliasExists = fn (string $alias): bool => $this->Database->prepare('SELECT id FROM tl_book WHERE alias=? AND id!=?')->execute($alias, $dc->id)->numRows > 0;

        // Generate alias if there is none
        if (!$varValue) {
            $varValue = System::getContainer()->get('contao.slug')->generate($dc->activeRecord->title, BookArchiveModel::findById($dc->activeRecord->pid)->jumpTo, $aliasExists);
        } elseif (preg_match('/^[1-9]\d*$/', $varValue)) {
            throw new Exception(sprintf($GLOBALS['TL_LANG']['ERR']['aliasNumeric'], $varValue));
        } elseif ($aliasExists($varValue)) {
            throw new Exception(sprintf($GLOBALS['TL_LANG']['ERR']['aliasExists'], $varValue));
        }

        return $varValue;
    }

    /**
     * Return the SERP URL.
     *
     * @return string
     */
    public function getSerpUrl(BookModel $model)
    {
        return Book::generateBookUrl($model, false, true);
    }

    /**
     * Return the title tag from the associated page layout.
     *
     * @return string
     */
    public function getTitleTag(BookModel $model)
    {
        if (!$archive = $model->getRelated('pid')) {
            return '';
        }

        if (!$page = $archive->getRelated('jumpTo')) {
            return '';
        }

        $page->loadDetails();

        if (!$layout = $page->getRelated('layout')) {
            return '';
        }

        $origObjPage = $GLOBALS['objPage'] ?? null;

        // Override the global page object, so we can replace the insert tags
        $GLOBALS['objPage'] = $page;

        $insertTagParser = System::getContainer()->get('contao.insert_tag.parser');

        $title = implode(
            '%s',
            array_map(
                static fn ($strVal) => str_replace('%', '%%', $insertTagParser->replace($strVal)),
                explode('{{page::pageTitle}}', $layout->titleTag ?: '{{page::pageTitle}} - {{page::rootPageTitle}}', 2),
            ),
        );

        $GLOBALS['objPage'] = $origObjPage;

        return $title;
    }

    /**
     * Get all articles and return them as array.
     */
    public function getArticleAlias(DataContainer $dc): array
    {
        $arrPids = [];
        $arrAlias = [];

        if (!$this->User->isAdmin) {
            foreach ($this->User->pagemounts as $id) {
                $arrPids[] = $id;
                $arrPids = array_merge($arrPids, $this->Database->getChildRecords($id, 'tl_page'));
            }

            if (empty($arrPids)) {
                return $arrAlias;
            }

            $objAlias = $this->Database->prepare('SELECT a.id, a.title, a.inColumn, p.title AS parent FROM tl_article a LEFT JOIN tl_page p ON p.id=a.pid WHERE a.pid IN('.implode(',', array_map('intval', array_unique($arrPids))).') ORDER BY parent, a.sorting')
                ->execute($dc->id)
            ;
        } else {
            $objAlias = $this->Database->prepare('SELECT a.id, a.title, a.inColumn, p.title AS parent FROM tl_article a LEFT JOIN tl_page p ON p.id=a.pid ORDER BY parent, a.sorting')
                ->execute($dc->id)
            ;
        }

        if ($objAlias->numRows) {
            System::loadLanguageFile('tl_article');

            while ($objAlias->next()) {
                $arrAlias[$objAlias->parent][$objAlias->id] = $objAlias->title.' ('.($GLOBALS['TL_LANG']['tl_article'][$objAlias->inColumn] ?: $objAlias->inColumn).', ID '.$objAlias->id.')';
            }
        }

        return $arrAlias;
    }

    /**
     * Add the source options depending on the allowed fields (see #5498).
     */
    public function getSourceOptions(DataContainer $dc): array
    {
        if ($this->User->isAdmin) {
            return ['default', 'internal', 'article', 'external'];
        }

        $arrOptions = ['default'];

        // Add the "internal" option
        if ($this->User->hasAccess('tl_book::jumpTo', 'alexf')) {
            $arrOptions[] = 'internal';
        }

        // Add the "article" option
        if ($this->User->hasAccess('tl_book::articleId', 'alexf')) {
            $arrOptions[] = 'article';
        }

        // Add the "external" option
        if ($this->User->hasAccess('tl_book::url', 'alexf') && $this->User->hasAccess('tl_book::target', 'alexf')) {
            $arrOptions[] = 'external';
        }

        // Add the option currently set
        if ($dc->activeRecord && '' !== $dc->activeRecord->source) {
            $arrOptions[] = $dc->activeRecord->source;
            $arrOptions = array_unique($arrOptions);
        }

        return $arrOptions;
    }

    /**
     * @param DataContainer $dc
     *
     * @return array
     */
    public function addSitemapCacheInvalidationTag($dc, array $tags)
    {
        $archiveModel = BookArchiveModel::findById($dc->activeRecord->pid);

        if (null === $archiveModel) {
            return $tags;
        }

        $pageModel = PageModel::findWithDetails($archiveModel->jumpTo);

        if (null === $pageModel) {
            return $tags;
        }

        return array_merge($tags, ['contao.sitemap.'.$pageModel->rootId]);
    }
}
