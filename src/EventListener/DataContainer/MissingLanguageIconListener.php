<?php

declare(strict_types=1);

/*
 * Contao Book Bundle for Contao Open Source CMS.
 * @copyright  Copyright (c) Erdmann & Freunde
 * @author     Erdmann & Freunde <https://erdmann-freunde.de>
 * @license    MIT
 * @link       http://github.com/erdmannfreunde/contao-book-bundle
 */

namespace ErdmannFreunde\BookBundle\EventListener\DataContainer;

use ErdmannFreunde\BookBundle\Models\BookArchiveModel;
use ErdmannFreunde\BookBundle\Models\BookModel;
use Terminal42\ChangeLanguage\Helper\LabelCallback;

class MissingLanguageIconListener
{
    private static array $callbacks = [
        'tl_book' => 'onPorfolioChildRecords',
    ];

    /**
     * Override core labels to show missing language information.
     */
    public function register(string $table): void
    {
        if (\array_key_exists($table, self::$callbacks)) {
            LabelCallback::createAndRegister(
                $table,
                fn (array $args, $previousResult) => $this->{self::$callbacks[$table]}($args, $previousResult)
            );
        }
    }

    /**
     * Generate missing translation warning for child records.
     */
    public function onPorfolioChildRecords(array $args, $previousResult = null): string
    {
        $row = $args[0];
        $label = (string) $previousResult;

        $archive = BookArchiveModel::findByPk($row['pid']);

        if (
            null !== $archive
            && $archive->master
            && (!$row['languageMain'] || null === BookModel::findByPk($row['languageMain']))
        ) {
            return $this->generateLabelWithWarning($label);
        }

        return $label;
    }

    private function generateLabelWithWarning(string $label, string $imgStyle = ''): string
    {
        return $label.sprintf(
            '<span style="padding-left:3px"><img src="%s" alt="%s" title="%s" style="%s"></span>',
            'bundles/terminal42changelanguage/language-warning.png',
            $GLOBALS['TL_LANG']['MSC']['noMainLanguage'],
            $GLOBALS['TL_LANG']['MSC']['noMainLanguage'],
            $imgStyle
        );
    }
}
