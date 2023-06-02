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

// Extend the default palettes
PaletteManipulator::create()
    ->addLegend('book_legend', 'amg_legend', PaletteManipulator::POSITION_BEFORE)
    ->addField(['book', 'bookp'], 'book_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('extend', 'tl_user')
    ->applyToPalette('custom', 'tl_user')
;

// Add fields to tl_user
$GLOBALS['TL_DCA']['tl_user']['fields']['book'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'foreignKey' => 'tl_book_archive.title',
    'eval' => ['multiple' => true],
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_user']['fields']['bookp'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'options' => ['create', 'delete'],
    'reference' => &$GLOBALS['TL_LANG']['MSC'],
    'eval' => ['multiple' => true],
    'sql' => 'blob NULL',
];
