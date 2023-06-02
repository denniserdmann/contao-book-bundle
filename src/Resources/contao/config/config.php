<?php

declare(strict_types=1);

/*
 * Contao Book Bundle for Contao Open Source CMS.
 * @copyright  Copyright (c) Erdmann & Freunde
 * @author     Erdmann & Freunde <https://erdmann-freunde.de>
 * @license    MIT
 * @link       http://github.com/erdmannfreunde/contao-book-bundle
 */

$GLOBALS['BE_MOD']['content']['books'] = [
    'tables' => ['tl_book_archive', 'tl_book', 'tl_book_category', 'tl_content'],
];

/*
 * Front end modules
 */
$GLOBALS['FE_MOD']['book'] = [
    'booklist' => '\\ErdmannFreunde\\BookBundle\\Modules\\ModuleBookList',
    'bookarchive' => '\\ErdmannFreunde\\BookBundle\\Modules\\ModuleBookArchive',
    'bookreader' => '\\ErdmannFreunde\\BookBundle\\Modules\\ModuleBookReader',
];

$GLOBALS['TL_MODELS']['tl_book'] = '\\ErdmannFreunde\\BookBundle\\Models\\BookModel';
$GLOBALS['TL_MODELS']['tl_book_archive'] = '\\ErdmannFreunde\\BookBundle\\Models\\BookArchiveModel';
$GLOBALS['TL_MODELS']['tl_book_category'] = '\\ErdmannFreunde\\BookBundle\\Models\\BookCategoryModel';

/*
 * Register hooks
 */
$GLOBALS['TL_HOOKS']['getSearchablePages'][] = ['\\ErdmannFreunde\\BookBundle\\Classes\\Book', 'getSearchablePages'];

/*
 * Add permissions
 */
$GLOBALS['TL_PERMISSIONS'][] = 'book';
$GLOBALS['TL_PERMISSIONS'][] = 'bookp';
