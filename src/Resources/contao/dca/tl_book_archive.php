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
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\DataContainer;
use Contao\DC_Table;
use Contao\Input;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;

$GLOBALS['TL_DCA']['tl_book_archive'] = [
    // Config
    'config' => [
        'dataContainer' => DC_Table::class,
        'ctable' => ['tl_book'],
        'switchToEdit' => true,
        'enableVersioning' => true,
        'markAsCopy' => 'title',
        'onload_callback' => [
            ['tl_book_archive', 'checkPermission'],
        ],
        'oncreate_callback' => [
            ['tl_book_archive', 'adjustPermissions'],
        ],
        'oncopy_callback' => [
            ['tl_book_archive', 'adjustPermissions'],
        ],
        'oninvalidate_cache_tags_callback' => [
            ['tl_book_archive', 'addSitemapCacheInvalidationTag'],
        ],
        'sql' => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_SORTED,
            'fields' => ['title'],
            'flag' => DataContainer::SORT_INITIAL_LETTER_ASC,
            'panelLayout' => 'search,filter,limit',
            'defaultSearchField' => 'title',
        ],
        'label' => [
            'fields' => ['title'],
            'format' => '%s',
        ],
    ],

    // Palettes
    'palettes' => [
        '__selector__' => ['protected'],
        'default' => '{title_legend},title,jumpTo;{protected_legend:hide},protected;',
    ],

    // Subpalettes
    'subpalettes' => [
        'protected' => 'groups',
    ],

    // Fields
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'title' => [
            'label' => &$GLOBALS['TL_LANG']['tl_book_archive']['title'],
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'jumpTo' => [
            'label' => &$GLOBALS['TL_LANG']['tl_book_archive']['jumpTo'],
            'exclude' => true,
            'inputType' => 'pageTree',
            'foreignKey' => 'tl_page.title',
            'eval' => ['mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'clr'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
        ],
        'protected' => [
            'label' => &$GLOBALS['TL_LANG']['tl_book_archive']['protected'],
            'exclude' => true,
            'filter' => true,
            'inputType' => 'checkbox',
            'eval' => ['submitOnChange' => true, 'isBoolean' => true],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'groups' => [
            'label' => &$GLOBALS['TL_LANG']['tl_book_archive']['groups'],
            'exclude' => true,
            'inputType' => 'checkbox',
            'foreignKey' => 'tl_member_group.name',
            'eval' => ['mandatory' => true, 'multiple' => true],
            'sql' => 'blob NULL',
            'relation' => ['type' => 'hasMany', 'load' => 'lazy'],
        ],
    ],
];

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 */
class tl_book_archive extends Backend
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
     * Check permissions to edit table tl_book_archive.
     *
     * @throws AccessDeniedException
     */
    public function checkPermission(): void
    {
        if ($this->User->isAdmin) {
            return;
        }

        // Set root IDs
        if (empty($this->User->book) || !is_array($this->User->book)) {
            $root = [0];
        } else {
            $root = $this->User->book;
        }

        $GLOBALS['TL_DCA']['tl_book_archive']['list']['sorting']['root'] = $root;

        // Check permissions to add archives
        if (!$this->User->hasAccess('create', 'bookp')) {
            $GLOBALS['TL_DCA']['tl_book_archive']['config']['closed'] = true;
            $GLOBALS['TL_DCA']['tl_book_archive']['config']['notCreatable'] = true;
            $GLOBALS['TL_DCA']['tl_book_archive']['config']['notCopyable'] = true;
        }

        // Check permissions to delete calendars
        if (!$this->User->hasAccess('delete', 'bookp')) {
            $GLOBALS['TL_DCA']['tl_book_archive']['config']['notDeletable'] = true;
        }

        $objSession = System::getContainer()->get('request_stack')->getSession();

        // Check current action
        switch (Input::get('act')) {
            case 'select':
                // Allow
                break;

            case 'create':
                if (!$this->User->hasAccess('create', 'bookp')) {
                    throw new AccessDeniedException('Not enough permissions to create book archives.');
                }
                break;

            case 'edit':
            case 'copy':
            case 'delete':
            case 'show':
                if (!in_array(Input::get('id'), $root, true) || ('delete' === Input::get('act') && !$this->User->hasAccess('delete', 'bookp'))) {
                    throw new AccessDeniedException('Not enough permissions to '.Input::get('act').' book archive ID '.Input::get('id').'.');
                }
                break;

            case 'editAll':
            case 'deleteAll':
            case 'overrideAll':
            case 'copyAll':
                $session = $objSession->all();

                if ('deleteAll' === Input::get('act') && !$this->User->hasAccess('delete', 'bookp')) {
                    $session['CURRENT']['IDS'] = [];
                } else {
                    $session['CURRENT']['IDS'] = array_intersect((array) $session['CURRENT']['IDS'], $root);
                }
                $objSession->replace($session);
                break;

            default:
                if (Input::get('act')) {
                    throw new AccessDeniedException('Not enough permissions to '.Input::get('act').' book archives.');
                }
                break;
        }
    }

    /**
     * Add the new archive to the permissions.
     */
    public function adjustPermissions($insertId): void
    {
        // The oncreate_callback passes $insertId as second argument
        if (4 === func_num_args()) {
            $insertId = func_get_arg(1);
        }

        if ($this->User->isAdmin) {
            return;
        }

        // Set root IDs
        if (empty($this->User->book) || !is_array($this->User->book)) {
            $root = [0];
        } else {
            $root = $this->User->book;
        }

        // The archive is enabled already
        if (in_array($insertId, $root, true)) {
            return;
        }

        $objSessionBag = System::getContainer()->get('request_stack')->getSession()->getBag('contao_backend');

        $arrNew = $objSessionBag->get('new_records');

        if (is_array($arrNew['tl_book_archive']) && in_array($insertId, $arrNew['tl_book_archive'], true)) {
            // Add the permissions on group level
            if ('custom' !== $this->User->inherit) {
                $objGroup = $this->Database->execute('SELECT id, book, bookp FROM tl_user_group WHERE id IN('.implode(',', array_map('\intval', $this->User->groups)).')');

                while ($objGroup->next()) {
                    $arrBookp = StringUtil::deserialize($objGroup->bookp);

                    if (is_array($arrBookp) && in_array('create', $arrBookp, true)) {
                        $arrBook = StringUtil::deserialize($objGroup->book, true);
                        $arrBook[] = $insertId;

                        $this->Database->prepare('UPDATE tl_user_group SET book=? WHERE id=?')
                            ->execute(serialize($arrBook), $objGroup->id)
                        ;
                    }
                }
            }

            // Add the permissions on user level
            if ('group' !== $this->User->inherit) {
                $objUser = $this->Database->prepare('SELECT book, bookp FROM tl_user WHERE id=?')
                    ->limit(1)
                    ->execute($this->User->id)
                ;

                $arrBookp = StringUtil::deserialize($objUser->bookp);

                if (is_array($arrBookp) && in_array('create', $arrBookp, true)) {
                    $arrBook = StringUtil::deserialize($objUser->book, true);
                    $arrBook[] = $insertId;

                    $this->Database->prepare('UPDATE tl_user SET book=? WHERE id=?')
                        ->execute(serialize($arrBook), $this->User->id)
                    ;
                }
            }

            // Add the new element to the user object
            $root[] = $insertId;
            $this->User->book = $root;
        }
    }

    /**
     * @param DataContainer $dc
     */
    public function addSitemapCacheInvalidationTag($dc, array $tags): array
    {
        $pageModel = PageModel::findWithDetails($dc->activeRecord->jumpTo);

        if (null === $pageModel) {
            return $tags;
        }

        return array_merge($tags, ['contao.sitemap.'.$pageModel->rootId]);
    }
}
