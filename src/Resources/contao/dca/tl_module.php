<?php

declare(strict_types=1);

/*
 * Contao Book Bundle for Contao Open Source CMS.
 * @copyright  Copyright (c) Erdmann & Freunde
 * @author     Erdmann & Freunde <https://erdmann-freunde.de>
 * @license    MIT
 * @link       http://github.com/erdmannfreunde/contao-book-bundle
 */

$GLOBALS['TL_DCA']['tl_module']['palettes']['booklist'] = '{title_legend},name,headline,type;{config_legend},book_archives,book_readerModule,book_featured,numberOfItems,filter_categories,perPage;{nav_legend},book_filter,book_filter_reset;{redirect_legend},jumpTo;{template_legend:hide},book_template,customTpl;{image_legend:hide},imgSize;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';
$GLOBALS['TL_DCA']['tl_module']['palettes']['bookreader'] = '{title_legend},name,headline,type;{config_legend},book_archives;{template_legend:hide},book_template,customTpl;{protected_legend:hide},{image_legend:hide},imgSize;protected;{expert_legend:hide},guests,cssID,space';

/*
 * Add fields to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['fields']['book_archives'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['book_archives'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'options_callback' => ['tl_module_book', 'getBookArchives'],
    'eval' => ['multiple' => true, 'mandatory' => true],
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['book_template'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['book_template'],
    'default' => 'book_short',
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => ['tl_module_book', 'getBookTemplates'],
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(32) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['book_featured'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['book_featured'],
    'default' => 'all_items',
    'exclude' => true,
    'inputType' => 'select',
    'options' => ['all_items', 'featured', 'unfeatured'],
    'reference' => &$GLOBALS['TL_LANG']['tl_module'],
    'eval' => ['tl_class' => 'w50 clr'],
    'sql' => "varchar(16) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['book_filter'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['book_filter'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 clr'],
    'sql' => ['type' => 'boolean', 'default' => 0],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['book_filter_reset'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['book_filter_reset'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50'],
    'sql' => ['type' => 'boolean', 'default' => 0],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['filter_categories'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['filter_categories'],
    'exclude' => true,
    'filter' => true,
    'inputType' => 'select',
    'foreignKey' => 'tl_book_category.title',
    'eval' => ['multiple' => true, 'chosen' => true, 'tl_class' => 'clr w50'],
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['book_readerModule'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['book_readerModule'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => ['tl_module_book', 'getReaderModules'],
    'reference' => &$GLOBALS['TL_LANG']['tl_module'],
    'eval' => ['includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql' => 'int(10) unsigned NOT NULL default 0',
];

/**
 * Class tl_module_book.
 *
 * Provide miscellaneous methods that are used by the data configuration array.
 */
class tl_module_book extends Backend
{
    /**
     * Import the back end user object.
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('BackendUser', 'User');
    }

    /**
     * Return all book templates as array.
     */
    public function getBookTemplates(): array
    {
        return $this->getTemplateGroup('book_');
    }

    /**
     * Get all book archives and return them as array.
     */
    public function getBookArchives(): array
    {
        if (!$this->User->isAdmin && !is_array($this->User->book)) {
            return [];
        }

        $arrArchives = [];
        $objArchives = $this->Database->execute('SELECT id, title FROM tl_book_archive ORDER BY title');

        while ($objArchives->next()) {
            if ($this->User->hasAccess($objArchives->id, 'book')) {
                $arrArchives[$objArchives->id] = $objArchives->title;
            }
        }

        return $arrArchives;
    }

    /**
     * Get all book reader modules and return them as array.
     */
    public function getReaderModules(): array
    {
        $arrModules = [];
        $objModules = $this->Database->execute("SELECT m.id, m.name, t.name AS theme FROM tl_module m LEFT JOIN tl_theme t ON m.pid=t.id WHERE m.type='bookreader' ORDER BY t.name, m.name");

        while ($objModules->next()) {
            $arrModules[$objModules->theme][$objModules->id] = $objModules->name.' (ID '.$objModules->id.')';
        }

        return $arrModules;
    }
}
