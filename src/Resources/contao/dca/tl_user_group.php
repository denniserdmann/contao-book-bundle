<?php

declare(strict_types=1);

/*
 * Contao Book Bundle for Contao Open Source CMS.
 * @copyright  Copyright (c) Erdmann & Freunde
 * @author     Erdmann & Freunde <https://erdmann-freunde.de>
 * @license    MIT
 * @link       http://github.com/erdmannfreunde/contao-book-bundle
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

// Extend the default palette
PaletteManipulator::create()
    ->addLegend('book_legend', 'amg_legend', PaletteManipulator::POSITION_BEFORE)
    ->addField(['book', 'bookp'], 'book_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_user_group')
;

// Add fields to tl_user_group
$GLOBALS['TL_DCA']['tl_user_group']['fields']['book'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_user']['book'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'foreignKey' => 'tl_book_archive.title',
    'eval' => ['multiple' => true],
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_user_group']['fields']['bookp'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_user']['bookp'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'options' => ['create', 'delete'],
    'reference' => &$GLOBALS['TL_LANG']['MSC'],
    'eval' => ['multiple' => true],
    'sql' => 'blob NULL',
];
