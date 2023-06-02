<?php

declare(strict_types=1);

/*
 * Contao Book Bundle for Contao Open Source CMS.
 * @copyright  Copyright (c) Erdmann & Freunde
 * @author     Erdmann & Freunde <https://erdmann-freunde.de>
 * @license    MIT
 * @link       http://github.com/erdmannfreunde/contao-book-bundle
 */

use Contao\Input;

if ('book' === Input::get('do')) {
    $GLOBALS['TL_DCA']['tl_content']['config']['ptable'] = 'tl_book';
}
